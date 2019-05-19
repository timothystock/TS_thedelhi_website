<?php
/*
Plugin Name: WooCommerce Opening Hours / Delivery Times
Plugin URI: https://www.simbahosting.co.uk/s3/product/woocommerce-opening-hours-delivery-times/
Description: Adds opening hours to a shop, allowing a shop to open/close or to restrict deliveries to chosen times
Author: DavidAnderson
Version: 1.10.6
License: GPLv3+
Text Domain: openinghours
Author URI: https://www.simbahosting.co.uk
WC requires at least: 3.0.0
WC tested up to: 3.5.0
// N.B. WooCommerce doesn't check the minor version. So, '3.5.0' means 'the entire 3.5 series'
*/

/*
Possible to-do:
- Have the initial suggested time be rounded according to the picker's rounding rules
- Document the openinghours_frontend_initialvalue filter (valid values: text(default, inline) on the product page
- Add a shortcode and widget, like in: https://wordpress.org/plugins/wp-opening-hours/
- Allow editing of the time in the back-end
- Stripped-down free version?
*/

if (!defined('ABSPATH')) die('No direct access allowed.');

define('OPENINGTIMES_URL', plugins_url('', __FILE__));
define('OPENINGTIMES_DIR', untrailingslashit(plugin_dir_path(__FILE__)));

$woocommerce_opening_hours = new WooCommerce_Opening_Hours;

class WooCommerce_Opening_Hours {

	public $version = '1.10.6';

	private $current_category_is_open = true;
	private $shipping_methods_and_zones;
	private $shipping_zone_labels;
	private $debug_mode = false; // Activate by defining WOOCOMMERCE_OPENINGHOURS_DEBUG_MODE - it will cause extra logging (but not systemtically - generally, only where it's been needed during the course of development)
	public $wc_compat;
	
	private $current_admin_page_order;

	/**
	 * Constructor
	 */
	public function __construct() {

		$this->debug_mode = (defined('WOOCOMMERCE_OPENINGHOURS_DEBUG_MODE') && WOOCOMMERCE_OPENINGHOURS_DEBUG_MODE);
	
		add_action('plugins_loaded', array($this, 'plugins_loaded'));
// 		add_action('admin_menu', array($this, 'admin_menu'));
		add_action('woocommerce_admin_field_openinghours', array($this, 'woocommerce_admin_field_openinghours'));
		add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
		add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
		add_filter('plugin_action_links', array($this, 'plugin_action_links'), 10, 2);

		add_filter('woocommerce_general_settings', array($this, 'woocommerce_general_settings'));
		add_filter('woocommerce_locate_template', array($this, 'woocommerce_locate_template'), 10, 3);
		# Be careful of using positions which may not always show (e.g. woocommerce_after_checkout_registration_form) - make sure that on your configuration, they *do* always show
		$chooser_position = apply_filters('openinghours_chooser_position', 'woocommerce_after_checkout_billing_form');
		add_action($chooser_position, array($this, 'time_chooser'), 20);

		// WC 3.0+ - but does not actually work the way we thought
// 		add_action('woocommerce_checkout_create_order', array($this, 'woocommerce_checkout_update_order_meta'));
		add_action('woocommerce_checkout_update_order_meta', array($this, 'woocommerce_checkout_update_order_meta'));
		add_action('woocommerce_admin_order_data_after_shipping_address', array($this, 'woocommerce_admin_order_data_after_shipping_address'));

		add_action('woocommerce_check_cart_items', array($this, 'woocommerce_check_cart_items'));
		add_action('woocommerce_checkout_process', array($this, 'woocommerce_check_cart_items'));
		add_action('wp_ajax_openinghours_ajax', array($this, 'ajax'));
		add_action('wp_ajax_nopriv_openinghours_ajax', array($this, 'ajax'));
		
		add_action('wp_ajax_openinghours_edittime', array($this, 'ajax_openinghours_edittime'));

		// Show time in orders page, in shipping column
		add_action( 'manage_shop_order_posts_custom_column', array( $this, 'manage_shop_order_posts_custom_column' ), 11 );

		// Integration with "WooCommerce Print Orders" plugin
		add_action('woocommerce_cloudprint_internaloutput_footer', array($this, 'woocommerce_cloudprint_internaloutput_footer'), 10, 2);
		// Integration with "WooCommerce Delivery Notes" plugin
		add_action('wcdn_loop_content', array($this, 'wcdn_loop_content'), 20);
		// Integration with WooCommerce PDF Packing Slips and Invoices
		add_filter(apply_filters('openinghours_wpo_wcpdf_template_position', 'wpo_wcpdf_footer'), array($this, 'wpo_wcpdf_footer'), 10, 2);
		// Integration with WooCommerce Print Invoices & Packing lists
		add_action('wc_pip_after_body', array($this, 'wc_pip_after_body'), 10, 4);

		add_action('openinghours_print_choice', array($this, 'openinghours_print_choice'));

		// WooCommerce email subjects
		$filter_emails = array('customer_invoice', 'customer_invoice_paid', 'customer_completed_order', 'customer_processing_order', 'new_order', 'customer_note');
		foreach ($filter_emails as $id) {
			add_filter('woocommerce_email_subject_'.$id, array($this, 'woocommerce_email_subject'), 10, 2);
		}

		// Archive page
		add_action('woocommerce_archive_description', array($this, 'woocommerce_archive_description'));

		// Mention chosen time on 'thank you' screen
		add_action('woocommerce_thankyou', array($this, 'woocommerce_thankyou'), 10);

		// Per-category settings
		add_action('product_cat_add_form_fields', array( $this, 'product_cat_add_form_fields' ) );
		add_action('product_cat_edit_form_fields', array( $this, 'product_cat_edit_form_fields' ), 10 );
		// add_action( 'created_term', array( $this, 'edit_term' ), 10 );
		add_action('edit_term', array( $this, 'edit_term' ), 10 );

		// Setting the CSS class on a category list page
		add_action('wc_get_template', array($this, 'wc_get_template'), 10, 3);
		add_filter('product_cat_class', array($this, 'product_cat_class'), 10, 3);

		// Product pages (also called on archives - but can be distinguished later)
		$product_page_position = apply_filters('openinghours_product_page_position', 'woocommerce_before_main_content');
		add_action($product_page_position, array($this, 'on_product_page'), 20);

		add_action('wpo_wcpdf_process_template_order', array($this, 'wpo_wcpdf_process_template_order'), 10, 2);
		
		// WC 2.6 update notice
		add_action('all_admin_notices', array($this, 'admin_notices'));
		
		if (!class_exists('WooCommerce_Compat_0_3')) require_once(dirname(__FILE__).'/vendor/davidanderson684/woocommerce-compat/woocommerce-compat.php');
		$this->wc_compat = new WooCommerce_Compat_0_3();
		
		// Updater
		add_action('plugins_loaded', array($this, 'load_updater'), 0);
		
		add_shortcode('openinghours_conditional', array($this, 'shortcode_openinghours_conditional'));
		add_shortcode('openinghours_time_chosen', array($this, 'shortcode_openinghours_time_chosen'));
		
	}
	
	public function wpo_wcpdf_process_template_order($template_id, $order_id) {
		$this->wcpdf_order_id = $order_id;
	}
	
	/**
	 * WordPress action wc_pip_after_body - part of the template construction procedure for WooCommerce Print Invoices/Packing Lists (SkyVerge)
	 *
	 * @param string $type Document type
	 * @param string $action Current action running on Document
	 * @param WC_PIP_Document $document Document object
	 * @param WC_Order $order Order object
	 */
	public function wc_pip_after_body($type, $action, $document, $order) {

        $time = $this->get_display_time_from_order($order);
        
        if ($time) {
			echo apply_filters('', '<p id="openinghours_printout_timechosen"><strong>'.$this->get_time_chosen_label('printout').":</strong> $time</p>", $time, $type, $action, $document, $order);
		}

	}

	/**
	 * Shortcode openinghours_time_chosen. Only works with WP Overnight's PIPS.
	 *
	 * @param Array $atts - shortcode attributes
	 *
	 * @return String
	 */
	public function shortcode_openinghours_time_chosen($atts) {
	
		if (empty($this->wcpdf_order_id)) return;
		
		if (false == ($order = wc_get_order($this->wcpdf_order_id))) return '';
		
		if ($time = $this->wc_compat->get_meta($order, '_openinghours_time', true)) {
			return $this->get_display_time_from_meta($time);
		}
		
		return '';
	
	}
	
	public function shortcode_openinghours_conditional($atts, $content = null) {
	
		global $current_user;

		// Valid: open | closed
		$atts = shortcode_atts(
			array(
				'only_if' => 'open',
				'shipping_method' => 'default',
				'category_id' => false,
				'instance_id' => false
			),
		$atts);
		
		extract($atts);
		
		if ($this->debug_mode) error_log("OpeningHours: shortcode attributes: ".json_encode($atts));
		
		// Convert GMT date to blog time zone
		$date_now = get_date_from_gmt(gmdate('Y-m-d H:i:s'), 'Y-n-j H:i');
		if (!preg_match('/^(\d+)-(\d+)-(\d+) (\d+):(\d+)$/', $date_now, $matches_now)) return '';

		$shop_open = $this->is_shop_open($matches_now[1], $matches_now[2], $matches_now[3], $matches_now[4], $matches_now[5], $shipping_method, false, $instance_id);
	
		$only_if = ('closed' == $only_if) ? false : true;
	
		if (($only_if && $shop_open) || (!$only_if && !$shop_open)) {
			return do_shortcode($content);
		}
		
		return '';
	
	}
	
	// Not currently used
	public function admin_menu() {
		add_submenu_page(
			'woocommerce',
			__('Opening Times', 'opening_hours'),
			__('Opening Times', 'opening_hours'),
			'manage_woocommerce',
			'wc_opening_hours',
			array($this, 'settings_page')
		);
	}
	
	/**
	 * Load the updater class
	 */
	public function load_updater() {
		if (file_exists(OPENINGTIMES_DIR.'/wpo_update.php')) {
			require(OPENINGTIMES_DIR.'/wpo_update.php');
		} elseif (file_exists(OPENINGTIMES_DIR.'/updater.php')) {
			require(OPENINGTIMES_DIR.'/updater.php');
		}
	}

	/**
	 * Runs upon the WP action admin_notices.
	 */
	public function admin_notices() {
		
		if (function_exists('WC') && version_compare(WC()->version, '2.6', '>=')) {
			$settings = $this->get_options();
			
			if (is_array($settings) && (empty($settings['last_wc_saved_on']) || version_compare($settings['last_wc_saved_on'], '2.6', '<'))) {
			
				$this->show_admin_warning('<span style="font-size:125%;"><strong>'.__('Important upgrade notice', 'openinghours').'</strong></span><br>'.__('You have updated WooCommerce to a new version, which includes significant changes to shipping functionality.', 'openinghours').' <a href="'.esc_attr($this->our_page()).'">'.__('After setting up your shipping zones (if any), please then immediately visit and save your WooCommerce opening hours settings, in order to remain compatible.', 'openinghours').'</a>'.' '.__('This message will disappear when you have done so.', 'openinghours'), 'error');
			
			}
			
		}
	}
	
	/**
	 * Returns a link to the plugin configuration page
	 *
	 * @return String
	 */
	private function our_page() {
		return admin_url('admin.php?page=wc-settings&tab=general');
	}
	
	private function show_admin_warning($message, $class = "updated") {
		echo '<div class="openinghours-admin-message '.$class.'">'."<p>$message</p></div>";
	}
	
	// Used to read the category argument - we don't actually want to over-ride the template
	public function wc_get_template($located, $template_name, $args) {
		if ('content-product_cat.php' != $template_name) return $located;

		$this->current_category_is_open = true;
		if (!is_array($args) || empty($args['category'])) return $located;

		$category = $args['category'];

		$term_id = $category->term_id;
		$hours = $this->get_woocommerce_term_meta($term_id, 'opening_hours_allowed_hours', true);
		if (!is_array($hours)) $hours = array();
		if (false === ($time_to_check_against = $this->get_current_time_in_array())) return $located;

		$this->current_category_is_open = $this->is_shop_open($time_to_check_against[0], $time_to_check_against[1], $time_to_check_against[2], $time_to_check_against[3], $time_to_check_against[4], 'default', $hours);

		return $located;
	}

	public function product_cat_class($classes, $class, $use_category) {
		if ($this->current_category_is_open) {
			$classes[] = 'category-open';
		} else {
			$classes[] = 'category-closed';
		}
		return $classes;
	}

	/**
	 * Runs upon the WP action woocommerce_archive_description
	 */
	public function woocommerce_archive_description() {
		if (!is_shop()) return;

		// Unix time
		$time_now = gmdate('Y-m-d H:i:s');
		// Convert to blog time zone
		$date_now = get_date_from_gmt($time_now, 'Y-n-j H:i');
		if (!preg_match('/^(\d+)-(\d+)-(\d+) (\d+):(\d+)$/', $date_now, $matches_now)) return;

		$shop_open = $this->is_shop_open($matches_now[1], $matches_now[2], $matches_now[3], $matches_now[4], $matches_now[5]);

		do_action('openinghours_archive_page', $shop_open, $this->get_customer_choice());
	}

	public function on_product_page() {
		// Only run on product pages (the default hook runs on archives too)
		if (!is_product()) return;

		global $post;
		if (!is_a($post, 'WP_Post')) return;

		$product = wc_get_product($post->ID);
		if (!is_a($product, 'WC_Product')) return;

		if (false === ($time_to_check_against = $this->get_current_time_in_array())) return;

		$check_choice = $this->get_customer_choice();
		$restricted = false;
		$mingap = 0;

		// Valid values: anyopen|anyclosed|parent|child
		// If choosing 'parent'/'child' and a product is in unrelated categories (i.e. neither is a parent or child of the other), then the effective choice reverts back to 'anyopen'
		$handling_multiple_sets = apply_filters('openinghours_multiple_category_handling', 'anyopen');
		if ('parent' != $handling_multiple_sets && 'child' != $handling_multiple_sets && 'anyclosed' != $handling_multiple_sets) $handling_multiple_sets = 'anyopen';

		list($product_allowed, $mingap, $msg) = $this->check_product_for_category_restrictions($product, $time_to_check_against, 'woocommerce_check_cart_items', $mingap, $handling_multiple_sets, 'product_page');

		if (!$product_allowed && !empty($msg)) {
			wc_add_notice($msg, 'notice');
		}
	}

	private function get_current_time_in_array() {
		// Unix time
		$time_now = gmdate('Y-m-d H:i:s');
		// Convert to blog time zone
		$date_now = get_date_from_gmt($time_now, 'Y-n-j H:i');
		if (!preg_match('/^(\d+)-(\d+)-(\d+) (\d+):(\d+)$/', $date_now, $matches_now)) return false;

		$time_to_check_against = array_slice($matches_now, 1);

		return $time_to_check_against;
	}

	public function product_cat_edit_form_fields( $term ) {

		$allowed_hours = $this->get_woocommerce_term_meta($term->term_id, 'opening_hours_allowed_hours', true );
		$mingap = absint((int)$this->get_woocommerce_term_meta($term->term_id, 'opening_hours_mingap', true ));
		$out_of_hours_msg = $this->get_woocommerce_term_meta($term->term_id, 'opening_hours_allowed_hours_msg', true );
		$product_page_msg = $this->get_woocommerce_term_meta($term->term_id, 'opening_hours_product_page_msg', true );

		if (!is_array($allowed_hours)) $allowed_hours = array();
		// Cause the category page to use them
		$this->use_these_times = $allowed_hours;

		?>
		<tr class="form-field">
			<th scope="row" valign="top"><label><?php _e( 'Allowed times', 'openinghours' ); ?></label></th>
			<td>
				<?php
					echo __('Enter the times allowed for orders containing products in this category.', 'openinghours').' '.__('If there are no times at all, then by default any time is allowed.', 'openinghours').' ('.__('N.B. All of your shop-wide settings will still apply - this setting is for setting an additional restriction to any shop-wide settings.', 'openinghours').')';
				?>

				<?php
					if ($this->date_only) {
						$implied_time = apply_filters('openinghours_forced_time', array(12, 0));
						$show_time = sprintf('%02d:%02d', $implied_time[0], $implied_time[1]);
						echo '<p>'.__('Operating in date-only mode; the assumed time for every order (for the purposes of calculating shop status) will be:', 'opening_hours').' '.$show_time.'</p>';
					}
				?>
				<input name="openinghours_categoryhours" type="hidden" value="1">
				<div id="openinghours-rules">
					<div id="openinghours-rules-default" class="openinghours-rules">
						<div class="openinghours-rules-rulediv"></div>
						<?php
							echo '<p><em><a href="#" data-whichsm="default" data-which_instance="" class="openinghours-addnewdefault">'.__('Add a set of default rules (Monday - Saturday, 9 a.m. - 5 p.m.)...', 'openinghours').'</a></em><br>';
							echo '<em><a href="#" data-whichsm="default" data-which_instance="" class="openinghours-addnew">'.__('Add a new time...', 'openinghours').'</a></em></p>';
						?>
					</div>
				</div>

			</td>
		</tr>

		<tr class="form-field">
			<th scope="row" valign="top">
				<label for="openinghours_mingap"><?php _e('Minimum Order Fulfilment Time', 'openinghours');?></label>
			</th>
			<td class="forminp forminp-number">
				<input name="openinghours-mingap" id="openinghours_mingap" type="number" style="width:64px;" value="<?php echo $mingap; ?>" class="" min="0" step="1"> <span style="margin-top: 2px;"><?php _e('minutes', 'openinghours');?></span>
				<br>
				<em><?php
					echo htmlspecialchars(__('Enter the minimum number of minutes from ordering time that a customer can select. Note that this only applies if the customer is being asked to make a choice (otherwise, "as soon as possible" is implied).', 'openinghours'));
				?></em>
			</td>
		</tr>

		<tr class="form-field">
			<th scope="row" valign="top"><label><?php echo __( 'Out-of-hours message', 'openinghours').'<br>'.__('(cart / check-out)', 'openinghours' ); ?></label></th>
				<td><textarea name="opening_hours_allowed_hours_msg" rows="5" cols="50" class="large-text"><?php echo esc_textarea($out_of_hours_msg);?></textarea>
			<p class="description"><?php echo __('If anything is entered here, then it is displayed instead of the default text, when the customer has the item in his cart at the cart or checkout page, out of hours.', 'openinghours'); ?></p>
			</td>
		</tr>

		<tr class="form-field">
			<th scope="row" valign="top"><label><?php echo __( 'Out-of-hours message', 'openinghours').'<br>'.__('(product page)', 'openinghours' ); ?></label></th>
				<td><textarea name="opening_hours_product_page_msg" rows="5" cols="50" class="large-text"><?php echo esc_textarea($product_page_msg);?></textarea>
			<p class="description"><?php echo __('If anything is entered here, then it is displayed when the customer visits the product page, out of hours (with any minimum order time disregarded, as otherwise it would always display).', 'openinghours'); ?></p>
			</td>
		</tr>

		<?php
		add_action('admin_footer', array($this, 'footer'));
	}

	public function product_cat_add_form_fields() {
		?>
		<div class="form-field">
			<?php
				echo htmlspecialchars(__('You can enter restricted times for the availability of products in this category by editing the category, after you have created it. i.e. Add the product category, and then choose the "edit" option for it.', 'openinghours'));
			?>
		</div>
		<?php
	}

	// Save category times
	public function edit_term( $term_id ) {

		if (!is_admin() || !current_user_can('manage_woocommerce') || empty($_POST['openinghours_categoryhours'])) return;

		$settings = $this->parse_posted_keys('openinghours-');
		# There must be at least one setting - the radio is compulsory
		if (!is_array($settings)) $settings = array();

		$out_of_hours_msg = empty($_POST['opening_hours_allowed_hours_msg']) ? '' : $_POST['opening_hours_allowed_hours_msg'];
		$product_page_msg = empty($_POST['opening_hours_product_page_msg']) ? '' : $_POST['opening_hours_product_page_msg'];

		$mingap = (empty($_POST['openinghours-mingap']) || !is_numeric($_POST['openinghours-mingap'])) ? 0 : absint($_POST['openinghours-mingap']);

		$this->update_woocommerce_term_meta($term_id, 'opening_hours_allowed_hours', $settings);
		$this->update_woocommerce_term_meta($term_id, 'opening_hours_mingap', $mingap);
		$this->update_woocommerce_term_meta($term_id, 'opening_hours_allowed_hours_msg', $out_of_hours_msg);
		$this->update_woocommerce_term_meta($term_id, 'opening_hours_product_page_msg', $product_page_msg);

	}

	public function woocommerce_email_subject($subject, $order) {
		if (is_a($order, 'WC_Order') && false != ($show_time = $this->get_display_time_from_order($order))) {
			return str_ireplace('{chosen_time}', $show_time, $subject);
		}
		return $subject;
	}

	/**
	 * Given a WC order, return the time for display (if any)
	 *
	 * @param WC_Order $order - the order object
	 *
	 * @return String|Boolean - the time to display, or false if not time was stored for the order
	 */
	public function get_display_time_from_order($order) {
		if ($time = $this->wc_compat->get_meta($order, '_openinghours_time', true)) {
			return $this->get_display_time_from_meta($time);
		}
		return false;
	}

	public function get_display_time_from_meta($time) {
		$unix_time = strtotime($time);
		if (false === $unix_time) {
			$unix_time = strtotime(str_replace('/', '-', $time));
		}
		if ($this->date_only) {
			return $unix_time ? date_i18n(get_option('date_format'), $unix_time) : $time;
		} else {
			return $unix_time ? date_i18n(get_option('date_format').' '.get_option('time_format'), $unix_time) : $time;
		}
	}

	public function woocommerce_thankyou($order_id) {
		if (false == ($order = wc_get_order($order_id))) return;
		if ($time = $this->wc_compat->get_meta($order, '_openinghours_time', true)) {
			$time = $this->get_display_time_from_meta($time);
			echo '<p id="openinghours_thankyou_timechosen"><strong>'.$this->get_time_chosen_label('label').":</strong> ".$this->get_display_time_from_meta($time)."</p>";
		}
	}

	/**
	 * Show time in orders page, in shipping column
	 * See woocommerce/includes/admin/class-wc-admin-post-types.php
	 *
	 * @param String $column - which column
	 */
	public function manage_shop_order_posts_custom_column($column) {
		if ('shipping_address' != $column) return;

		global $post, $the_order;
		if (empty($the_order) || $this->wc_compat->get_id($the_order) != $post->ID) {
			$the_order = wc_get_order($post->ID);
		}
		
		if ($time = $this->wc_compat->get_meta($the_order, '_openinghours_time', true)) {
			$time = $this->get_display_time_from_meta($time);
			echo '<span class="order_time">';
			
			echo apply_filters('openinghours_label_timechosen', __('Time chosen', 'openinghours')).":</strong> ".$time;
			
			echo '</span>';

		}

	}

	// $time_parsed and $date_parsed should be in the format as from decode_datepicker_date, decode_datepicker_time
	// The option mingap parameter is for testing against an extra minimum gap setting as well as against that found in the options
	private function time_is_after_minimum_gap($opts, $date_parsed, $time_parsed, $mingap_extra = false, $use_shipping_method = 'default', $use_instance_id = false) {

		$mingap = is_array($opts) ? $this->calculate_mingap_from_options($opts, $use_shipping_method, $use_instance_id) : 0;

		if (false !== $mingap_extra && $mingap_extra > $mingap) $mingap = $mingap_extra;

		$ret = true;
		
		// N.B. The shop owner can enter a negative minimum gap, to set the maximum time in the past (if the customer has lurked long on the checkout) that is allowed
		
		if (0 != $mingap) {
			
			// Unix time
			$time_after_gap = gmdate('Y-m-d H:i:s', time() + 60*$mingap);
			// Convert to site time zone
			$date_after_gap = get_date_from_gmt($time_after_gap, 'Y-n-j H:i');

			if (!preg_match('/^(\d+)-(\d+)-(\d+) (\d+):(\d+)$/', $date_after_gap, $no_orders_before)) return;

			# The chosen date needs to be more minutes in the future than mingap
			
			# The lines test, in sequence: 1) the year 2) month 3) day-of-month 4) hour 5) minute
			if ($no_orders_before[1] > $date_parsed[0] ||
				($no_orders_before[1] == $date_parsed[0] && ($no_orders_before[2] > $date_parsed[1] ||
					($no_orders_before[2] == $date_parsed[1] && ($no_orders_before[3] > $date_parsed[2] ||
						($no_orders_before[3] == $date_parsed[2] && ($no_orders_before[4] > $time_parsed[0] || 
							($no_orders_before[4] == $time_parsed[0] && $no_orders_before[5] > $time_parsed[1])
			))))))) {
				$ret = false;
			}
			
		}

		$ret = apply_filters('openinghours_time_is_after_minimum_gap', $ret, $opts, $date_parsed, $time_parsed, $mingap_extra, $use_shipping_method, $use_instance_id);
		
		if ($this->debug_mode) error_log("time_is_after_minimum_gap(date_parsed=".implode(', ', $date_parsed).", time_parsed=".implode(', ', $time_parsed).", mingap_extra=".serialize($mingap_extra).", use_shipping_method=$use_shipping_method, use_instance_id=$use_instance_id, calculated_mingap=$mingap, return=$ret");
		
		return $ret;
	}

	public function ajax() {

		// N.B. The same nonce is shared, front-end and back-end; so, must always also check the user level, for the operation they are attempting, if it is restricted.

		if (empty($_REQUEST['nonce']) || !wp_verify_nonce($_REQUEST['nonce'], 'openinghours-ajax-nonce') || empty($_REQUEST['subaction'])) die('Security check');

		$woocommerce = WC();

		$original_shipping_method = $shipping_method = !empty($_REQUEST['shipping_method']) ? $_REQUEST['shipping_method'] : 'default';
		$instance_id = false;
		
		// WC 2.6 beta 2 introduced a colon
		if (version_compare($woocommerce->version, '2.6', '>=') && preg_match('/^(.*):(\S+)$/', $shipping_method, $matches)) {
			$shipping_method = $matches[1];
			$instance_id = $matches[2];
		}

		$time_or_date = ($this->date_only) ? __('date', 'openinghours') : __('time', 'openinghours');
		
		switch ($_REQUEST['subaction']):
			case 'export_settings': {
				global $wpdb, $table_prefix;
				$options = $this->get_options();
				include(ABSPATH.WPINC.'/version.php');
				
				if (version_compare($wp_version, '4.4', '>=') && version_compare($woocommerce->version, '2.6', '>=')) {
					$sql = 'SELECT term_id, meta_key, meta_value FROM '.$wpdb->termmeta.' WHERE meta_key LIKE "opening_hours_%"';
				} else {
					$sql = 'SELECT woocommerce_term_id, meta_key, meta_value FROM '.$table_prefix.'woocommerce_termmeta WHERE meta_key LIKE "opening_hours_%"';
				}
				
				$term_meta = $wpdb->get_results($sql);
				if (!is_array($term_meta)) { $term_meta = 'not_found'; }
				
				$meta = array(
					'timezone_string' => get_option('timezone_string'),
				);
				
				echo json_encode(array(
					'options' => $options,
					'meta' => $meta,
					'term_meta' => $term_meta,
					'versions' => array(
						'wc' => $woocommerce->version,
						'opening_hours' => $this->version,
						'wp' => $wp_version
					),
				));
				die;
			}
			break;
			case 'checktime': {
				if ($this->debug_mode) error_log("OpeningHours AJAX: original_shipping_method=$original_shipping_method, shipping_method=$shipping_method, instance_id=".(isset($instance_id) ? $instance_id : 'undefined'));
				if (empty($_REQUEST['text'])) exit;
				$date_parsed = $this->decode_datepicker_date($_REQUEST['text']);
				if (!is_array($date_parsed)) exit;
				$time_parsed = $this->decode_datepicker_time($_REQUEST['text']);
				if (!is_array($time_parsed)) exit;

				$is_open = $this->is_shop_open($date_parsed[0], $date_parsed[1], $date_parsed[2], $time_parsed[0], $time_parsed[1], $shipping_method, false, $instance_id);

				if ($this->debug_mode) error_log("OpeningHours: AJAX checktime: is_shop_open (shipping_method=$shipping_method, instance_id=$instance_id) returned (before other checks): ".serialize($is_open));
				
				$time_to_check_against = array($date_parsed[0], $date_parsed[1], $date_parsed[2], $time_parsed[0], $time_parsed[1]);

				list($check_category_restrictions, $mingap) = $this->check_cart_for_category_restrictions($time_to_check_against, 'woocommerce_check_cart_items', false);

				if ($check_category_restrictions) {
					if ($this->debug_mode) error_log("OpeningHours: AJAX checktime: Product in closed category found");
					$is_open = false;
				}

				$opts = $this->get_options();
				if (!$this->time_is_after_minimum_gap($opts, $date_parsed, $time_parsed, $mingap, $shipping_method, $instance_id)) {
					if ($this->debug_mode) error_log("OpeningHours: AJAX checktime: Time is not after the minimum gap");
					$is_open = false;
				}

				if ($is_open) {
					echo json_encode(array(
						'a' => true,
						'm' => apply_filters('openinghours_frontendtext_timeavailable', '<span id="openinghours_frontendtext_timeavailable"><em>'.sprintf(__('That %s is available', 'openinghours'), $time_or_date).'</em></span>')
					));
				} else {

					$next_opening_time = $this->next_opening_time($date_parsed[0], $date_parsed[1], $date_parsed[2], $time_parsed[0], $time_parsed[1], $shipping_method, $instance_id);

					$time_for_picker = $this->get_text_for_picker($next_opening_time);

					$msg = sprintf(__('That %s is not available - please choose another', 'openinghours'), $time_or_date).'.';

					$ret = array(
						'a' => false,
						'm' => apply_filters('openinghours_frontendtext_timenotavailable', '<span id="openinghours_frontendtext_timenotavailable" class="required"><em>'.$msg.'</em></span>'),
					);

					if (!$check_category_restrictions) {
						$ret = array_merge($ret, array(
							// No longer used
// 							'alt_time' => $time_for_picker,
// 							'alt_time_m' => apply_filters('openinghours_frontendtext_nexttimeis', sprintf(__('The next %s available is:', 'openinghours'), $time_or_date)),
// 							'alt_m' => apply_filters('openinghours_frontendtext_timeavailable', '<span id="openinghours_frontendtext_timeavailable"><em>'.sprintf(__('That %s is available', 'openinghours'), $time_or_date).'</em></span>')
						));
					}

					echo json_encode($ret);
				}
				exit;
			}
		endswitch;

	}

	/**
	 * Runs upon the WP action plugins_loaded
	 */
	public function plugins_loaded() {
		$date_only = (defined('OPENINGHOURS_DATEONLY') && OPENINGHOURS_DATEONLY) ? true : false;
		$this->date_only = apply_filters('openinghours_date_only', $date_only);
		load_plugin_textdomain('openinghours', false, basename(dirname(__FILE__)).'/languages/');
	}

	/**
	 * Get the plugin options array
	 *
	 * @return Array
	 */
	private function get_options() {
		return get_option('openinghours_options', array());
	}
	
	private function get_shipping_instances_to_zones() {
	
		$shipping_instances_to_zones = array();
	
		$shipping_methods_and_zones = $this->get_shipping_methods_and_zones();

		foreach ($shipping_methods_and_zones as $method_id => $method) {
			if (is_array($method['zones'])) {
				foreach ($method['zones'] as $zone_id => $instance_ids) {
					foreach ($instance_ids as $instance_id) {
						$shipping_instances_to_zones[$instance_id] = $zone_id;
					}
				}
			}
		}
		
		return $shipping_instances_to_zones;
	}
	
	private function output_openinghours_shipping_method_labels_js($with_instance_labels = false) {
		
		$shipping_methods_and_zones = $this->get_shipping_methods_and_zones();
	
		$shipping_method_ids = array_merge(array('default'), array_keys($shipping_methods_and_zones));
		
		$shipping_instances_to_zones = $this->get_shipping_instances_to_zones();
		
		$shipping_zone_labels = $this->get_shipping_zone_labels();
		
		// Intentionally global
		echo 'openinghours_shipping_method_labels = { ';
		foreach ($shipping_method_ids as $shipping_method) {
		
			// For mis-behaved/non-standard shipping methods - allows you to specify the ID that will appear on 	the front-end
			$print_shipping_method = apply_filters('openinghours_shipping_method_for_front_end', $shipping_method);
		
			$data = ('default' == $shipping_method) ? null : $shipping_methods_and_zones[$shipping_method];
			if (empty($data['zones'])) {
			
				// Do not includes entries for instance-supporting shipping methods which have no instances
				if (!isset($data['method_object']) || !is_object($data['method_object']) || !isset($data['method_object']->supports) || !is_array($data['method_object']->supports) || !in_array('instance-settings', $data['method_object']->supports)) {
			
					echo "$print_shipping_method: '".esc_js($this->get_shipping_method_title_from_id($shipping_method))." ".__('(Legacy)', 'openinghours')."', ";
					
				}
			} else {
				
				// This next line shouldn't be needed, but can't hurt
				echo "$print_shipping_method: '".esc_js($this->get_shipping_method_title_from_id($shipping_method))." ".__('(All instances)', 'openinghours')."', ";
				foreach ($data['zones'] as $zone_id => $instance_ids) {
					foreach ($instance_ids as $instance_id) {
						$shipping_method_full = $print_shipping_method.':'.$instance_id;
						
						$title = $this->get_shipping_method_title_from_id($shipping_method, $instance_id);
						
						if ($with_instance_labels) $title .= ' ('.$shipping_zone_labels[$zone_id].', '.$data['method_object']->title.')';
						
						echo "'$shipping_method_full': '".esc_js($title)."', ";
					}
				}
			}
		}
		echo " };\n";

	}
	
	// This needs to be able to cope with WC 2.6+ zones and earlier versions that don't have them
	public function checkout_and_cart_footer() {
	
		$shipping_methods_and_zones = $this->get_shipping_methods_and_zones();
	
		$shipping_method_ids = array_merge(array('default'), array_keys($shipping_methods_and_zones));
		
		echo '<script>';

		$this->output_openinghours_shipping_method_labels_js();
	
		echo 'var openinghours_choice_status = { ';

		if (!empty($this->time_chooser_matchesnow)) {

			$customer_choice = $this->get_customer_choice();
			if ('choosewhenclosed' == $customer_choice) {
				list($check_category_restrictions, $mingap) = $this->check_cart_for_category_restrictions($this->time_chooser_matchesnow, 'woocommerce_check_cart_items', false);
			}

			foreach ($shipping_method_ids as $shipping_method) {
			
				// For mis-behaved/non-standard shipping methods - allows you to specify the ID that will appear on 	the front-end
				$print_shipping_method = apply_filters('openinghours_shipping_method_for_front_end', $shipping_method);
			
				$instances = (isset($shipping_methods_and_zones[$shipping_method]) && !empty($shipping_methods_and_zones[$shipping_method]['instances'])) ? $shipping_methods_and_zones[$shipping_method]['instances'] : array();
			
				// We want to still run the loop, if there are no instances
				// We also always make sure that 'false' is in there, in order to send a default to the front-end (believed to be unused, but has no negative implications)
				if (empty($instances) || !in_array(false, $instances)) $instances[] = false;

				foreach ($instances as $instance_id) {
			
					$choice_status = $this->get_choice_status($this->time_chooser_matchesnow, array($shipping_method => array($instance_id)));

					// Check for the case where a category-override means that it really should be shown
					if (false === $choice_status && 'choosewhenclosed' == $customer_choice && $check_category_restrictions) {
						$choice_status = 1;
					} else {
						// Forcing to integer
						$choice_status = $choice_status ? 1 : 0;
					}

					$full_shipping_method = $instance_id ? $print_shipping_method.':'.$instance_id : $print_shipping_method;
					
					echo "'$full_shipping_method': ".$choice_status.", ";
					
				}
			}
		}
		echo " };\n";
		
		// This is somewhat non-optimal, but not a real problem: we pass the information through twice; once with formatting, once raw.
		$date_with_gap_raw = 'var openinghours_date_with_gap_raw = { ';
		
		echo 'var openinghours_date_with_gap = { ';
		
		if (!empty($this->opts_for_page)) {

			foreach ($shipping_method_ids as $shipping_method) {
			
				// For mis-behaved/non-standard shipping methods - allows you to specify the ID that will appear on the front-end
				$print_shipping_method = apply_filters('openinghours_shipping_method_for_front_end', $shipping_method);
			
				$instances = (isset($shipping_methods_and_zones[$shipping_method]) && !empty($shipping_methods_and_zones[$shipping_method]['instances'])) ? $shipping_methods_and_zones[$shipping_method]['instances'] : array();
			
				// We want to still run the loop, if there are no instances
				// We also always make sure that 'false' is in there, in order to send a default to the front-end (believed to be unused, but has no negative implications)
				if (empty($instances) || !in_array(false, $instances)) $instances[] = false;
			
				foreach ($instances as $instance_id) {
			
					$next_opening_time = false;
			
					if (!empty($_POST['openinghours_time'])) {
						$initial_value = $_POST['openinghours_time'];
						
						// Parse date
						$date_parsed = $this->decode_datepicker_date($_POST['openinghours_time']);
						// Validate time
						$time_parsed = $this->decode_datepicker_time($_POST['openinghours_time']);
						
						if (is_array($date_parsed) && is_array($time_parsed)) {
							$next_opening_time = array($date_parsed[0], $date_parsed[1], $date_parsed[2], $time_parsed[0], $time_parsed[1]);
						}
					}
						
					if (false === $next_opening_time) {

						$category_mingap = empty($this->category_mingap_for_page) ? 0 : $this->category_mingap_for_page;
						
						$next_opening_time = $this->next_opening_time_with_gap($shipping_method, $instance_id, $this->opts_for_page, $category_mingap);
						
						$initial_value = $this->get_text_for_picker($next_opening_time);
						
					}
					
					$full_shipping_method = $instance_id ? $print_shipping_method.':'.$instance_id : $print_shipping_method;

					$initial_value = apply_filters('openinghours_frontend_initialvalue_formethod', $initial_value, $shipping_method, $instance_id);
					

					echo "'$full_shipping_method': '".esc_js($initial_value)."', ";
					
					if ('' != $initial_value) {
					
						$date_with_gap_raw .= "'$full_shipping_method': '".esc_js(apply_filters('openinghours_frontend_initialvalue_formethod_raw', json_encode($next_opening_time), $shipping_method, $instance_id, $print_shipping_method))."', ";
					
					}
					
				}
			}
		}
		echo "};\n";
		
		$date_with_gap_raw .= "};\n";
		echo $date_with_gap_raw;
		
		$time_now = time();
		// Convert from current time to blog time zone
		$date_now = get_date_from_gmt(gmdate('Y-m-d H:i:s', $time_now), 'Y-n-j H:i');
		if (!preg_match('/^(\d+)-(\d+)-(\d+) (\d+):(\d+)$/', $date_now, $matches_now)) return true;
		$is_open_contents = '';
		$is_open_after_mingap_contents = '';
		
		$options_to_use = empty($this->opts_for_page) ? $this->get_options() : $this->opts_for_page;
		
		foreach ($shipping_method_ids as $shipping_method) {
		
			// For mis-behaved/non-standard shipping methods - allows you to specify the ID that will appear on the front-end
			$print_shipping_method = apply_filters('openinghours_shipping_method_for_front_end', $shipping_method);
		
			$instances = (isset($shipping_methods_and_zones[$shipping_method]) && !empty($shipping_methods_and_zones[$shipping_method]['instances'])) ? $shipping_methods_and_zones[$shipping_method]['instances'] : array();
				
			// We want to still run the loop, if there are no instances
			// We also always make sure that 'false' is in there, in order to send a default to the front-end (believed to be unused, but has no negative implications)
			if (empty($instances) || !in_array(false, $instances)) $instances[] = false;
		
			foreach ($instances as $instance_id) {
				$shop_open = $this->is_shop_open($matches_now[1], $matches_now[2], $matches_now[3], $matches_now[4], $matches_now[5], $shipping_method, $options_to_use, $instance_id);
				
				$mingap = $this->calculate_mingap_from_options($options_to_use, $shipping_method, $instance_id);
				
				$date_after_mingap = get_date_from_gmt(gmdate('Y-m-d H:i:s', time()), 'Y-n-j H:i');
				preg_match('/^(\d+)-(\d+)-(\d+) (\d+):(\d+)$/', $date_now, $matches_now);
				
				if ($mingap > 0) {
				
					$time_after_mingap = $time_now + 60 * $mingap;
					// Convert to blog time zone and then parse into components
					preg_match('/^(\d+)-(\d+)-(\d+) (\d+):(\d+)$/', get_date_from_gmt(gmdate('Y-m-d H:i:s', $time_after_mingap), 'Y-n-j H:i'), $matches_after_mingap);
					
					$shop_open_after_mingap = $this->is_shop_open($matches_after_mingap[1], $matches_after_mingap[2], $matches_after_mingap[3], $matches_after_mingap[4], $matches_after_mingap[5], $shipping_method, $options_to_use, $instance_id);
					
				} else {
					$shop_open_after_mingap = $shop_open;
				}
				
				$full_shipping_method = $instance_id ? $print_shipping_method.':'.$instance_id : $print_shipping_method;
				
				$is_open_contents .= "'$full_shipping_method': ".(($shop_open) ? '1' : '0').',';
				$is_open_after_mingap_contents .= "'$full_shipping_method': ".($shop_open_after_mingap ? '1' : '0').',';
			}
		}
		echo "var openinghours_is_open_data = { $is_open_contents };\n";
		echo "var openinghours_is_open_after_mingap_data = { $is_open_after_mingap_contents };\n";
		
		echo 'var openinghours_next_open_data = { ';

		foreach ($shipping_method_ids as $shipping_method) {
		
			// For mis-behaved/non-standard shipping methods - allows you to specify the ID that will appear on the front-end
			$print_shipping_method = apply_filters('openinghours_shipping_method_for_front_end', $shipping_method);
		
			$instances = (isset($shipping_methods_and_zones[$shipping_method]) && !empty($shipping_methods_and_zones[$shipping_method]['instances'])) ? $shipping_methods_and_zones[$shipping_method]['instances'] : array();
				
			// We want to still run the loop, if there are no instances
			// We also always make sure that 'false' is in there, in order to send a default to the front-end (believed to be unused, but has no negative implications)
			if (empty($instances) || !in_array(false, $instances)) $instances[] = false;
		
			foreach ($instances as $instance_id) {
		
				$next_opening_time = $this->next_opening_time($matches_now[1], $matches_now[2], $matches_now[3], $matches_now[4], $matches_now[5], $shipping_method, $instance_id);

				$display_date = date_i18n(get_option('date_format').' '.get_option('time_format'), mktime($next_opening_time[3], $next_opening_time[4], 0, $next_opening_time[1], $next_opening_time[2], $next_opening_time[0]));

				$full_shipping_method = $instance_id ? $print_shipping_method.':'.$instance_id : $print_shipping_method;
				echo "'$full_shipping_method': '".esc_js($display_date)."',";
				
			}
		}
		echo " };\n";
		echo '</script>';
	}

	/**
	 * @param Array	  $time_to_check_against - the members (in order) are: year, month, day, hour, minute
	 * @param String  $current_filter
	 * @param Boolean $print_results
	 *
	 * @return Array - items are: (Boolean)$restricted, (Integer)$mingap
	 */
	private function check_cart_for_category_restrictions($time_to_check_against, $current_filter = 'woocommerce_check_cart_items', $print_results = true) {

		$woocommerce = WC();

		$check_choice = $this->get_customer_choice();
		$restricted = false;
		$mingap = false;

		// Check whether any items in the cart are in restricted categories
		$cart = $woocommerce->cart->get_cart();

		// Valid values: anyopen|anyclosed|parent|child
		// If choosing 'parent'/'child' and a product is in unrelated categories (i.e. neither is a parent or child of the other), then the effective choice reverts back to 'anyopen'
		$handling_multiple_sets = apply_filters('openinghours_multiple_category_handling', 'anyopen');
		if ('parent' != $handling_multiple_sets && 'child' != $handling_multiple_sets && 'anyclosed' != $handling_multiple_sets) $handling_multiple_sets = 'anyopen';

		foreach ($cart as $item) {

			list($product_allowed, $mingap, $msg) = $this->check_product_for_category_restrictions($item['data'], $time_to_check_against, $current_filter, $mingap, $handling_multiple_sets);

			if (!$product_allowed) {
				$restricted = true;
				if ($print_results) {
					if (('woocommerce_check_cart_items' == $current_filter && (!defined('WOOCOMMERCE_CHECKOUT') || !WOOCOMMERCE_CHECKOUT)) && (is_cart() || 'noorders' != $check_choice)) {
						echo "<p class=\"woocommerce-info openinghours-notpossible-restrictedcategory\">".$msg."</p>";
					} else {
						$this->add_wc_error($msg);
					}
				}
			}
		}
		
		return array($restricted, $mingap);
	}

	/**
	 * Check whether the product is in a category that is allowed at the indicated time, or not
	 *
	 * @param WC_Order_Item_Product|WC_Product_Variation|WC_Product $product
	 * @param Array $time_to_check_against
	 * @param String $current_filter
	 * @param Boolean|Integer $mingap
	 * @param String $handling_multiple_sets
	 * @param String $which_msg
	 *
	 * @return Array - the values are (boolean)$product_allowed, (integer)$mingap, (string)$any_message
	 */
	private function check_product_for_category_restrictions($product, $time_to_check_against, $current_filter = 'woocommerce_check_cart_items', $mingap = false, $handling_multiple_sets = 'anyopen', $which_msg = 'allowed_hours') {
	
		if ($this->debug_mode) error_log("check_product_for_category_restrictions: mingap=$mingap, handling_multiple_sets=$handling_multiple_sets which_msg=$which_msg, time_to_check_against= ".serialize($time_to_check_against));
		
		static $cache_category_hours = array();
		static $cache_category_mingap = array();
		static $opts = array();
		static $category_restrictions_ignore_empty_sets = false;
		static $check_choice = -1;

		if (-1 === $check_choice) $check_choice = $this->get_customer_choice();

		if (empty($opts)) {
			$opts = $this->get_options();
			$category_restrictions_ignore_empty_sets = !empty($opts['category_restrictions_ignore_empty_sets']);
		}

		if (is_a($product, 'WC_Order_Item_Product')) {
			$product_id = $product->get_variation_id();
			if (empty($product_id)) {
				$product_id = $product->get_product_id();
			}
		} elseif (is_a($product, 'WC_Product_Variation')) {
			// On WC 3.0, get_id() returns the variation ID, whereas the 'id' property (for which direct access is deprecated) is/was the parent. 
			$product_id = 
			is_callable(array($product, 'get_parent_id')) ? $product->get_parent_id() : $product->id;
		} else {
			$product_id = $this->wc_compat->get_id($product);
		}		

		$categories_unindexed = get_the_terms($product_id, 'product_cat');
		if (!is_array($categories_unindexed)) $categories_unindexed = array();
		$categories = array();
		
		// Reindex based on term ID
		foreach ($categories_unindexed as $cat) {
			$categories[$cat->term_id] = $cat;
		}
		
		$product_allowed = true;

		if ('parent' == $handling_multiple_sets || 'child' == $handling_multiple_sets) {

			foreach ($categories as $term_id => $cat) {
				if (!empty($cat->parent)) {
					if ('child' == $handling_multiple_sets) {
						unset($categories[$cat->parent]);
					} else {
						unset($categories[$term_id]);
					}
				}
			}
		}

		$messages = array();
		$parents = array();

		foreach ($categories as $cat) {

			if (!isset($cat->term_id)) continue;
			if (isset($cache_category_hours[$cat->term_id])) {
				$hours = $cache_category_hours[$cat->term_id];
			} else {
				$hours = $this->get_woocommerce_term_meta($cat->term_id, 'opening_hours_allowed_hours', true );
				if (!is_array($hours)) $hours = array();
				$cache_category_hours[$cat->term_id] = $hours;
			}

			if (isset($cache_category_mingap[$cat->term_id])) {
				$item_mingap = $cache_category_mingap[$cat->term_id];
			} else {
				$item_mingap = absint((int)$this->get_woocommerce_term_meta($cat->term_id, 'opening_hours_mingap', true ));
				$cache_category_mingap[$cat->term_id] = $item_mingap;
			}
			
			// The final gap is the largest of the supplied values
			if ($item_mingap > $mingap) $mingap = $item_mingap;

			// If empty sets are being ignored, then short-circuit here, to prevent an 'allow' result.
			if (empty($hours) && $category_restrictions_ignore_empty_sets) continue;

			$product_allowed = $this->is_shop_open($time_to_check_against[0], $time_to_check_against[1], $time_to_check_against[2], $time_to_check_against[3], $time_to_check_against[4], 'default', $hours);
			
			// This check is only relevant if we are checking 'now' on the cart/checkout page; but *not* on the order placement (or AJAX checking) itself. This is because outside of the order-placement context, we are implicitly assuming that the time being checked is 'now'.
			// Doing this check here is a bit structurally ugly, as we have another minimum time check later. But here is where the message is easily available without re-factoring.
			if ($product_allowed && $item_mingap > 0 && !isset($_POST['openinghours_time']) && (empty($_REQUEST['subaction']) || 'checktime' != $_REQUEST['subaction']) &&  'woocommerce_check_cart_items' == $current_filter && (!defined('WOOCOMMERCE_CHECKOUT') || !WOOCOMMERCE_CHECKOUT)) {
				$time_given = sprintf("%d-%02d-%02dT%02d:%02d", $time_to_check_against[0], $time_to_check_against[1], $time_to_check_against[2], $time_to_check_against[3], $time_to_check_against[4]).'+0000';

				$epoch_time_after_gap = strtotime($time_given) + 60 * $item_mingap;

				$time_after_gap = gmdate('Y-m-d H:i:s +0000', $epoch_time_after_gap);
				
				preg_match('/^(\d+)-(\d+)-(\d+) (\d+):(\d+)/', $time_after_gap, $matches_after_mingap);

				if (!$this->is_shop_open($matches_after_mingap[1], $matches_after_mingap[2], $matches_after_mingap[3], $matches_after_mingap[4], $matches_after_mingap[5], 'default', $hours)) {

					if ($this->debug_mode) {
						error_log("check_product_for_category_restrictions(): time was within permitted hours, but is not within the minimum gap");
					}
					
					$product_allowed = false;
				}
			
				// Ensure (anyopen mode) that if the product is allowed, then this is the final verdict on the product (i.e. can't later be reversed by a different category's rule)
			} 
			
			if ($product_allowed) {
				if ('anyopen' == $handling_multiple_sets) break;
			} else {
				$potential_msg = trim($this->get_woocommerce_term_meta($cat->term_id, 'opening_hours_'.$which_msg.'_msg', true ));
				if ($potential_msg || 'product_page' != $which_msg) {
					$msg = $potential_msg;
					$messages[$cat->term_id] = $msg;
					if (!empty($cat->parent)) $parents[$cat->term_id] = $cat->parent;
				}
				// Don't immediately break, as we may need to accumulate category messages from other closed categories
				if ('anyclosed' == $handling_multiple_sets) $final_product_allowed = false;
			}
		}

		if (isset($final_product_allowed)) $product_allowed = $final_product_allowed;

		if (!$product_allowed) {

			$prefer_parent = apply_filters('openinghours_category_message_prefer_parent_to_child', false);

			// Which message to display if a product is restricted by multiple categories has no obvious solution. This filter makes two simple rules possible: prefer the parent category, or prefer the child. Of course, there may be multiple un-related categories.
			if ($prefer_parent) {
				foreach ($parents as $cat_term_id => $cat_parent_term_id) {
					if (isset($messages[$cat_parent_term_id])) $msg = $messages[$cat_parent_term_id];
				}
			} else {
				foreach ($messages as $cat_term_id => $message) {
					if (isset($parents[$cat_term_id])) $msg = $messages[$cat_term_id];
				}
			}

			if (!isset($msg)) $msg = '';
			$msg = apply_filters('openinghours_category_message', $msg, $messages, $parents, $which_msg);
			
			// On the product page, an empty message means the user hasn't chosen to display
			if (empty($msg) && 'product_page' != $which_msg) {
			
				if (('woocommerce_check_cart_items' != $current_filter || (defined('WOOCOMMERCE_CHECKOUT') && WOOCOMMERCE_CHECKOUT)) && isset($_POST['openinghours_time'])) {
					$msg = __('This item is not available at the time you have chosen.', 'openinghours');
				} else {
					$msg = __('This item is not immediately available.', 'openinghours');

					if ('alwayschoose' == $check_choice || 'choosewhenclosed' == $check_choice) {
						$msg .= ' ';
						if ('woocommerce_check_cart_items' == $current_filter && !is_checkout()) {
							$msg .= __('You will need to choose an available time at the checkout.', 'openinghours');
						} else {
							$msg .= __('Please choose an available time.', 'openinghours');
						}
					}
				}
			}

			$msg = $msg ? '<strong>'.htmlspecialchars($product->get_title()).'</strong>: '.$msg : '';

		} else {
			$msg = null;
		}

		if ($this->debug_mode) error_log("check_product_for_category_restrictions result: product_allowed=$product_allowed mingap=$mingap");
		
		return array($product_allowed, $mingap, $msg);

	}

	/**
	 * Multi-purpose function: both for advisories (action woocommerce_check_cart_items) and for errors (woocommerce_checkout_process)
	 */
	public function woocommerce_check_cart_items() {

		// WooCommerce 3.0+ runs both woocommerce_check_cart_items and woocommerce_checkout_process, which results in duplicate notices.
				
		static $we_already_did_this = false;
		if ($we_already_did_this) return;
		$we_already_did_this = true;
	
		$woocommerce = WC();

		// If there are shipping classes settings, then check that we have items in the cart that match the selected classes - otherwise, we have no objection
		$opts = $this->get_options();
		
		if (apply_filters('openinghours_check_cart_items', false, $opts)) return;

		if (isset($opts['shippingoptional']) && !$opts['shippingoptional'] && !$woocommerce->cart->needs_shipping()) return;

		if (!empty($opts['shippingclasses']) && is_array($opts['shippingclasses'])) {
			$relevant_products_found = false;
			$cart = $woocommerce->cart->get_cart();
			foreach ($cart as $item) {
				$_product = $item['data'];
				$shipping_class = $_product->get_shipping_class_id();
				if (!empty($shipping_class) && in_array($shipping_class, $opts['shippingclasses'])) {
					$relevant_products_found = true;
					break;
				}
			}
			if (!$relevant_products_found) return;
		}

		$epoch_time_now = time();
		// Get a string from the current UNIX time
		$time_now = gmdate('Y-m-d H:i:s', $epoch_time_now);
		// Convert to blog time zone
		$date_now = get_date_from_gmt($time_now, 'Y-n-j H:i');
		if (!preg_match('/^(\d+)-(\d+)-(\d+) (\d+):(\d+)$/', $date_now, $matches_now)) {
			error_log("Opening Hours: woocommerce_check_cart_items: could not parse current date/time");
			return;
		}

		$current_filter = current_filter();

		// Get the time to check when checking restricted categories in the cart
		if (('woocommerce_check_cart_items' != $current_filter || (defined('WOOCOMMERCE_CHECKOUT') && WOOCOMMERCE_CHECKOUT)) && isset($_POST['openinghours_time'])) {
			// Parse date
			$date_parsed = $this->decode_datepicker_date($_POST['openinghours_time']);
			// Validate time
			$time_parsed = $this->decode_datepicker_time($_POST['openinghours_time']);
			if (!is_array($date_parsed) || !is_array($time_parsed)) {
				$time_to_check_against = $matches_now;
			} else {
				$time_to_check_against = array($date_parsed[0], $date_parsed[1], $date_parsed[2], $time_parsed[0], $time_parsed[1]);
			}
		} else {
			$time_to_check_against = array_slice($matches_now, 1);
		}
		
		// alwayschoose, choosewhenclosed, noorders
		$check_choice = $this->get_customer_choice();

		// The function prints out notices if appropriate
		list($check_category_restricted, $mingap) = $this->check_cart_for_category_restrictions($time_to_check_against, $current_filter, true);

		// This must come after any other checks that can abort the check, like the 'any products in relevant shipping class' check above.
		if (is_cart() || is_checkout()) add_action('wp_footer', array($this, 'checkout_and_cart_footer'));

		// Get the list of methods and instances to check against
		if (!empty($_POST['shipping_method'])) {
		
			$posted_shipping_method = $_POST['shipping_method'];
			$check_shipping_methods = is_array($posted_shipping_method) ? array_shift($posted_shipping_method) : $posted_shipping_method;
			
			$shipping_instances_to_zones = $this->get_shipping_instances_to_zones();
			
			// WC 2.6 - from beta 2 onwards, the format is <method_id>:<instance_id>. Aug 2018 - have seen evidence that table-rate shipping then adds an extra colon and another ID, so, don't fall over if that is seen.
			if (version_compare($woocommerce->version, '2.6', '>=') && preg_match('/^(.*):(\d+)/', $check_shipping_methods, $imatches)) {
				$check_shipping_methods = $imatches[1];
				$check_instance_id = $imatches[2];
			} else {
				$check_instance_id = false;
			}
			
			$check_shipping_methods = apply_filters('openinghours_shipping_method_from_front_end', $check_shipping_methods);
			
			$use_shipping_method = $check_shipping_methods;
			$use_instance_id = $check_instance_id;

			$check_shipping_methods = array($check_shipping_methods => array($check_instance_id));
		} else {
		
			if ('noorders' == $check_choice) {
				$shipping_methods_and_zones = $this->get_shipping_methods_and_zones();
				$check_shipping_methods = array();
				foreach ($shipping_methods_and_zones as $method_id => $data) {
					$check_shipping_methods[$method_id] = empty($shipping_methods_and_zones['instances']) ? array(false) : $shipping_methods_and_zones['instances'];
				}
			} else {
				$check_shipping_methods = array('default' => array(false));
			}
			$use_shipping_method = 'default';
			$use_instance_id = false;
		}

		
		// Return values: true = choice needed, false = no choice needed, null = no order possible
		$choice_status = $this->get_choice_status($matches_now, $check_shipping_methods);

		if ('noorders' == $check_choice) {

			// No order possible currently (i.e. we are closed)

			$next_opening_time = $this->next_opening_time($matches_now[1], $matches_now[2], $matches_now[3], $matches_now[4], $matches_now[5], $use_shipping_method, $use_instance_id);

			$msg = apply_filters('openinghours_notcurrentlyopenerror', htmlspecialchars(sprintf(__('The %s is not currently able to fulfil this order.', 'openinghours'), apply_filters('openinghours_shopsubjectnoun', __('shop', 'openinghours')))).' ');

			if ('woocommerce_check_cart_items' != $current_filter || (defined('WOOCOMMERCE_CHECKOUT') && WOOCOMMERCE_CHECKOUT)) {
				$shipping_methods = is_object($woocommerce->shipping) ? $woocommerce->shipping->load_shipping_methods() : array();
				$msg .= apply_filters('openinghours_nextavailableat', sprintf(__('This delivery method (%s) is next available at: %s', 'openinghours'), $this->get_shipping_method_title_from_id($use_shipping_method, $use_instance_id), '<span id="openinghours_next_opening_time">'.date_i18n(get_option('date_format').' '.get_option('time_format'), mktime($next_opening_time[3], $next_opening_time[4], 0, $next_opening_time[1], $next_opening_time[2], $next_opening_time[0])).'</span>', $next_opening_time, $use_shipping_method, $use_instance_id));
			} else {
				$msg .= apply_filters('openinghours_nextopenat', __('We are next open at:', 'openinghours').' <span id="openinghours_next_opening_time">'.date_i18n(get_option('date_format').' '.get_option('time_format'), mktime($next_opening_time[3], $next_opening_time[4], 0, $next_opening_time[1], $next_opening_time[2], $next_opening_time[0])).'</span>', $next_opening_time);

			}

			// $choice_status can only be false or null, here
			if ('woocommerce_check_cart_items' == $current_filter && (!defined('WOOCOMMERCE_CHECKOUT') || !WOOCOMMERCE_CHECKOUT)) {
				$display = (null === $choice_status) ? 'block' : 'none';
				echo "<p style=\"display: $display;\" class=\"woocommerce-info openinghours-notpossible-nextavailableat\" id=\"openinghours-notpossible\">".$msg."</p>";
			} else {
				if (null === $choice_status) $this->add_wc_error($msg);
			}

		} else {

			$shop_open = $this->is_shop_open($matches_now[1], $matches_now[2], $matches_now[3], $matches_now[4], $matches_now[5], $use_shipping_method, $opts, $use_instance_id);

			if ('informwhenclosed' == $check_choice) {

				// We always print this; but use CSS to decide whether to display - so that it can be shown/hidden as the shipping method changes
				$next_opening_time = $this->next_opening_time($matches_now[1], $matches_now[2], $matches_now[3], $matches_now[4], $matches_now[5], $use_shipping_method, $use_instance_id);

				$date_show = date_i18n(get_option('date_format').' '.get_option('time_format'), mktime($next_opening_time[3], $next_opening_time[4], 0, $next_opening_time[1], $next_opening_time[2], $next_opening_time[0]));

				$msg = apply_filters('openinghours_notcurrentlyopeninformation',
					htmlspecialchars(sprintf(
						__('The %s is not currently open; your order will be fulfilled once we open.', 'openinghours'), apply_filters('openinghours_shopsubjectnoun', __('shop', 'openinghours'))).
					' '.__('We are next open at:', 'openinghours')).' <span id="openinghours_next_opening_time">'.$date_show.'</span>', $next_opening_time);

				$display = $shop_open ? 'none' : 'block';

				if ('woocommerce_checkout_process' == $current_filter || (defined('WOOCOMMERCE_CHECKOUT') && WOOCOMMERCE_CHECKOUT)) {
					// Nothing to do - the order is allowed to proceed.
				} else {
					echo "<p style=\"display: $display;\" class=\"woocommerce-info openinghours-notpossible-nextopenat\" id=\"openinghours-notpossible\">".$msg."</p>";
				}
				return;

			} elseif ('alwayschoose' == $check_choice) {
			
				// On WC 2.4, the current filter may be woocommerce_check_cart_items, even when 'place order' has been pressed. Hence the extra define check.
				if ('woocommerce_checkout_process' == $current_filter || (defined('WOOCOMMERCE_CHECKOUT') && WOOCOMMERCE_CHECKOUT)) {
					// Carry on to validation
				} else {
				
					// Potentially we want to echo something onto the page
					
					// If we are not currently open, then let them know. N.B. The display status is adjusted in JavaScript; it should give the same result in theory.
					$display = $shop_open ? 'none' : 'block';
					
					$msg = apply_filters('openinghours_frontendtext_currentlyclosedinfo', __('This order cannot be fulfilled immediately; but you will be able to choose a time for later fulfilment.', 'openinghours'));

					$mingap = $this->calculate_mingap_from_options($opts, $use_shipping_method, $use_instance_id);
				
					if ($mingap > 0) {
						$time_after_mingap = $epoch_time_now + 60 * $mingap;
						// Convert to blog time zone and then parse into components
						preg_match('/^(\d+)-(\d+)-(\d+) (\d+):(\d+)$/', get_date_from_gmt(gmdate('Y-m-d H:i:s', $time_after_mingap), 'Y-n-j H:i'), $matches_after_mingap);
						
						$shop_open_after_mingap = $this->is_shop_open($matches_after_mingap[1], $matches_after_mingap[2], $matches_after_mingap[3], $matches_after_mingap[4], $matches_after_mingap[5], $use_shipping_method, $opts, $use_instance_id);
						
						if ($shop_open && !$shop_open_after_mingap) {
							$msg = apply_filters('openinghours_frontendtext_closedbeforefulfiment_info', __('We will close before this order can be fulfilled. You will be able to choose a time for later fulfilment.', 'openinghours'));
						}
						
					}
					
					echo "<p style=\"display: $display;\" class=\"woocommerce-info openinghours-notpossible-notimmediate\" id=\"openinghours-notpossible\">$msg</p>\n";
					return;
				}
				// Is there a chosen time, and is it valid?
			} elseif ('choosewhenclosed' == $check_choice) {

				// This used to be here
				// if ($choice_status !== true && empty($_POST['openinghours_time'])) return;

				// There's only something to print if we're on the cart
				if ('woocommerce_check_cart_items' == $current_filter && (!defined('WOOCOMMERCE_CHECKOUT') || !WOOCOMMERCE_CHECKOUT)) {
					$next_opening_time = $this->next_opening_time($matches_now[1], $matches_now[2], $matches_now[3], $matches_now[4], $matches_now[5], $use_shipping_method, $use_instance_id);

					$msg = '<span class="openinghours_shippingmethod"></span>'.apply_filters('openinghours_frontendtext_notcurrentlyopeninfo',
						htmlspecialchars(sprintf(__('The %s cannot immediately fulfil this order; you will need to choose an available fulfilment time.', 'openinghours'), apply_filters('openinghours_shopsubjectnoun', __('shop', 'openinghours')))).
						' '.htmlspecialchars(__('The next possible time is:', 'openinghours'))).' <span id="openinghours_next_opening_time">'.date_i18n(get_option('date_format').' '.get_option('time_format'), mktime($next_opening_time[3], $next_opening_time[4], 0, $next_opening_time[1], $next_opening_time[2], $next_opening_time[0])).'</span>';
					$display = ($choice_status !== true && empty($_POST['openinghours_time'])) ? 'none' : 'block';
					echo "<p style=\"display:$display;\" class=\"woocommerce-info openinghours-notpossible-notimmediate-willchoose\" id=\"openinghours-notpossible\">".$msg."</p>";
					return;
				} else {
					// Is a choice needed now? Also, perhaps they provided one anyway (e.g. reached the checkout whilst still closed, and chose a later time)
					// error_log("category_restricted=$check_category_restricted, choice_status=$choice_status, chosen_time=".$_POST['openinghours_time']);
					if ($choice_status !== true && empty($_POST['openinghours_time'])) return;
					// Validate choice
				}
			}

			// If we reached here without returning, then there is meant to be valid data in $_POST['openinghours_time']
			if (empty($_POST['openinghours_time'])) {
				$this->add_wc_error(apply_filters('openinghours_frontendtext_timeneeded', __('Please choose a time.', 'openinghours')));
			} else {
				# Parse date
				$date_parsed = $this->decode_datepicker_date($_POST['openinghours_time']);
				# Validate time
				$time_parsed = $this->decode_datepicker_time($_POST['openinghours_time']);

				if (!is_array($date_parsed) || !is_array($time_parsed)) {
					$this->add_wc_error(apply_filters('openinghours_frontendtext_timeneeded', __('Please choose a time.', 'openinghours')));
					return;
				}

				$is_open = $this->is_shop_open($date_parsed[0], $date_parsed[1], $date_parsed[2], $time_parsed[0], $time_parsed[1], $use_shipping_method, $opts, $use_instance_id);
				
				if (!$this->time_is_after_minimum_gap($opts, $date_parsed, $time_parsed, $mingap, $use_shipping_method, $use_instance_id)) {
					$is_open = false;
				}

				if (!$is_open) {
					$shipping_method_title = $this->get_shipping_method_title_from_id($use_shipping_method, $use_instance_id);
					if ('default' == $use_shipping_method || empty($shipping_method_title)) {
						$this->add_wc_error(apply_filters('openinghours_frontendtext_timeinvalid', __('The time you have chosen is not available; please choose again.', 'openinghours')));
					} else {
						$this->add_wc_error(apply_filters('openinghours_frontendtext_timeinvalid_withmethod', sprintf(__('The time you have chosen is not available for the chosen delivery method (%s); please choose again.', 'openinghours'), $shipping_method_title), $use_shipping_method, $shipping_method_title));
					}
				}
			}
		}

	}

	// Default levels available: error, success, notice
	public function add_wc_error($msg, $level = 'error') {
		// Since WC 2.1
		wc_add_notice($msg, $level);
	}

	public function woocommerce_locate_template($template, $name, $path) {

		if ('emails/email-addresses.php' !== $name && 'emails/plain/email-addresses.php' != $name) return $template;

		if (!$path) $path = WC()->template_url;

		$theme_template = locate_template(array($path.$name, $name));
		if ($theme_template) return $theme_template;
		return OPENINGTIMES_DIR.'/wootemplates/'.$name;
	}

	/**
	 * @param $order WC_Order
	 *
	 * @return String - the HTML
	 */
	private function get_admin_order_page_fields($order) {
	
		if ($time = $this->wc_compat->get_meta($order, '_openinghours_time', true)) {
		
			$display_time = $this->get_display_time_from_meta($time);
				
			$time_format = $this->wc_compat->get_meta($order, '_openinghours_time_format', true);
			
			$ret = '<p id="openinghours_admin_timechosen"><strong>'.$this->get_time_chosen_label('label').":</strong> <span id=\"openinghours_admin_timechosen_value\" data-timeformat=\"".esc_attr($time_format)."\" data-time=\"".esc_attr($time)."\">".htmlspecialchars($display_time)."</span> <span id=\"openinghours-edit\" class=\"dashicons dashicons-edit\"></span>";
			
			$ret .= '<br> <div id="openinghours_admin_timechosen_edit_container" style="display:none;"><input id="openinghours_admin_timechosen_edit" size='.(2+strlen($time_format)).' placeholder="'.esc_attr($time_format).'" value="'.esc_attr($time).'"> <button id="openinghours_admin_timechosen_edit_go">'.__('Update', 'openinghours').'</button></div>';
			
			$ret .= '</p>';
		
			return $ret;
		}
		
		return '';
	}
	
	// Show chosen time on the admin page
	public function woocommerce_admin_order_data_after_shipping_address($order) {
		
			echo '<div id="openinghours_admin_timechosen_container">'.$this->get_admin_order_page_fields($order).'</div>';
			
			static $added = false;
			if (!$added) {
				$this->current_admin_page_order = $order;
				add_action('admin_footer', array($this, 'edit_footer'));
			}
			$added = true;
	}

	public function ajax_openinghours_edittime() {
		// Check both permission level and proof of intent
		if (!is_user_logged_in() || !current_user_can('edit_shop_orders') || !isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'openinghours_edittime') || empty($_POST['order_id']) || !is_numeric($_POST['order_id']) || false == ($order = wc_get_order($_POST['order_id'])) || !is_a($order, 'WC_Order') || !isset($_POST['time_chosen']) || !is_string($_POST['time_chosen'])) die('Security check');
		
		$time_format = $this->wc_compat->get_meta($order, '_openinghours_time_format', true);
		
		$date_and_time_chosen = $_POST['time_chosen'];
		
		// Parse date
		$date_parsed = $this->decode_datepicker_date($date_and_time_chosen);
		// Validate time
		$time_parsed = $this->decode_datepicker_time($date_and_time_chosen);
		
		$d = DateTime::createFromFormat($time_format, $date_and_time_chosen);
		// The Y ( 4 digits year ) returns TRUE for any integer with any number of digits so changing the comparison from == to === fixes the issue.
		$parsed = ($d && $d->format($format) === $date);
		
		$result = array(
			'expected_format' => $time_format,
			'requested_value' => $_POST['time_chosen'],
		);
		
		if ($parsed) {
			$result['result'] = 'error';
			$result['message'] = __('The time you entered did not match the expected format.', 'openinghours').' '.sprintf(__('Please enter in the exact format: %s', 'openinghours'), $time_format);
		} else {
			$this->store_time($order, $date_and_time_chosen);
			$result['result'] = 'ok';
			$result['message'] = __('The time chosen has been updated.', 'openinghours');
			$order = wc_get_order($_POST['order_id']);
			$result['fields'] = $this->get_admin_order_page_fields($order);
		}
		
		echo json_encode($result);
		
		die();
	}
	
	/**
	 * Runs upon the WP action admin_footer
	 */
	public function edit_footer() {
		?>
		<script>
			jQuery(document).ready(function($) {
				var ajax_url = '<?php echo esc_js(admin_url('admin-ajax.php', 'relative'));?>';
				$('#openinghours_admin_timechosen_container').on('click', '#openinghours-edit', function() {
					$('#openinghours_admin_timechosen_edit_container').slideDown();
					$('#openinghours_admin_timechosen_edit').focus();
				});
				$('#openinghours_admin_timechosen_container').on('click', '#openinghours_admin_timechosen_edit_go', function(e) {
					e.preventDefault();
					$.post(ajax_url, {
						action: 'openinghours_edittime',
						_wpnonce: '<?php echo wp_create_nonce("openinghours_edittime");?>',
						order_id: <?php echo $this->current_admin_page_order->get_id(); ?>,
						time_chosen: $('#openinghours_admin_timechosen_edit').val(),
					}, function(response) {
						var resp = JSON.parse(response);
						if (resp.hasOwnProperty('result')) {
							if ('ok' == resp.result) {
								alert(resp.message);
								$('#openinghours_admin_timechosen_container').html(resp.fields);
							} else if ('error' == resp.result) {
								alert(resp.message);
							}
						} else {
							console.log(response);
							alert('Error in response (more information available in the JavaScript console)');
						}
					});
				});
			});
		</script>
		<?php
	}
	
	private function get_time_chosen_label($filter = 'label') {
		if ($this->date_only) {
			return apply_filters('openinghours_'.$filter.'_datechosen', __('Date chosen', 'openinghours'));
		} else {
			return apply_filters('openinghours_'.$filter.'_timechosen', __('Time chosen', 'openinghours'));
		}
	}

	public function wcdn_loop_content($order) {
		if ($time = $this->wc_compat->get_meta($order, '_openinghours_time', true)) {
			$time = $this->get_display_time_from_meta($time);
			echo '<p id="openinghours_printout_timechosen"><strong>'.$this->get_time_chosen_label('printout').":</strong> $time</p>";
		}
	}

	public function woocommerce_cloudprint_internaloutput_footer($order, $format = 'text/html') {
		if ($time = $this->wc_compat->get_meta($order, '_openinghours_time', true)) {
			$time = $this->get_display_time_from_meta($time);
			if ('text/plain' == $format) {
				echo $this->get_time_chosen_label('printout').": $time";
			} else {
				echo '<p id="openinghours_printout_timechosen"><strong>'.$this->get_time_chosen_label('printout').":</strong> $time</p>";
			}
		}
	}

	public function wpo_wcpdf_footer($footer, $document = null) {

		// Since version 2.0 of the WooCommerce PDF Invoices & Packing Slips plugin
		if (!function_exists('WPO_WCPDF')) {
			global $wpo_wcpdf;
			if (!is_a($wpo_wcpdf, 'WooCommerce_PDF_Invoices') || empty($wpo_wcpdf->export)) return $footer;
			$order = $wpo_wcpdf->export->order;
		} else {
			if (!is_object($document) || empty($document->order)) return $footer;
			$order = $document->order;
		}
		if ($time = $this->wc_compat->get_meta($order, '_openinghours_time', true)) {
			$order_time = $this->get_display_time_from_meta($time);
			$footer .= apply_filters('openinghours_wpo_wcpdf_footer', '<p id="openinghours_printout_timechosen"><strong>'.$this->get_time_chosen_label('printout').":</strong> $order_time</p>", $order_time, $time);
		}
		return $footer;
	}

	// Intended for calling as a WP action in user code
	public function openinghours_print_choice($order_id = false) {
		// Default to the PDF invoices + packing slips current order
		
		if (!$order_id && !empty($this->wcpdf_order_id)) $order_id = $this->wcpdf_order_id;
		
		if (!$order_id) {
		
			if (!function_exists('WPO_WCPDF')) {
				global $wpo_wcpdf;
				if (!is_a($wpo_wcpdf, 'WooCommerce_PDF_Invoices') || empty($wpo_wcpdf->export)) return;
				$order = $wpo_wcpdf->export->order;
			}
			
		} else {
			$order = wc_get_order($order_id);
		}
		if ($time = $this->wc_compat->get_meta($order, '_openinghours_time', true)) {
			echo $this->get_display_time_from_meta($time);
		}
	}

	/**
	 * Store a time for an order
	 *
	 * @param WC_Order $order
	 * @param String   $store_time
	 */
	private function store_time($order, $store_time) {
		$store_time = apply_filters('openinghours_store_time', $store_time, $order);
		$this->wc_compat->update_meta_data($order, '_openinghours_time', $store_time);
	}
	
	/**
	 * Runs upon the WP action woocommerce_checkout_update_order_meta
	 *
	 * @param Integer|WC_Order $order_id_or_order
	 */
	public function woocommerce_checkout_update_order_meta($order_id_or_order) {
	
// 		if (!is_a($order_id_or_order, 'WC_Order') && version_compare(WC_VERSION, '2.7', '>=')) return;
		
		$order = is_a($order_id_or_order, 'WC_Order') ? $order_id_or_order : wc_get_order($order_id_or_order);

		if (!empty($_POST['openinghours_time'])) {
		
			$this->store_time($order, $_POST['openinghours_time']);
		
			$store_time_format = apply_filters('openinghours_store_time_format', $this->get_datepicker_format_from_wp().' '.$this->get_timepicker_format_from_wp(), $order);
			
			$this->wc_compat->update_meta_data($order, '_openinghours_time_format', $store_time_format);
		}
	}

	/**
	 * Get the value of the customerchoices setting. Will return a default value if no valid value was saved (i.e. will always return something valid).
	 *
	 * @return String
	 */
	public function get_customer_choice() {
		$settings = $this->get_options();
		
		$check_choice = (is_array($settings) && !empty($settings['customerchoices'])) ? $settings['customerchoices'] : 'choosewhenclosed';
		
		// Check that it's on the list of supported values.
		if ('alwayschoose' != $check_choice && 'noorders' != $check_choice && 'informwhenclosed' != $check_choice) $check_choice = 'choosewhenclosed';
		
		return $check_choice;
	}

	// Return values: true = choice needed, false = no choice needed, null = no order possible
	// Input: array: year, month, day, hour, minute of time to check for
	// The question being asked is as to whether the shop is open for *any* of the specified shipping methods
	// $check_shipping_methods is an array, where keys are shipping methods, and values are an array of instance IDs (or an array with false).
	public function get_choice_status($matches_now, $shipping_methods = array('default' => array(false))) {
	
		$check_choice = $this->get_customer_choice();

		// alwayschoose, choosewhenclosed, noorders, informwhenclosed
		if ('alwayschoose' == $check_choice) return true;
		if ('informwhenclosed' == $check_choice) return false;

		// Handle legacy format
		if (is_string($shipping_methods)) $shipping_methods = array($shipping_methods => array(false));
		
		$shipping_instances_to_zones = $this->get_shipping_instances_to_zones();

		foreach ($shipping_methods as $shipping_method => $instances) {
			
			foreach ($instances as $instance_id) {
				if ($instance_id) {
					if (isset($shipping_instances_to_zones[$instance_id]) && $this->is_shop_open($matches_now[1], $matches_now[2], $matches_now[3], $matches_now[4], $matches_now[5], $shipping_method, false, $instance_id)) return false;
				} else {
					if ($this->is_shop_open($matches_now[1], $matches_now[2], $matches_now[3], $matches_now[4], $matches_now[5], $shipping_method)) return false;
				}
			}
		
		}

		return ('choosewhenclosed' == $check_choice) ? true : null;
	}

	// Print the actual widget on the checkout page, if necessary
	public function time_chooser() {

		$woocommerce = WC();

		// If nothing in the cart needs shipping, and we aren configured to require shippable items, then bail completely
		$opts = $this->get_options();
		if (isset($opts['shippingoptional']) && !$opts['shippingoptional'] && !$woocommerce->cart->needs_shipping()) return;

		$category_mingap = 0;

		// Check if there are relevant items in the cart
		if (!empty($opts['shippingclasses']) && is_array($opts['shippingclasses'])) {
			$relevant_products_found = false;
			$cart = $woocommerce->cart->get_cart();
			foreach ($cart as $item) {
				$_product = $item['data'];
				$shipping_class = $_product->get_shipping_class_id();
				if (!empty($shipping_class) && in_array($shipping_class, $opts['shippingclasses'])) {
					$relevant_products_found = true;
					break;
				}
			}
			if (!$relevant_products_found) return;
		}

		$check_choice = $this->get_customer_choice();
		// No time picker ever displays, in this situation
		if ('informwhenclosed' == $check_choice || 'noorders' == $check_choice) return;

		// To reach here, $check_choice must either be alwayschoose or choosewhenclosed

		// Unix time
		$time_now = gmdate('Y-m-d H:i:s');
		// Convert to blog time zone
		$date_now = get_date_from_gmt($time_now, 'Y-n-j H:i');
		if (!preg_match('/^(\d+)-(\d+)-(\d+) (\d+):(\d+)$/', $date_now, $matches_now)) return;

		$this->time_chooser_matchesnow = $matches_now;

		$choice_status = $this->get_choice_status($matches_now);

		// null means 'no order possible'; we deal with that elsewhere
		// false means 'no choice needed - either shop is currently open, or the user is not allowed a choice'

		list($check_category_restrictions, $mingap) = $this->check_cart_for_category_restrictions($matches_now, 'woocommerce_check_cart_items', false);

// 		if (false === $choice_status || null === $choice_status) return;

		// 28-May-2015 - the selector should be shown if there's a choice to be made out-of-hours and category restrictions are in place.
// 		$display = (false === $choice_status || null === $choice_status) ? 'none' : 'block';
		$display = ((false === $choice_status && ('choosewhenclosed' != $check_choice || !$check_category_restrictions )) || null === $choice_status) ? 'none' : 'block';

		// To reach here, this must either be alwayschoose or choosewhenclosed

		// We *always* need to print the HTML, in case a different shipping method does not allow now - i.e. the choice status differs for different shipping methods

		if (!empty($_POST['openinghours_time'])) {
			$initial_value = $_POST['openinghours_time'];
		} else {

			// The initial value should not matter too much, as the page refreshes itself via AJAX once a shipping method is chosen
			if (!empty($_POST['shipping_method'])) {
				$posted_shipping_method = $_POST['shipping_method'];
				$use_shipping_method = is_array($posted_shipping_method) ? array_shift($posted_shipping_method) : $posted_shipping_method;
				if (preg_match('/^(\S+)__I-(\d+)$/', $use_shipping_method, $imatches)) {
					$use_shipping_method = $imatches[1];
					$use_instance_id = $imatches[2];
				} else {
					$use_instance_id = false;
				}
			} else {
				$use_shipping_method = 'default';
				$use_instance_id = false;
			}
			
			$next_opening_time = $this->next_opening_time_with_gap($use_shipping_method, $use_instance_id, $opts, $category_mingap);
			
			$this->opts_for_page = $opts;
			$this->category_mingap_for_page = $category_mingap;

			$initial_value = $this->get_text_for_picker($next_opening_time);
		}

		$time_or_date = $this->date_only ? __('date', 'openinghours') : __('time', 'openinghours');
		
		$text = apply_filters('openinghours_frontendtext_choicelabel',
			('alwayschoose' == $check_choice) ? sprintf(__('Choose your order fulfilment %s', 'openinghours'), $time_or_date) : sprintf(__('This order cannot be immediately fulfilled. Please choose an available %s.', 'openinghours'), $time_or_date), $check_choice, $this->date_only
		);

		$field_type = apply_filters('openinghours_checkout_field_type', 'text');
		$initial_value = apply_filters('openinghours_frontend_initialvalue', $initial_value);

		if ('text' == $field_type) {
			// The class is for the benefit of WC 2.1, which does not support style
			woocommerce_form_field('openinghours_time',
				// If in your case a time is always required, use this filter to add a 'required' => true attribute.
				apply_filters('openinghours_openinghours_time_form_field', array(
					'clear' => true,
					'label' => $text,
					'placeholder' => apply_filters('openinghours_frontendtext_choiceplaceholder', sprintf(__('Enter a %s to deliver after', 'openinghours'), $time_or_date)),
					'class' => array('form-row-wide', "openinghours-initial-display-$display"),
					'style' => "display:$display;"
				)),
				$initial_value
			);
		} elseif ('inline' == $field_type) {
			// Make hidden - ? Can leave that up to user CSS
			?>
			<p id="openinghours_time_field" class="form-row form-row-wide openinghours-initial-display-<?php echo $display;?>">
				<label for="openinghours_time" class=""><?php echo $text;?></label>
				<input type="text" name="openinghours_time" id="openinghours_time_result">
				<span id="openinghours_time"></span>
			</p>
			<?php
			echo "";
		} else {
			do_action("openinghours_checkout_render_".$field_type, $display, $text, $initial_value);
		}

		add_action('wp_footer', array($this, 'footer'));

	}
	
	/**
	 * @param String $shipping_method - the shipping method
	 * @param Integer $instance_id - the instance ID
	 * @param Array|Boolean $opts - options. If not supplied, then defaults will be fetched
	 * @param Integer $min_gap_at_least - use this as a minimum gap if the calculated value is less
	 *
	 * @return Array|null - the result (same as from next_opening_time()), or null for an error
	 */
	private function next_opening_time_with_gap($shipping_method = 'default', $instance_id = false, $opts = false, $min_gap_at_least = 0) {
	
		if ($this->debug_mode) {
			// error_log("next_opening_time_with_gap(shipping_method=$shipping_method, instance_id=$instance_id, min_gap_at_least=$min_gap_at_least, opts=(opts)");
		}
	
		if (false === $opts) $opts = $this->get_options();
	
		$min_gap = max($this->calculate_mingap_from_options($opts, $shipping_method, $instance_id), $min_gap_at_least);
		
		// We add on a 5 minute cushion to allow them to spend 5 minutes checking out; 
		$min_gap += 5;

		// Unix time
		$time_with_gap = gmdate('Y-m-d H:i:s', time() + 60 * $min_gap);

		// Convert to site time zone
		$date_with_gap = get_date_from_gmt($time_with_gap, 'Y-n-j H:i');
		if (!preg_match('/^(\d+)-(\d+)-(\d+) (\d+):(\d+)$/', $date_with_gap, $matches_now)) return;

		$next_opening_time = $this->next_opening_time($matches_now[1], $matches_now[2], $matches_now[3], $matches_now[4], $matches_now[5], $shipping_method, $instance_id);
		
		return apply_filters('openinghours_next_opening_time_with_gap', $next_opening_time, $shipping_method, $instance_id, $opts, $min_gap_at_least);
	
	}

	// The $shipping_method parameter is not currently used: instead, we get the earliest from any shipping method
	private function calculate_mingap_from_options($opts, $shipping_method = 'default', $instance_id = false) {
	
		$full_shipping_method = ('default' != $shipping_method && $instance_id) ? $shipping_method.'__I-'.$instance_id : $shipping_method;
	
		if (empty($opts['mingap'])) {
			$mingap = 0;
		} elseif (!is_array($opts['mingap'])) {
			$mingap = (int)$opts['mingap'];
		} elseif (isset($opts['mingap'][$full_shipping_method]) && empty($opts['mingap_usedefault'][$full_shipping_method])) {
			$mingap = (int)$opts['mingap'][$full_shipping_method];
		} elseif (isset($opts['mingap']['default'])) {
			$mingap = (int)$opts['mingap']['default'];
		} else {
			$mingap = 0;
		}
		
		$hour_now = get_date_from_gmt(gmdate('Y-m-d H:i:s'), 'H');
		$minute_now = get_date_from_gmt(gmdate('Y-m-d H:i:s'), 'i');

		if (isset($opts['mingap_further']) && !is_array($opts['mingap_further'])) {
			$mingap_further = $opts['mingap_further'];
		} elseif (isset($opts['mingap_further'][$full_shipping_method]) && empty($opts['mingap_usedefault'][$full_shipping_method])) {
			$mingap_further = $opts['mingap_further'][$full_shipping_method];
		} elseif (isset($opts['mingap_further']['default'])) {
			$mingap_further = $opts['mingap_further']['default'];
		} else {
			$mingap_further = false;
		}
		
		if ($mingap_further) {
		
			if (isset($opts['mingap_ifpast_minute']) && !is_array($opts['mingap_ifpast_minute'])) {
				$mingap_ifpast_minute = $opts['mingap_ifpast_minute'];
			} elseif (isset($opts['mingap_ifpast_minute'][$full_shipping_method]) && empty($opts['mingap_usedefault'][$full_shipping_method])) {
				$mingap_ifpast_minute = $opts['mingap_ifpast_minute'][$full_shipping_method];
			} elseif (isset($opts['mingap_ifpast_minute']['default'])) {
				$mingap_ifpast_minute = $opts['mingap_ifpast_minute']['default'];
			} else {
				$mingap_ifpast_minute = false;
			}
			
			if (isset($opts['mingap_ifpast_hour']) && !is_array($opts['mingap_ifpast_hour'])) {
				$mingap_ifpast_hour = $opts['mingap_ifpast_hour'];
			} elseif (isset($opts['mingap_ifpast_hour'][$full_shipping_method]) && empty($opts['mingap_usedefault'][$full_shipping_method])) {
				$mingap_ifpast_hour = $opts['mingap_ifpast_hour'][$full_shipping_method];
			} elseif (isset($opts['mingap_ifpast_hour']['default'])) {
				$mingap_ifpast_hour = $opts['mingap_ifpast_hour']['default'];
			} else {
				$mingap_ifpast_hour = false;
			}
		
			if (false !== $mingap_ifpast_minute && false !== $mingap_ifpast_hour) {

				$mingap_ifpast_hour = absint($mingap_ifpast_hour);
				$mingap_ifpast_minute = absint($mingap_ifpast_minute);
				if ($hour_now > $mingap_ifpast_hour || ($hour_now == $mingap_ifpast_hour && $minute_now > $mingap_ifpast_minute)) {
					$mingap += absint($mingap_further);
				}
			}

		}

		if ($this->debug_mode) {
			error_log("OpeningHours: calculate_mingap_from_options(shipping_method=$shipping_method, instance_id=$instance_id, full_shipping_method=$full_shipping_method): mingap=$mingap, mingap_further=$mingap_further, mingap_ifpast_minute=".(isset($mingap_ifpast_minute) ? $mingap_ifpast_minute : 'undefined').", mingap_ifpast_hour=".(isset($mingap_ifpast_hour) ? $mingap_ifpast_hour : 'undefined'));
		}

		// Return if nothing more to do
		if (!$mingap || empty($opts['addminto']) || 'opening' != $opts['addminto']) return $mingap;

		// We need to count it from today's opening time, instead of from 'now'.
		if (!empty($opts['addminto']) && 'opening' ==$opts['addminto']) {

			$today_weekday = get_date_from_gmt(gmdate('Y-m-d H:i:s'), 'w');

			$today_earliest_opening_hour = false;
			$today_earliest_opening_minute = false;

// 			$filtered_settings = $this->filter_opening_hours($opts, $shipping_method);
			$filtered_settings = $opts;
			foreach ($filtered_settings as $key => $val) {

				$weekday = (int)$val;
				if ($today_weekday != $weekday) continue;

				if (preg_match('/^openinghours-(shipmethod-(\S+)-)?(\d+)-day$/', $key, $matches_now)) {
					// Filter for relevant settings
					$which_shipping_method = (empty($matches_now[1])) ? 'default' : $matches_now[2];

					$ind = ('default' == $which_shipping_method) ? $matches_now[3] : 'shipmethod-'.$which_shipping_method.'-'.$matches_now[3];

					if (isset($filtered_settings["openinghours-".$ind."-hour"]) && isset($filtered_settings["openinghours-".$ind."-minute"])) {
						$today_opening_hour = $filtered_settings["openinghours-".$ind."-hour"];
						$today_opening_minute = $filtered_settings["openinghours-".$ind."-minute"];
						if (false === $today_earliest_opening_hour || $today_opening_hour < $today_earliest_opening_hour || ($today_opening_hour == $today_earliest_opening_hour && $today_opening_minute < $today_earliest_opening_minute )) {
							$today_earliest_opening_hour = $today_opening_hour;
							$today_earliest_opening_minute = $today_opening_minute;
						}
					}
				}
			}

			// Anything to do?
			if (false === $today_earliest_opening_minute) return apply_filters('openinghours_mingap_from_options', $mingap, $opts, $shipping_method, $instance_id);;

			if ($hour_now > $today_earliest_opening_hour || ($hour_now == $today_earliest_opening_hour && $minute_now > $today_earliest_opening_minute)) {
				return apply_filters('openinghours_mingap_from_options', $mingap, $opts, $shipping_method, $instance_id);;
			} else {
				$how_many_minutes = 60 * ($today_earliest_opening_hour - $hour_now) + $today_earliest_opening_minute - $minute_now;
				return apply_filters('openinghours_mingap_from_options', $mingap + $how_many_minutes, $opts, $shipping_method, $instance_id);
			}

		}

		return apply_filters('openinghours_mingap_from_options', $mingap, $opts, $shipping_method, $instance_id);
	}

	// $use_time is an array, in the format returned by next_opening_time()
	private function get_text_for_picker($use_time) {
		if ($this->date_only) {
			return $this->get_datepicker_format_from_wp($use_time);
		} else {
			return $this->get_datepicker_format_from_wp($use_time).' '.$this->get_timepicker_format_from_wp($use_time[3], $use_time[4]);
		}
	}

	private function parse_posted_keys($filter = false) {
		$settings = array();

		if (empty($_POST)) return $settings;

		foreach ($_POST as $key => $val) {
			if (0 === strpos($key, 'openinghours-')) {
				$nkey = substr($key, 13);
				if (!$filter || 0 === strpos($nkey, $filter)) {
					$settings[$nkey] = $val;
				}
			}
		}

		// An isset() test is done on this when rendering the options, to handle our changing of the default. By explicitly setting it here, we can then know that the user has deliberately saved the option.
		if (!empty($settings) && !isset($settings['shippingoptional'])) $settings['shippingoptional'] = false;

		return $settings;
	}

	/**
	 * Get a list of WooCommerce shipping methods
	 *
	 * @return Array - keys are shipping method IDs, and values are shipping objects
	 */
	private function get_shipping_methods() {
		$woocommerce = WC();
		$shipping_methods = is_object($woocommerce->shipping) ? $woocommerce->shipping->load_shipping_methods() : array();
		return $shipping_methods;
	}
	
	/**
	 * Get information about shipping methods and zones
	 *
	 * @return Array - keyed by shipping method ID, in which each entry is an array with keys 'method_object' (the shipping method object) and 'zones' (a list of shipping zone IDs that the shipping method is active in)
	 */
	private function get_shipping_methods_and_zones() {
	
		if (is_array($this->shipping_methods_and_zones)) return $this->shipping_methods_and_zones;
	
		$woocommerce = WC();
		$methods_and_zones = array();
		
		$shipping_methods = $this->get_shipping_methods();
		foreach ($shipping_methods as $method_id => $method_object) {
			$methods_and_zones[$method_id] = array(
				'method_object' => $method_object,
				'zones' => array(),
				'instances' => array(),
			);
		}
		
		if (version_compare($woocommerce->version, '2.6', '>=')) {
			global $wpdb, $table_prefix;
			$zones = $wpdb->get_results("SELECT DISTINCT zone_id, method_id, instance_id FROM ${table_prefix}woocommerce_shipping_zone_methods WHERE is_enabled=1");
		
			foreach ($zones as $zone) {
				$zone_id = $zone->zone_id;
				$method_id = $zone->method_id;
				$instance_id = $zone->instance_id;
				if (isset($methods_and_zones[$method_id])) {
					if (!isset($methods_and_zones[$method_id]['zones'][$zone_id])) $methods_and_zones[$method_id]['zones'][$zone_id] = array();
					if (!in_array($instance_id, $methods_and_zones[$method_id]['zones'][$zone_id])) $methods_and_zones[$method_id]['zones'][$zone_id][] = $instance_id;
					if (!in_array($instance_id, $methods_and_zones[$method_id]['instances'])) $methods_and_zones[$method_id]['instances'][] = $instance_id;
				}
			}
		
		}
		
		$this->shipping_methods_and_zones = $methods_and_zones;

		return $methods_and_zones;
		
	}
	
	/**
	 * Run upon the action wp_footer when Opening Hours is doing things on the page
	 */
	public function wp_footer_add_debug_mode() {
		echo "<script>var openinghours_debug_mode = ".($this->debug_mode ? 'true' : 'false').";</script>\n";
	}
	
	/**
	 * Enqueue scripts
	 */
	public function enqueue_scripts() {

		if (!function_exists('WC') || (is_admin() && !current_user_can('manage_woocommerce'))) return;

		// Note: this block returns
		if (function_exists('is_cart') && is_cart()) {
		
			$enqueue_version = (defined('WP_DEBUG') && WP_DEBUG) ? $this->version.'.'.time() : $this->version;
		
			wp_enqueue_script('openinghours-js', OPENINGTIMES_URL.'/js/openinghours.js', array('jquery'), $enqueue_version);

			add_action('wp_footer', array($this, 'wp_footer_add_debug_mode'));
			
			return;
		}

		global $pagenow;

		$woocommerce = WC();

		// In WP 4.5, the category editing moved from edit-tags.php to term.php - and no longer had the 'action' parameter
		if (
			('admin.php' == $pagenow && !empty($_REQUEST['page']) && 'wc-settings' == $_REQUEST['page'])
			|| (('edit-tags.php' == $pagenow || 'term.php' == $pagenow) && ('term.php' == $pagenow || (!empty($_REQUEST['action']) && 'edit' == $_REQUEST['action'])) && !empty($_REQUEST['taxonomy']) && 'product_cat' == $_REQUEST['taxonomy'])
			|| (function_exists('is_checkout') && is_checkout())
		) {

			if ('admin.php' == $pagenow && is_admin() && current_user_can('manage_woocommerce')) {
				$settings = $this->parse_posted_keys();
				// There must be at least one setting - the radio is compulsory
				if (count($settings) > 0) {
					$settings['last_saved_on'] = $this->version;
					$settings['last_wc_saved_on'] = $woocommerce->version;
					update_option('openinghours_options', $settings);
				}
			}

			$min_or_not = ($this->debug_mode || (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG)) ? '' : '.min';
			
			$timepickerjs_version_series = $this->get_timepickerjs_version_series();
			
			if ('1.6' == $timepickerjs_version_series) {
				wp_register_script('jquery-ui-timepicker', OPENINGTIMES_URL.'/js/jquery-ui-timepicker-addon-16'.$min_or_not.'.js', array('jquery-ui-datepicker', 'jquery-ui-slider'), '1.6.3', true);
			} else {
				wp_register_script('jquery-ui-timepicker', OPENINGTIMES_URL.'/js/jquery-ui-timepicker-addon'.$min_or_not.'.js', array('jquery-ui-datepicker', 'jquery-ui-slider'), '1.5.5', true);
			}

			if (function_exists('is_checkout') && is_checkout()) {
				$jquery_version = isset($wp_scripts->registered['jquery-ui-core']->ver ) ? $wp_scripts->registered['jquery-ui-core']->ver : '1.10.3';

				$jquery_ui_url = (file_exists(OPENINGTIMES_DIR.'/css/jqueryui/'.$jquery_version.'/themes/smoothness/jquery-ui.css')) ? OPENINGTIMES_URL.'/css/jqueryui/'.$jquery_version.'/themes/smoothness/jquery-ui.css' : '//ajax.googleapis.com/ajax/libs/jqueryui/'.$jquery_version.'/themes/smoothness/jquery-ui.css';

				wp_enqueue_style('jquery-ui-style', apply_filters('openinghours_jquery_ui_url', $jquery_ui_url, $jquery_version));
					
			}

			$settings = $this->get_options();
			if (!is_array($settings)) $settings = array();
			$holidays = array();
			$holiday_shipping_methods = array();

			$days_with_no_opening_hours = array('default' => array(0, 1, 2, 3, 4, 5, 6));

			$methods_and_zones = $this->get_shipping_methods_and_zones();
			$shipping_method_ids = array_merge(array('default'), array_keys($methods_and_zones));
			
			foreach ($methods_and_zones as $method_id => $method_data) {
			
				$days_with_no_opening_hours[$method_id] = array(0, 1, 2, 3, 4, 5, 6);
				if (!empty($method_data['instances'])) {
					foreach ($method_data['instances'] as $instance_id) {
						$shipping_method_full = $method_id.':'.$instance_id;
						$days_with_no_opening_hours[$shipping_method_full] = array(0, 1, 2, 3, 4, 5, 6);
					}
				}
			}
			
			$mingap = 0;

			foreach ($settings as $key => $val) {
				if (preg_match('/^openinghours-(shipmethod-(\S+)-)?(\d+)-day$/', $key, $matches_now)) {
					$shipping_method = empty($matches_now[1]) ? 'default' : $matches_now[2];
					$ind = ('default' == $shipping_method) ? $matches_now[3] : 'shipmethod-'.$shipping_method.'-'.$matches_now[3];

					if (preg_match('/^(\S+)__I-(\d+)$/', $shipping_method, $imatches)) {
						$shipping_method = $imatches[1];
						$instance_id = $imatches[2];
						$day_array_key = $shipping_method.':'.$instance_id;
					} else {
						$day_array_key = $shipping_method;
					} 
					
					if (isset($settings["openinghours-".$ind."-hour"]) && isset($settings["openinghours-".$ind."-minute"]) && isset($settings["openinghours-".$ind."-close-hour"]) && isset($settings["openinghours-".$ind."-close-minute"])) {
						if (isset($days_with_no_opening_hours[$day_array_key]) && false !== ($key = array_search($val, $days_with_no_opening_hours[$day_array_key]))) {
							unset($days_with_no_opening_hours[$day_array_key][$key]);
						}
					}
				} elseif (preg_match('/^holiday-(\d+)-date$/', $key) && preg_match('/^(\d+)-(\d+)-(\d+)$/', $val, $matches_now)) {
					$year = (int)$matches_now[1];
					$month = (int)$matches_now[2];
					$day = (int)$matches_now[3];
					$repeat = !empty($settings[$key."-repeat"]);
					$holidays[] = $repeat ? $month.'-'.$day : $year.'-'.$month.'-'.$day;
					$holiday_shipping_key = substr($key, 0, strlen($key) - 5).'-shipping_method';
					$holiday_shipping_method = empty($settings[$holiday_shipping_key]) ? 'all' : $settings[$holiday_shipping_key];
					$holiday_shipping_methods[] = apply_filters('openinghours_shipping_method_for_front_end', $holiday_shipping_method);
				} elseif ('maxdays' == $key) {
					if ($this->date_only) {
						$maxdate = $val;
					} else {
						$maxdate = $val;
					}
				} elseif ('mingap' == $key) {
					$mingap = $this->calculate_mingap_from_options($settings);
				}
			}
			
			// If there are no opening hours defined, then by default we are always open
			if (7 == count($days_with_no_opening_hours['default'])) {
				$days_with_no_opening_hours['default'] = array();
			}
			
			foreach ($methods_and_zones as $method_id => $method_data) {
				if (!empty($method_data['instances'])) {
					foreach ($method_data['instances'] as $instance_id) {
						$shipping_method_full = $method_id.':'.$instance_id;
						if (7 == count($days_with_no_opening_hours[$shipping_method_full])) {
							$days_with_no_opening_hours[$shipping_method_full] = $days_with_no_opening_hours['default'];
						}
					}
				} else {
					if (7 == count($days_with_no_opening_hours[$method_id])) {
						$days_with_no_opening_hours[$method_id] = $days_with_no_opening_hours['default'];
					}
				}
			}
			
			/*
			// Old code, which isn't instance-aware
			foreach ($shipping_method_ids as $id) {
				if ('default' == $id) continue;
				if (7 == count($days_with_no_opening_hours[$id])) {
					$days_with_no_opening_hours[$id] = $days_with_no_opening_hours['default'];
				}
			}
			*/

			// Now, check the cart - some products may have date restrictions on them also - add these also to the list of days that cannot be selected in the widget (helps prevent hunt-and-guess)
			if (function_exists('is_checkout') && is_checkout() && is_object($woocommerce->cart)) {

				$cache_category_hours = array();
				$cache_category_mingap = array();
				$cart = $woocommerce->cart->get_cart();
				$cart_allowed_on_days = true;
				foreach ($cart as $item) {
					if (!is_array($item) || !isset($item['data'])) continue;
					$_product = $item['data'];
					
					
					if (is_a($_product, 'WC_Order_Item_Product')) {
						$_product_id = $_product->get_variation_id();
						if (empty($product_id)) {
							$_product_id = $_product->get_product_id();
						}
					} elseif (is_a($_product, 'WC_Product_Variation')) {
						// On WC 3.0, get_id() returns the variation ID, whereas the 'id' property (for which direct access is deprecated) is/was the parent. 
						$_product_id = 
						is_callable(array($_product, 'get_parent_id')) ? $_product->get_parent_id() : $_product->id;
					} else {
						$_product_id = $this->wc_compat->get_id($_product);
					}
					
					$categories = get_the_terms($_product_id, 'product_cat');
					if (!is_array($categories)) $categories = array();
					foreach ($categories as $cat) {
						if (!isset($cat->term_id)) continue;
						if (isset($cache_category_hours[$cat->term_id])) {
							$hours = $cache_category_hours[$cat->term_id];
						} else {
							$hours = $this->get_woocommerce_term_meta($cat->term_id, 'opening_hours_allowed_hours', true);
							if (!is_array($hours)) $hours = array();
							$cache_category_hours[$cat->term_id] = $hours;
						}
						if (isset($cache_category_mingap[$cat->term_id])) {
							$category_mingap = $cache_category_mingap[$cat->term_id];
						} else {
							$category_mingap = absint((int)$this->get_woocommerce_term_meta($cat->term_id, 'opening_hours_mingap', true));
							$cache_category_mingap[$cat->term_id] = $category_mingap;
						}
						if ($category_mingap > $mingap) $mingap = $category_mingap;

						foreach ($hours as $key => $day_indicated) {
							if (preg_match('/^openinghours-(\d+)-day$/', $key, $matches_now)) {
								$ind = $matches_now[1];
								if (isset($hours["openinghours-".$ind."-hour"]) && isset($hours["openinghours-".$ind."-minute"]) && isset($hours["openinghours-".$ind."-close-hour"]) && isset($hours["openinghours-".$ind."-close-minute"])) {
									// Indicates that some restrictions existed
									if (!is_array($cart_allowed_on_days)) $cart_allowed_on_days = array();
									$cart_allowed_on_days[$day_indicated] = true;
								}
							}
						}
					}

					$this->mingap_for_cart = $mingap;

				}

				// See if any new restrictions have been created - if they have, then add them to the final list
				if (is_array($cart_allowed_on_days)) {
					$cart_prohibited_days = array_diff(array(0, 1, 2, 3, 4, 5, 6), array_keys($cart_allowed_on_days));
					foreach ($cart_prohibited_days as $day) {
						foreach ($days_with_no_opening_hours as $id => $days) {
							if (isset($days[$day])) {
								unset($days[$day]);
								$days_with_no_opening_hours[$id] = $days;
							}
						}
					}
				}

			}

			$js_deps = array('jquery', 'jquery-ui-datepicker', 'jquery-ui-timepicker');

			$lang = get_locale();
			
			$datepicker_lang = '';
			if (!empty($lang)) {
				if (preg_match('/^(.*)_.*$/', $lang, $matches)) $lang_stub = $matches[1];
				if (file_exists(OPENINGTIMES_DIR.'/js/datepicker-i18n/datepicker-'.$lang.'.js')) {
					$datepicker_lang = 'datepicker-'.$lang.'.js';
				} elseif (!empty($lang_stub) && file_exists(OPENINGTIMES_DIR.'/js/datepicker-i18n/datepicker-'.$lang_stub.'.js')) {
					$datepicker_lang = 'datepicker-'.$lang_stub.'.js';
				}
				if (!empty($datepicker_lang)) {
					wp_register_script('jquery-ui-datepicker-i18n', OPENINGTIMES_URL.'/js/datepicker-i18n/'.$datepicker_lang, array('jquery-ui-datepicker'), '1.4.2', true);
					$js_deps[] = 'jquery-ui-datepicker-i18n';
				}
			}

			$timepicker_lang = '';
			if (!empty($lang)) {
				if (preg_match('/^(.*)_.*$/', $lang, $matches)) $lang_stub = $matches[1];
				if (file_exists(OPENINGTIMES_DIR.'/js/timepicker-i18n/jquery-ui-timepicker-'.$lang.'.js')) {
					$timepicker_lang = 'jquery-ui-timepicker-'.$lang.'.js';
				} elseif (!empty($lang_stub) && file_exists(OPENINGTIMES_DIR.'/js/timepicker-i18n/jquery-ui-timepicker-'.$lang_stub.'.js')) {
					$timepicker_lang = 'jquery-ui-timepicker-'.$lang_stub.'.js';
				}
				if (!empty($timepicker_lang)) {
					wp_register_script('jquery-ui-timepicker-i18n', OPENINGTIMES_URL.'/js/timepicker-i18n/'.$timepicker_lang, array('jquery-ui-timepicker'), '1.4.2', true);
					$js_deps[] = 'jquery-ui-timepicker-i18n';
				}
			}

			$enqueue_version = (defined('WP_DEBUG') && WP_DEBUG) ? $this->version.'.'.time() : $this->version;
			
			$frontend_days_with_no_opening_hours = array();
			if (is_array($days_with_no_opening_hours)) {
				foreach ($days_with_no_opening_hours as $print_shipping_id => $value) {
					$print_shipping_id = apply_filters('openinghours_shipping_method_for_front_end', $print_shipping_id);
					$frontend_days_with_no_opening_hours[$print_shipping_id] = $value;
				}
			}
			
			wp_enqueue_script('openinghours-js', OPENINGTIMES_URL.'/js/openinghours.js', $js_deps, $enqueue_version);
			add_action('wp_footer', array($this, 'wp_footer_add_debug_mode'));
			$localize = array(
				'sunday' => __('Sunday'),
				'monday' => __('Monday'),
				'tuesday' => __('Tuesday'),
				'wednesday' => __('Wednesday'),
				'thursday' => __('Thursday'),
				'friday' => __('Friday'),
				'saturday' => __('Saturday'),
				'openfrom' => __('From:', 'openinghours'),
				'until' => __('until', 'openinghours'),
				'delete' => __('Delete'),
				'repeat' => __('Repeat each year', 'openinghours'),
				'choosedate' => __('Choose a date', 'openinghours'),
				'isholiday' => apply_filters('openinghours_frontendtext_closedforholiday', __('We are closed on this day.', 'openinghours')),
				'lang' => $lang,
				'date_only' => (int)$this->date_only,
				'datepicker_lang' => $datepicker_lang,
				'timepicker_lang' => $timepicker_lang,
				'holidays' => json_encode($holidays),
				'holiday_shipping_methods' => json_encode($holiday_shipping_methods),
				'days_with_no_opening_hours' => json_encode($frontend_days_with_no_opening_hours),
				'isclosedday' => apply_filters('openinghours_frontendtext_closedday', __('We do not open on this day of the week.', 'openinghours')),
				'timewarning' => __('You must choose a closing time that is later than the opening time', 'openinghours'),
				'after_midnight_warning' => __('The hour "24" can only be chosen as part of 24:00 to indicate "the end of the day". If your shop is open past midnight, then finish this entry at 24:00 and then create a second entry for the following day, beginning at 00:00.', 'openinghours'),
				'datepickerdateformat' => $this->get_datepicker_format_from_wp(),
				'datetimepickerstepminute' => (string)$this->date_time_picker_step_minute(),
				'ajaxurl' => admin_url('admin-ajax.php'), 
				'ajaxnonce' => wp_create_nonce('openinghours-ajax-nonce'),
				'datepickertimeformat' => $this->get_timepicker_format_from_wp(),
				'firstday' => get_option('start_of_week', 0),
				'timepickercontroltype' => apply_filters('openinghours_timepickercontroltype', 'slider'),
				'datepickeroneline' => apply_filters('openinghours_datepickerformat_oneline', false),
				'choose_soonest_time_on_shipping_method_switch' => apply_filters('openinghours_choose_soonest_time_on_shipping_method_switch', false),
				'all_shipping_methods' => __('All shipping methods', 'openinghours'),
				'wp_inc' => includes_url(),
				'customer_picker_choice' => $this->get_customer_choice(),
			);

			if (isset($maxdate)) $localize['maxdate'] = $maxdate;
			// N.B. With mindate, the format of the data parsed depends upon date_only
			
			$gmt_offset = get_option('gmt_offset');
			if (!is_numeric($gmt_offset)) $gmt_offset = 0;
			
			if (!empty($this->mingap_for_cart)) {
				$now_plus_mingap = time() + 60*$this->mingap_for_cart;
				// The time picker does not show or use any timezone. So, if you tell it that the minimum date is a certain time, but the WP timezone is something different, then you won't get what you expect unless you first adjust.
				// So... if the WP timezone is one hour ahead, then at mid-day GMT it's 13.00 on 'site time'. If you don't want someone to be able to select something before 13.00, then you need to add the offset on to the minimum time.
				if ($this->date_only) {
					if (date('z', $now_plus_mingap) != date('z', time())) {
						$localize['mindate'] = "+".ceil($this->mingap_for_cart/1440)."D";
					}
				} else {
					// Do not add 3600*$gmt_offset
// 					$localize['mindate'] = 1000 * (time() + 60 * $this->mingap_for_cart);
					$localize['mingap'] = 60000 * $this->mingap_for_cart;
				}
			}

			// Disabled on slider with 1.5 series due to https://github.com/trentrichardson/jQuery-Timepicker-Addon/issues/819
			// Dec 2017: we're now trying it on 1.6. Though, I think the minute fix below is actually what makes it work, rather than it having actually been fixed in 1.6.
			// N.B. $timepickerjs_version_series is set a lot further up
			$timepickerjs_version_series = $this->get_timepickerjs_version_series();
			
			if ('1.6' == $timepickerjs_version_series || 'select' == apply_filters('openinghours_timepickercontroltype', 'slider')) {
				list($first_open, $last_close) = $this->get_min_max_hours();

				// And even when using a select (i.e. dropdown), it's still buggy in its handling of the maximum minute
				$last_close = preg_replace('/:(\d\d)/', ':59', $last_close);
				$first_open = preg_replace('/:(\d\d)/', ':00', $first_open);
				
				if (false != $first_open) $localize['mintime'] = $first_open;
				if (false != $last_close) $localize['maxtime'] = $last_close;
			}

			wp_localize_script('openinghours-js', 'openinghourslion', $localize);

		}
	}
	
	/**
	 * Get the preferred TimePicker version series
	 *
	 * @return String - either '1.5' or '1.6'
	 */
	public function get_timepickerjs_version_series() {
		return apply_filters('openinghours_timepickerjs_version_series', '1.6');
	}
	
	/**
	 * Gets the number of minutes that the date/time picker is to step in
	 *
	 * @return Integer
	 */
	private function date_time_picker_step_minute() {
		return apply_filters('openinghours_datetimepickerstepminute', 5);
	}
	
	// An array, keyed by zone_id, giving the zone names
	private function get_shipping_zone_labels() {

		if (is_array($this->shipping_zone_labels)) return $this->shipping_zone_labels;
	
		$shipping_zone_labels = array();
	
		global $wpdb, $table_prefix;
		$zone_results = $wpdb->get_results("SELECT zone_id, zone_name FROM ${table_prefix}woocommerce_shipping_zones ORDER BY zone_order ASC");
	
		foreach ($zone_results as $zone) {
			$shipping_zone_labels[$zone->zone_id] = $zone->zone_name;
		}
	
		// This is hard-coded in the code of WooCommerce. We could get a new WC_Shipping_Zone(0), but that seems like overkill - it seems exceedingly unlikely to change.
		$shipping_zone_labels[0] = __('Rest of the World', 'woocommerce');
	
		$this->shipping_zone_labels = $shipping_zone_labels;
	
		return $shipping_zone_labels;
	
	}

	/**
	 * @return Array - a list of shipping method IDs, including 'default'
	 */
	private function get_shipping_method_ids() {
		return array_merge(array('default'), array_keys($this->get_shipping_methods_and_zones()));
	}

	/**
	 * Given a shipping method ID and possibly an instance, get a textual title
	 *
	 * @param Integer		  $shipping_method_id
	 * @param Integer|Boolean $instance_id
	 *
	 * @return String
	 */
	private function get_shipping_method_title_from_id($shipping_method_id, $instance_id = false) {
		$shipping_methods_and_zones = $this->get_shipping_methods_and_zones();
		$woocommerce = WC();
		$instances_to_zones = $this->get_shipping_instances_to_zones();
		
		$title = isset($shipping_methods_and_zones[$shipping_method_id]) ? $shipping_methods_and_zones[$shipping_method_id]['method_object']->title : false;
		
		if (!$instance_id || version_compare($woocommerce->version, '2.6', '<') || empty($instances_to_zones[$instance_id])) {
			return $title;
		}
		
		if (!class_exists('WC_Shipping_Zone')) {
			if ($this->debug_mode) error_log("Opening hours: WC_Shipping_Zone class needed loading");
			require_once($woocommerce->plugin_path() . '/includes/class-wc-shipping-zone.php');
		}
		
		try {
			$zone = new WC_Shipping_Zone($instances_to_zones[$instance_id]);
			$methods = $zone->get_shipping_methods(false);
		} catch (Exception $e) {
			// WC can throw an exception if the database has invalid data
			error_log(get_class($e).' when getting shipping methods '.$e->getMessage.' ('.$e->getLine().' in '.$e->getFile().'); indicates an inconsistent database; will continue with default for method');
			return $title;
		}

		if (!isset($methods[$instance_id])) return $title;

		$title = $methods[$instance_id]->title;
		if ('' == $title && isset($methods[$instance_id]->method_title)) $title = $methods[$instance_id]->method_title;
		return $title;
		
	}

	public function get_timepicker_format_from_wp($hour = false, $min = false) {
		$timepickerformat = 'HH:mm';
		$time_format = get_option('time_format');
		if ($hour > 12) {
			$am_pm = 'pm';
			$hour12 = $hour - 12;
		} else {
			$am_pm = (12 == $hour) ? 'pm' : 'am';
			$hour12 = (0 == $hour) ? '12' : $hour;
		} 
		switch ($time_format) {
			case 'g:i a':
				# e.g. 6:19 pm
				if (false !== $hour) return sprintf('%d:%02d %s', $hour12, $min, $am_pm);
				$timepickerformat = 'h:mm tt';
				break;
			case 'G:i a':
				if (false !== $hour) return sprintf('%d:%02d %s', $hour, $min, $am_pm);
				$timepickerformat = 'H:mm tt';
				break;
			case 'g:i A':
				if (false !== $hour) return sprintf('%d:%02d %s', $hour12, $min, strtoupper($am_pm));
				$timepickerformat = 'h:mm TT';
				break;
			case 'G:i A':
				if (false !== $hour) return sprintf('%d:%02d %s', $hour, $min, strtoupper($am_pm));
				$timepickerformat = 'H:mm TT';
				break;
			case 'H:i':
				if (false !== $hour) return sprintf('%02d:%02d', $hour, $min);
				$timepickerformat = 'HH:mm';
				break;
			case 'G:i':
				if (false !== $hour) return sprintf('%d:%02d', $hour, $min);
				$timepickerformat = 'H:mm';
				break;
			default:
				if (false !== $hour) return sprintf('%02d:%02d', $hour, $min);
				break;
		}
		return apply_filters('openinghours_timepickerformat', $timepickerformat);
	}

	public function get_datepicker_format_from_wp($date = false) {
		$date_format = get_option('date_format');
		// Each entry should have a corresponding one in decode_datepicker_date()
		switch ($date_format) {
			case 'm/d/Y':
				if (is_array($date)) return sprintf('%02d/%02d/%04d', $date[1], $date[2], $date[0]);
				$datepickerdateformat = 'mm/dd/yy';
				break;
			case 'j. F Y':
			case 'j. F, Y':
			case 'j F Y':
			case 'j, F Y':
			case 'd/m/Y':
				if (is_array($date)) return sprintf('%02d-%02d-%04d', $date[2], $date[1], $date[0]);
				// Use dashes, not slashes - because PHP's strtotime() uses that to decide whether it's European or American date formatting
				$datepickerdateformat = 'dd-mm-yy';
				break;
			case 'F j, Y':
			case 'Y/m/d':
			default:
				if (is_array($date)) return sprintf('%04d/%02d/%02d', $date[0], $date[1], $date[2]);
				$datepickerdateformat = 'yy/mm/dd';
				break;
		}
		return $datepickerdateformat;
	}

	public function decode_datepicker_time($date) {

		if (!empty($this->date_only)) {
			return apply_filters('openinghours_forced_time', array(12, 0));
		}

		# The mM is optional (and hence actually redundant) because it is not present in all localizations
		if (preg_match('# (\d{1,2}):(\d{1,2})( ([AaPp])[mM]?)?#', $date, $matches)) {
			$hour = $matches[1];
			if (isset($matches[4]) && strtolower($matches[4]) == 'p' && $hour != 12) $hour += 12;
			if (isset($matches[4]) && strtolower($matches[4]) == 'a' && $hour == 12) $hour = 0;
			return array((int)$hour, (int)$matches[2]);
		}
		return false;
	}

	/**
	 * @param String $date
	 *
	 * @return Boolean|Array
	 */
	public function decode_datepicker_date($date) {
		$date_format = get_option('date_format');
		switch ($date_format) {
			case 'm/d/Y':
				if (preg_match('#^(\d{2})/(\d{2})/(\d{4})#', $date, $matches_now)) {
					$month = (int)$matches_now[1];
					$day = (int)$matches_now[2];
					$year = (int)$matches_now[3];
				}
				break;
			case 'd/m/Y':
			case 'j. F Y':
			case 'j. F, Y':
			case 'j F Y':
			case 'j, F Y':
				if (preg_match('#^(\d{2})(/|-)(\d{2})(/|-)(\d{4})#', $date, $matches_now)) {
					$day = (int)$matches_now[1];
					$month = (int)$matches_now[3];
					$year = (int)$matches_now[5];
				}
				break;
			case 'F j, Y':
			case 'Y/m/d':
			default:
				if (preg_match('#^(\d{4})/(\d{2})/(\d{2})#', $date, $matches_now)) {
					$year = (int)$matches_now[1];
					$month = (int)$matches_now[2];
					$day = (int)$matches_now[3];
				}
		}
		return empty($year) ? false : array($year, $month, $day);
	}

	public function hourselector($id) {
		$ret = '';
		$ret .= '<select id="'.$id.'-'.$i.'" name="'.$id.'-'.$i.'">';
		for ($i=0; $i<24; $i++) {
			$ret .= '<option value="'.$i.'">'.sprintf('%02d' , $i).'</option>';
		}
		$ret .= '</select>';
		return $ret;
	}

	public function minuteselector($id) {
		$ret = '';
		$ret .= '<select id="'.$id.'-'.$i.'" name="'.$id.'-'.$i.'">';
		for ($i=0; $i<60; $i=$i+5) {
			$ret .= '<option value="'.$i.'">'.sprintf('%02d' , $i).'</option>';
		}
		$ret .= '</select>';
		return $ret;
	}

	// This is a bit crude - it gets the minimum and maximum across all shipping methods, if there is one
	// Returns an array ($min, $max) - in a format suitable for the timepicker; or, false if no min/max exists
	public function get_min_max_hours() {
		$opening_hour_settings = $this->get_options();
		
		if (!is_array($opening_hour_settings) || 0 == count($opening_hour_settings)) return array(false, false);

		$first_open = false;
		$last_close = false;

		foreach ($opening_hour_settings as $key => $val) {
			if (preg_match('/^openinghours-(shipmethod-(\S+)-)?(\d+)-minute$/', $key, $matches_now)) {

				$ind = $matches_now[1].$matches_now[3];

				if (isset($opening_hour_settings["openinghours-".$ind."-close-minute"]) && isset($opening_hour_settings["openinghours-".$ind."-hour"]) && isset($opening_hour_settings["openinghours-".$ind."-close-hour"]) ) {
					
					$open_minute = $val;
					$close_minute = $opening_hour_settings["openinghours-".$ind."-close-minute"];
					$open_hour = $opening_hour_settings["openinghours-".$ind."-hour"];
					$close_hour = $opening_hour_settings["openinghours-".$ind."-close-hour"];

					$computed_open = $open_hour*60 + $open_minute;
					$computed_close = min($close_hour*60 + $close_minute, 1440);

// 					$computed_open = $open_hour*60;
// 					$computed_close = $close_hour*60;

					if ($computed_open < $first_open || $first_open === false) $first_open = $computed_open;
					if ($computed_close > $last_close) $last_close = $computed_close;
				}
			}
		}

		$first_open = $this->convert_to_minmax_format($first_open);
		$last_close = $this->convert_to_minmax_format($last_close);
		if (preg_match('/^12:00 ?pm$/', $last_close)) $last_close = false;

		return array($first_open, $last_close);

	}

	// Time here is an integer, measured in minutes from the day start
	// Returns false for the first moment of the day (input == 0); returns '12:00 pm' at the end (input = 1440)
	private function convert_to_minmax_format($time) {

		if ($time) {
			$time_min = $time%60;
			$time_hour = ($time - $time_min)/60;
			if ($time_hour == 12) {
				$time = sprintf("12:%02d pm", $time_min);
			} elseif ($time_hour > 12) {
				$time = sprintf("%d:%02d pm", $time_hour-12, $time_min);
			} else {
				$time = sprintf("%d:%02d am", $time_hour, $time_min);
			}
		} else {
			$time = false;
		}
		return $time;
	}

	/**
	 * @param Integer $potyear
	 * @param Integer $potmonth
	 * @param Integer $potday
	 * @param Integer $pothour
	 * @param Integer $potminute
	 * @param String $shipping_method
	 * @param String|Boolean $instance_id
	 *
	 * @return Array
	 */
	public function next_opening_time($potyear, $potmonth, $potday, $pothour, $potminute, $shipping_method = 'default', $instance_id = false) {

		/* Test with:
		require('wp-load.php');
		global $woocommerce_opening_hours;
		# var_dump($woocommerce_opening_hours->next_opening_time($potyear, $potmonth, $potday, $pothour, $potminute));

		# Opening times configured: Mon-Sat, 9-17
		# One-off holiday Nov 1st 2013
		# Repeating holiday Nov 2nd

		# Open/closed status:
		# closed, closed, closed, closed
		# open, closed, open, closed
		# closed, closed, open, closed

		function dvar_dump($a) { list($yr, $mn, $dy, $hr, $min) = $a; printf("%04d-%02d-%02d %02d-%02d\n", $yr, $mn, $dy, $hr, $min); }

		# Nov 1st 2013 = Friday
		dvar_dump($woocommerce_opening_hours->next_opening_time(2013, 11, 1, 16, 34)); # Nov 4th, 9:00
		dvar_dump($woocommerce_opening_hours->next_opening_time(2013, 11, 2, 16, 34)); # Nov 4th, 9:00
		dvar_dump($woocommerce_opening_hours->next_opening_time(2013, 11, 3, 16, 34)); # Nov 4th, 9:00
		dvar_dump($woocommerce_opening_hours->next_opening_time(2013, 11, 3, 17, 34)); # Nov 4th, 9:00
		echo "\n";

		# Nov 1st 2014 = Saturday
		dvar_dump($woocommerce_opening_hours->next_opening_time(2014, 11, 1, 16, 34)); # Nov 1st, 16:34
		dvar_dump($woocommerce_opening_hours->next_opening_time(2014, 11, 2, 16, 34)); # Nov 3rd, 9:00
		dvar_dump($woocommerce_opening_hours->next_opening_time(2014, 11, 3, 16, 34)); # Nov 3rd, 16:34
		dvar_dump($woocommerce_opening_hours->next_opening_time(2014, 11, 3, 17, 34)); # Nov 4th, 9:00
		echo "\n";

		# Nov 1st 2015 = Sunday
		dvar_dump($woocommerce_opening_hours->next_opening_time(2015, 11, 1, 16, 34)); # Nov 3rd, 9:00
		dvar_dump($woocommerce_opening_hours->next_opening_time(2015, 11, 2, 16, 34)); # Nov 3rd, 9:00
		dvar_dump($woocommerce_opening_hours->next_opening_time(2015, 11, 3, 16, 34)); # Nov 3rd, 16:34
		dvar_dump($woocommerce_opening_hours->next_opening_time(2015, 11, 3, 17, 34)); # Nov 4th, 9:00
		echo "\n";

		*/

		# Algorithm: first, check that we're not always open (i.e. no settings). Then:
		# Wind on days until it is no longer a holiday. Next, seek if there's an opening hour left today. If not, then wind on until tomorrow. Repeat.

		if ($this->debug_mode) {
			error_log("OpeningHours: next_opening_time($potyear, $potmonth, $potday, $pothour, $potminute, shipping_method=$shipping_method, instance_id=$instance_id");
		}
		
		$settings = $this->get_options();
		if (!is_array($settings) || 0 == count($settings)) return apply_filters('openinghours_next_opening_time', array((int)$potyear, (int)$potmonth, (int)$potday, (int)$pothour, (int)$potminute), $shipping_method, $instance_id);

		$holidays = array();
		$holiday_shipping_methods = array();
		$opening_hours = array();
		$opening_minutes = array();
		$closing_hours = array();
		$closing_minutes = array();
		$any_opening_hours = false;

		$opening_hour_settings = $this->filter_opening_hours($settings, $shipping_method, $instance_id);

		$full_shipping_method = $instance_id ? $shipping_method.'__I-'.$instance_id : $shipping_method;

		foreach ($opening_hour_settings as $key => $val) {
			if (preg_match('/^openinghours-(shipmethod-(\S+)-)?(\d+)-day$/', $key, $matches_now)) {
				$ind = ('default' == $shipping_method) ? $matches_now[3] : 'shipmethod-'.$full_shipping_method.'-'.$matches_now[3];

				if (isset($opening_hour_settings["openinghours-".$ind."-hour"]) && isset($opening_hour_settings["openinghours-".$ind."-minute"]) && isset($opening_hour_settings["openinghours-".$ind."-close-hour"]) && isset($opening_hour_settings["openinghours-".$ind."-close-minute"])) {

					$any_opening_hours = true;
					$opening_hours[$val][] = (int)$opening_hour_settings["openinghours-".$ind."-hour"];
					$opening_minutes[$val][] = (int)$opening_hour_settings["openinghours-".$ind."-minute"];
					$closing_hours[$val][] = (int)$opening_hour_settings["openinghours-".$ind."-close-hour"];
					$closing_minutes[$val][] = (int)$opening_hour_settings["openinghours-".$ind."-close-minute"];
				}
			} elseif (preg_match('/^holiday-(\d+)-date$/', $key) && preg_match('/^(\d+)-(\d+)-(\d+)$/', $val, $matches_now)) {
				$year = (int)$matches_now[1];
				$month = (int)$matches_now[2];
				$day = (int)$matches_now[3];
				$repeat = !empty($opening_hour_settings[$key."-repeat"]);
				$holidays[] = $repeat ? $month.'-'.$day : $year.'-'.$month.'-'.$day;
				$holiday_shipping_key = substr($key, 0, strlen($key) - 5).'-shipping_method';
				$holiday_shipping_methods[] = empty($opening_hour_settings[$holiday_shipping_key]) ? 'all' : $opening_hour_settings[$holiday_shipping_key];
			}
		}

		while (true) {
			// Look through the list of holidays
			foreach ($holidays as $k => $holiday_date) {

				if ($holiday_date != $potmonth.'-'.$potday && $holiday_date != $potyear.'-'.$potmonth.'-'.$potday) continue;

				// The day matched. But did the shipping method?
				$holiday_shipping_method = $holiday_shipping_methods[$k];

				$compare_shipping_method = (false === strpos($holiday_shipping_method, ':') || false === $instance_id) ? $shipping_method : $shipping_method.':'.$instance_id;
				
				if ('all' != $holiday_shipping_method && $compare_shipping_method != $holiday_shipping_method) continue;

				// Matched the shipping method in the settings also. It is a holiday. Move on to tomorrow.
				
				$potday++;
				if ($potday > 31 || ($potday > 30 && (4 == $potmonth || 6 == $potmonth || 9 == $potmonth || 11 == $potmonth)) || ($potday > 29 && 2 == $potmonth) || ($potday > 28 && 2 == $potmonth && (0 != $potyear % 4 || (0 == $potyear % 100 && 0 != $potyear % 400)))) {
					$potday = 1;
					$potmonth++;
					if ($potmonth>12) { $potmonth = 1; $potyear++; }
				}
				$pothour = 0;
				$potminute = 0;
				continue;
			}

			// Not a holiday
			if (!$any_opening_hours) return array((int)$potyear, (int)$potmonth, (int)$potday, (int)$pothour, (int)$potminute);

			// There are some opening hours. Work out the week day.
			$potweekday = date('w', strtotime(sprintf("%d-%02d-%02d", $potyear, $potmonth, $potday)));

			if (isset($opening_hours[$potweekday])) {
			
				// There are opening times today. However, they may not be sorted. We want the earliest first.
				$opening_hours_for_weekday = $opening_hours[$potweekday];
				
				// N.B. Corner-case: If they have the same hour twice on this day (e.g. open 00:00 - 00:15 and also 00:45 - 01:00), then the results are undefined and the returned value may be incorrect if they were not sorted correctly.
				asort($opening_hours_for_weekday);
				
				foreach ($opening_hours_for_weekday as $ind => $openhour) {

					// If now is within range, then use it.
					$openminute = $opening_minutes[$potweekday][$ind];
					$closehour = $closing_hours[$potweekday][$ind];
					$closemin = $closing_minutes[$potweekday][$ind];
					
					if ($pothour >= $openhour && $pothour <= $closehour && ($pothour > $openhour || $potminute>= $openminute) && ($pothour < $closehour || $potminute < $closemin)) {
						return apply_filters('openinghours_next_opening_time', array((int)$potyear, (int)$potmonth, (int)$potday, (int)$pothour, (int)$potminute), $shipping_method, $instance_id);
					}
					
					// If not, then if there is an openhour/openmin that is later than now, then use that
					if ($openhour > $pothour || ($openhour == $pothour && $openminute >= $potminute)) {
						return apply_filters('openinghours_next_opening_time', array((int)$potyear, (int)$potmonth, (int)$potday, (int)$openhour, (int)$openminute), $shipping_method, $instance_id);
					}
				}
			}
			
			// No opening times today, or if there were, then not suitable any longer. Move on to tomorrow.
			$potday++;
			if ($potday > 31 || ($potday > 30 && (4 == $potmonth || 6 == $potmonth || 9 == $potmonth || 11 == $potmonth)) || ($potday > 29 && 2 == $potmonth) || ($potday > 28 && 2 == $potmonth && (0 != $potyear % 4 || (0 == $potyear % 100 && 0 != $potyear % 400)))) {
				$potday = 1;
				$potmonth++;
				if ($potmonth>12) { $potmonth = 1; $potyear++; }
			}
			$pothour = 0;
			$potminute = 0;
		}
	}

	// Given a complete array of settings, return an array containing only the hours and holidays relevant to the specified shipping method
	private function filter_opening_hours($settings, $shipping_method = 'default', $instance_id = false) {

		$filtered_settings = array();
		$return_key = $shipping_method;
		if ($instance_id) $return_key .= '__I-'.$instance_id;

		// If no settings exist for the chosen shipping method, then switch to default instead
		if ('default' != $shipping_method) {
			$keys_exist = false;
			foreach ($settings as $key => $val) {
				if (preg_match('/^openinghours-(shipmethod-(\S+)-)?(\d+)-day$/', $key, $matches_now)) {
					$which_shipping_method = empty($matches_now[1]) ? 'default' : $matches_now[2];
					if (preg_match('/^(\S+)__I-(\d+)$/', $which_shipping_method, $imatches)) {
						$which_shipping_method = $imatches[1];
						$which_instance_id = $imatches[2];
					} else {
						$which_instance_id = false;
					}
					if ($shipping_method == $which_shipping_method && $instance_id == $which_instance_id) {
						$keys_exist = true;
						break;
					}
				}
			}
			if (false == $keys_exist) {
				$shipping_method = 'default';
				$instance_id = false;
			}
		}
		
		if ($this->debug_mode) {
// 			error_log("filter_opening_hours(): shipping_method=$shipping_method, instance_id=$instance_id, keys_exist=".(isset($keys_exist) ? $keys_exist : 'undefined'));
		}

		foreach ($settings as $key => $val) {
			if (preg_match('/^openinghours-(shipmethod-(\S+)-)?(\d+)-day$/', $key, $matches_now)) {

				// Filter for relevant settings
				$full_shipping_method = $which_shipping_method = empty($matches_now[1]) ? 'default' : $matches_now[2];
				
				if (preg_match('/^(\S+)__I-(\d+)$/', $which_shipping_method, $imatches)) {
					$which_shipping_method = $imatches[1];
					$which_instance_id = $imatches[2];
				} else {
					$which_instance_id = false;
				}

				if ($shipping_method != $which_shipping_method || $instance_id != $which_instance_id) continue;

				$ind = ('default' == $which_shipping_method) ? $matches_now[3] : 'shipmethod-'.$full_shipping_method.'-'.$matches_now[3];
				$rkey = ('default' == $return_key) ? $matches_now[3] : 'shipmethod-'.$return_key.'-'.$matches_now[3];

				if (isset($settings["openinghours-".$ind."-hour"]) && isset($settings["openinghours-".$ind."-minute"]) && isset($settings["openinghours-".$ind."-close-hour"]) && isset($settings["openinghours-".$ind."-close-minute"])) {
					$filtered_settings["openinghours-".$rkey."-day"] = $val;
					$filtered_settings["openinghours-".$rkey."-hour"] = $settings["openinghours-".$ind."-hour"];
					$filtered_settings["openinghours-".$rkey."-minute"] = $settings["openinghours-".$ind."-minute"];
					$filtered_settings["openinghours-".$rkey."-close-hour"] = $settings["openinghours-".$ind."-close-hour"];
					$filtered_settings["openinghours-".$rkey."-close-minute"] = $settings["openinghours-".$ind."-close-minute"];
				}
			} elseif (preg_match('/^holiday-(\d+)-date$/', $key) && preg_match('/^(\d+)-(\d+)-(\d+)$/', $val, $matches_now)) {
				$filtered_settings[$key] = $val;
				if (isset($settings[$key.'-repeat'])) $filtered_settings[$key.'-repeat'] = $settings[$key.'-repeat'];
				$holiday_shipping_key = substr($key, 0, strlen($key) - 5).'-shipping_method';
				if (isset($settings[$holiday_shipping_key])) $filtered_settings[$holiday_shipping_key] = $settings[$holiday_shipping_key];
			}
		}

		return $filtered_settings;
	}

	/**
	 * No timezone conversion is done here: we assume that the input is in the same timezone as our settings
	 *
	 * @param Integer $potyear
	 * @param Integer $potmonth
	 * @param Integer $potday
	 * @param Integer $pothour
	 * @param Integer $potminute
	 * @param String $shipping_method
	 * @param Array|Boolean $settings
	 * @param String|Boolean $instance_id
	 *
	 * @return Boolean - whether the shop is open or not
	 */
	public function is_shop_open($potyear, $potmonth, $potday, $pothour, $potminute, $shipping_method = 'default', $settings = false, $instance_id = false) {

		if ($this->debug_mode) {
			error_log("is_shop_open(potyear=$potyear, potmonth=$potmonth, potday=$potday, pothour=$pothour, potminute=$potminute, shipping_method=$shipping_method, (settings), instance_id=$instance_id)");
			// file_put_contents('/tmp/hours-'.$shipping_method.'-'.$instance_id.'.txt', print_r($settings, true), FILE_APPEND);
		}

		/* Test with:
		require('wp-load.php');
		global $woocommerce_opening_hours;
		# var_dump($woocommerce_opening_hours->is_shop_open($potyear, $potmonth, $potday, $pothour, $potminute));

		# Opening times configured: Mon-Sat, 9-17
		# One-off holiday Nov 1st 2013
		# Repeating holiday Nov 2nd

		# Expected results:
		# closed, closed, closed, closed
		# open, closed, open, closed
		# closed, closed, open, closed

		# Nov 1st 2013 = Friday
		var_dump($woocommerce_opening_hours->is_shop_open(2013, 11, 1, 16, 34));
		var_dump($woocommerce_opening_hours->is_shop_open(2013, 11, 2, 16, 34));
		var_dump($woocommerce_opening_hours->is_shop_open(2013, 11, 3, 16, 34));
		var_dump($woocommerce_opening_hours->is_shop_open(2013, 11, 3, 17, 34));
		echo "\n";

		# Nov 1st 2014 = Saturday
		var_dump($woocommerce_opening_hours->is_shop_open(2014, 11, 1, 16, 34));
		var_dump($woocommerce_opening_hours->is_shop_open(2014, 11, 2, 16, 34));
		var_dump($woocommerce_opening_hours->is_shop_open(2014, 11, 3, 16, 34));
		var_dump($woocommerce_opening_hours->is_shop_open(2014, 11, 3, 17, 34));
		echo "\n";

		# Nov 1st 2015 = Sunday
		var_dump($woocommerce_opening_hours->is_shop_open(2015, 11, 1, 16, 34));
		var_dump($woocommerce_opening_hours->is_shop_open(2015, 11, 2, 16, 34));
		var_dump($woocommerce_opening_hours->is_shop_open(2015, 11, 3, 16, 34));
		var_dump($woocommerce_opening_hours->is_shop_open(2015, 11, 3, 17, 34));
		echo "\n";

		*/

		$formatted_date = sprintf("%d-%02d-%02d", $potyear, $potmonth, $potday);

		$potweekday = date('w', strtotime($formatted_date));

		// We get these always, because we always consult the default 'maxdays' setting
		$default_settings = $this->get_options();

		if (false === $settings) $settings = $default_settings;

		if (!is_array($settings)) $settings = array();

		// Enforce the 'maximum days' check
		if (isset($default_settings['maxdays']) && $default_settings['maxdays'] !== '' && is_numeric($default_settings['maxdays'])) {
			$maxdays = absint($default_settings['maxdays']);
			$order_date = date_create($formatted_date);
			// date_diff() requires PHP 5.3 - which makes this a lot simpler...
			// $date_now = date_create();
			// $interval = date_diff($order_date)->d;
			// if ($interval > $maxdays) { ... }
			$date_at_end_of_maxdays = get_date_from_gmt(gmdate('Y-m-d H:i:s', time()+86400*$maxdays), 'Y-m-d').' 23:59:59';
			// https://secure.php.net/manual/en/datetime.diff.php - PHP 5.2.2 onwards allows date comparison via comparison operators
			$date_at_end_of_maxdays = date_create($date_at_end_of_maxdays);
			if ($order_date > $date_at_end_of_maxdays) {
				if ($this->debug_mode) error_log("is_shop_open: returning false (choice goes beyond maximum days ahead)");
				return false;
			}
		}
		
		// If no settings, then we are always open
		if (is_array($settings) && empty($settings)) {
			if ($this->debug_mode) error_log("is_shop_open: returning true (settings are empty, so defaulting to always open)");
			return true;
		}

		// This variable value is provisional, until we've processed all holidays
		$apparently_open = false;
		$any_opening_hours = false;

		$filtered_settings = $this->filter_opening_hours($settings, $shipping_method, $instance_id);
		
		foreach ($filtered_settings as $key => $val) {
			if (preg_match('/^openinghours-(shipmethod-(\S+)-)?(\d+)-day$/', $key, $matches_now)) {
				// Filter for relevant settings

				// We don't need to detect the instance ID here - because all we're doing is getting the index for picking up the other keys
				$which_shipping_method = empty($matches_now[1]) ? 'default' : $matches_now[2];

				$ind = ('default' == $which_shipping_method) ? $matches_now[3] : 'shipmethod-'.$which_shipping_method.'-'.$matches_now[3];

				if (isset($filtered_settings["openinghours-".$ind."-hour"]) && isset($filtered_settings["openinghours-".$ind."-minute"]) && isset($filtered_settings["openinghours-".$ind."-close-hour"]) && isset($filtered_settings["openinghours-".$ind."-close-minute"])) {
					$any_opening_hours = true;
					$weekday = (int)$val;
					if ($potweekday == $weekday) {
						$hour = (int)$filtered_settings["openinghours-".$ind."-hour"];
						$minute = (int)$filtered_settings["openinghours-".$ind."-minute"];
						$close_hour = (int)$filtered_settings["openinghours-".$ind."-close-hour"];
						$close_minute = (int)$filtered_settings["openinghours-".$ind."-close-minute"];
						
						if ($pothour >= $hour && $pothour <= $close_hour && (($pothour > $hour || $potminute >= $minute) && ($pothour < $close_hour || $potminute <= $close_minute ))) $apparently_open = true;

					}
				}
			} elseif (preg_match('/^holiday-(\d+)-date$/', $key) && preg_match('/^(\d+)-(\d+)-(\d+)$/', $val, $matches_now)) {
				$repeat = empty($filtered_settings[$key."-repeat"]) ? false : true;
				$year = $matches_now[1];
				$month = $matches_now[2];
				$day = $matches_now[3];
				$shipping_method_key = substr($key, 0, strlen($key)-5).'-shipping_method';
				$shipping_id = empty($settings[$shipping_method_key]) ? 'all' : $settings[$shipping_method_key];
				// If the shipping ID in the settings indicates all zones (by the absence of a ':' separator), then we want to not check the instance ID that was passed in to us because it should match all of them
				$pot_shipping_id = ($instance_id && false !== strpos($shipping_id, ':')) ? $shipping_method.':'.$instance_id : $shipping_method;
				// Holiday?
				if ($this->debug_mode) {
					error_log("checktime: Holiday comparison: month:$month:$potmonth day:$day:$potday year:$year:$potyear shipping_id:$shipping_id:$pot_shipping_id repeat=$repeat");
				}
				if ($month == $potmonth && $day == $potday && ($repeat || $year == $potyear)) {
					// Date matches - but does the holiday setting?
					if ('all' == $shipping_id || $shipping_id == $pot_shipping_id) {
						if ($this->debug_mode) error_log("is_shop_open: returning false: matched a holiday");
						return false;
					}
				}
			} else {
				if ($this->debug_mode) {
					// error_log("Unrecognised key: $key with value: $val");
				}
			}
		}

		$result = $any_opening_hours ? $apparently_open : true;

		if ($this->debug_mode) error_log("is_shop_open: returning: $result");

		return $result;

	}

	private function print_rules_section($shipping_method_id, $instance_id = null) {
		if ('default' == $shipping_method_id) echo '<p>'.__('These hours will be used unless alternative hours for a particular shipping method over-ride them.', 'openinghours').'</p>';
		
		$full_method_id = ('default' != $shipping_method_id && null !== $instance_id) ? $shipping_method_id.'__I-'.$instance_id : $shipping_method_id;
		
		?>
		<div id="openinghours-rules-<?php echo $full_method_id;?>" class="openinghours-rules">
			<div class="openinghours-rules-rulediv"></div>
			<?php
				echo '<p><em><a href="#" data-whichsm="'.$shipping_method_id.'" data-which_instance="'.$instance_id.'" class="openinghours-addnewdefault">'.__('Add a set of default rules (Monday - Saturday, 9 a.m. - 5 p.m.)...', 'openinghours').'</a></em><br>';
				echo '<em><a href="#" data-whichsm="'.$shipping_method_id.'" data-which_instance="'.$instance_id.'" class="openinghours-addnew">'.__('Add a new time...', 'openinghours').'</a></em></p>';
			?>
		</div>
		<?php
	}

	/**
	 * Runs upon the WP action woocommerce_admin_field_openinghours
	 */
	public function woocommerce_admin_field_openinghours() {

		$opts = $this->get_options();
		$shipping_methods_and_zones = $this->get_shipping_methods_and_zones();
		$woocommerce = WC();

		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
			<label><?php _e('Regular Opening / Delivery Hours', 'openinghours');?></label>
			</th>
			<td>
				<?php
					if ($this->date_only) {
						$implied_time = apply_filters('openinghours_forced_time', array(12, 0));
						$show_time = sprintf('%02d:%02d', $implied_time[0], $implied_time[1]);
						echo '<p>'.__('Operating in date-only mode; the assumed time for every order (for the purposes of calculating shop status) will be:', 'opening_hours').' '.$show_time.'</p>';
					}
				?>
				<div id="openinghours-rules">
					<?php
					echo '<h2 class="nav-tab-wrapper" id="openinghours-shipping-methods" style="margin: 14px 0px;">';
					$on_first = true;
					?>
						<a class="nav-tab <?php if($on_first) { echo 'nav-tab-active'; $on_first = false; } ?>" href="#openinghours-shipping-methods-navtab-default-content" id="openinghours-shipping-methods-navtab-default"><?php echo htmlspecialchars(__('Default', 'openinghours'));?></a>
					<?php

					if (!empty($shipping_methods_and_zones)) {

						foreach ($shipping_methods_and_zones as $method_id => $method) {
						
							if (empty($method['zones'])) {
							
								// Do not show tabs for instance-supporting shipping methods which have no instances
								if (!isset($method['method_object']) || !is_object($method['method_object']) || !isset($method['method_object']->supports) || !is_array($method['method_object']->supports) || !in_array('instance-settings', $method['method_object']->supports)) {
							
								?>
									<a class="nav-tab <?php if($on_first) { echo 'nav-tab-active'; $on_first = false; } ?>" href="#openinghours-shipping-methods-navtab-<?php echo $method_id;?>-content" id="openinghours-shipping-methods-navtab-<?php echo $method_id;?>"><?php echo $method['method_object']->title;?></a>
								<?php
							
								}
							
							} else {
					
								$shipping_zone_labels = $this->get_shipping_zone_labels();

								foreach ($method['zones'] as $zone_id => $instance_ids) {
								
									foreach ($instance_ids as $instance_id) {
								
										$full_method_id = $method_id.'__I-'.$instance_id;
										$method_title = $method['method_object']->title;
										if ('' == $method_title && isset($method['method_object']->method_title) && '' != $method['method_object']->method_title) $method_title = $method['method_object']->method_title;
									
										?>
											<a class="nav-tab <?php if($on_first) { echo 'nav-tab-active'; $on_first = false; } ?>" href="#openinghours-shipping-methods-navtab-<?php echo $full_method_id;?>-content" id="openinghours-shipping-methods-navtab-<?php echo $full_method_id;?>"><?php echo htmlspecialchars($this->get_shipping_method_title_from_id($method_id, $instance_id).' ('.$shipping_zone_labels[$zone_id].', '.$method_title.')');?></a>
										<?php
									}
								
								}
							}
						}
					}
					echo '</h2>';

					$on_first = true;

					echo "<div class=\"openinghours-shipping-methods-navtab-content\" id=\"openinghours-shipping-methods-navtab-default-content\"";
					if (!$on_first) { echo ' style="display:none;"'; } else { $on_first = false; }
					echo ">";
					$this->print_rules_section('default');
					$this->print_mingap_settings($opts, 'default');
					echo "</div>";

					if (!empty($shipping_methods_and_zones)) {
						$on_first = false;
						foreach ($shipping_methods_and_zones as $method_id => $method) {

							if (empty($method['zones'])) {
						
								echo "<div class=\"openinghours-shipping-methods-navtab-content\" id=\"openinghours-shipping-methods-navtab-".$method_id."-content\"";
								if (!$on_first) { echo ' style="display:none;"'; } else { $on_first = false; }
								echo ">";

								echo "<p>".sprintf(__('If any hours are given here, then the default hours will not be used when this shipping method (%s) is selected - the hours here will be used instead.', 'openinghours'), htmlspecialchars($method['method_object']->title))."</p>";

								$this->print_rules_section($method_id);
								$this->print_mingap_settings($opts, $method_id);
								
								echo "</div>";
							} else {
							
								foreach ($method['zones'] as $zone_id => $instance_ids) {
								
									foreach ($instance_ids as $instance_id) {
								
										$full_method_id = $method_id.'__I-'.$instance_id;
									
										echo "<div class=\"openinghours-shipping-methods-navtab-content\" id=\"openinghours-shipping-methods-navtab-".$full_method_id."-content\"";
										
										if (!$on_first) { echo ' style="display:none;"'; } else { $on_first = false; }
										echo ">";

										echo "<p>".sprintf(__('If any hours are given here, then the default hours will not be used when this shipping method (%s) is selected - the hours here will be used instead.', 'openinghours'), htmlspecialchars($this->get_shipping_method_title_from_id($method_id, $instance_id).' - '.$shipping_zone_labels[$zone_id].', '.$method_title))."</p>";

										
										$this->print_rules_section($method_id, $instance_id);
										$this->print_mingap_settings($opts, $method_id, $instance_id);

										echo "</div>";
									}
								
								}
							
							}
							
						}

					}
					?>
				</div>
			</td>
		</tr>

		<tr valign="top">
			<th scope="row" class="titledesc">
			<label><?php _e('Holidays', 'openinghours');?></label>
			</th>
			<td>
					<?php
						echo '<p><em>'.__('Configure days which cannot be chosen at all.', 'openinghours').'</em><br>';
					?>
					<div id="openinghours-holidays"></div>
					<?php
						echo '<em><a href="#" id="openinghours-addnew-holiday">'.__('Add a new holiday...', 'openinghours').'</a></em></p>';
					?>
			</td>
		</tr>

		<?php
			
			$check_choice = (is_array($opts) && !empty($opts['customerchoices'])) ? $opts['customerchoices'] : 'choosewhenclosed';
			if ('alwayschoose' != $check_choice && 'noorders' != $check_choice && 'informwhenclosed' != $check_choice) $check_choice = 'choosewhenclosed';
			
			// Default has to be 'now', because that is what was done before this setting existed
			$mingap_addminto = empty($opts['addminto']) ? 'now' : $opts['addminto'];

			$maxdays = (isset($opts['maxdays'])) ? $opts['maxdays'] : '';
			if ($maxdays != '') $maxdays=absint($maxdays);
			$shippingoptional = isset($opts['shippingoptional']) ? $opts['shippingoptional'] : true;
			$category_restrictions_ignore_empty_sets = !empty($opts['category_restrictions_ignore_empty_sets']);
		?>

		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="openinghours_mingap"><?php _e('Minimum Order Fulfilment Time', 'openinghours');?></label>
			</th>
			<td class="forminp forminp-number">
				<p>
					<?php _e('Count the minimum fulfilment time beginning from:', 'openinghours');?><br>
					<input type="radio" <?php if ('now' == $mingap_addminto) echo 'checked="checked" ';?>name="openinghours-addminto" id="openinghours-addminto_now" value="now"><label for="openinghours-addminto_now"><?php _e('The time that the customer is ordering (default)', 'openinghours');?></label><br>
					<input type="radio" <?php if ('opening' == $mingap_addminto) echo 'checked="checked" ';?>name="openinghours-addminto" id="openinghours-addminto_opening" value="opening"><label for="openinghours-addminto_opening"><?php _e("The time when the shop first opens/opened today (for any shipping method). When the shop is closed all day, or has already closed, the minimum time will be added to the time now instead.", 'openinghours');?></label>
				</p>
			</td>
		</tr>

		<tr valign="top">
			<th scope="row" class="titledesc">
			<label><?php _e('Customer Check-out Choices', 'openinghours');?></label>
			</th>
			<td>

				<input type="radio" <?php if ('choosewhenclosed' == $check_choice) echo ' checked="checked"'; ?> name="openinghours-customerchoices" id="openinghours-customerchoices3" value="choosewhenclosed">
				<label for="openinghours-customerchoices3"><?php _e('Outside of opening hours, force the customer to choose a fulfilment time when checking-out', 'openinghours');?></label>
				<br>

				<input type="radio" <?php if ('informwhenclosed' == $check_choice) echo ' checked="checked"'; ?> name="openinghours-customerchoices" id="openinghours-customerchoices4" value="informwhenclosed">
				<label for="openinghours-customerchoices4"><?php _e('Outside of opening hours, advise the customer that their order will not be fulfilled until opening times (but neither prevent check-out nor ask the customer to choose a time)', 'openinghours');?></label>
				<br>

				<input type="radio" <?php if ('noorders' == $check_choice) echo ' checked="checked"'; ?> name="openinghours-customerchoices" id="openinghours-customerchoices2" value="noorders">
				<label for="openinghours-customerchoices2"><?php _e('Outside of opening hours, forbid any customers to check-out', 'openinghours');?></label>
				<br>

				<input type="radio" <?php if ('alwayschoose' == $check_choice) echo ' checked="checked"'; ?> name="openinghours-customerchoices" id="openinghours-customerchoices" value="alwayschoose">
				<label for="openinghours-customerchoices"><?php _e('Always force the customer to choose a time when checking-out', 'openinghours');?></label>

			</td>
		</tr>

		<tr valign="top">
			<th scope="row" class="titledesc">
			<label for="openinghours_maxdays"><?php _e('Maximum Number of Days Ahead', 'openinghours');?></label>
			</th>
			<td class="forminp forminp-number">
			<input name="openinghours-maxdays" id="openinghours_maxdays" type="number" style="width:50px;" value="<?php echo $maxdays; ?>" class="" min="0" step="1"> <span style="margin-top: 2px;"><?php _e('days', 'openinghours');?></span>
			<br>
			<em><?php
				echo htmlspecialchars(__('Enter the maximum number of days ahead that it is possible to choose a time from. Leave blank for no maximum (0 means "today only").', 'openinghours'));
			?></em>
			</td>
		</tr>

		<tr valign="top">
			<th scope="row" class="titledesc">
				<label><?php _e('Apply To Virtual Goods', 'openinghours');?></label>
			</th>
			<td>
				<input type="checkbox" <?php if ($shippingoptional) echo ' checked="checked"'; ?> name="openinghours-shippingoptional" id="openinghours-shippingoptional" value="1"><br>
				<em><label for="openinghours-shippingoptional"><?php echo __('If you wish your chosen times to apply even when there are no shippable goods, then choose this option.', 'openinghours').' '.__("If this option is not selected and the customer's cart only contains goods which do not require shipping (i.e. virtual goods), then your chosen times will be ignored - checkout will always be allowed.", 'openinghours'); ?></label></em>
			</td>
		</tr>

		<tr valign="top">
			<th scope="row" class="titledesc">
				<label><?php _e('Ignore Unrestricted Categories', 'openinghours');?></label>
			</th>
			<td>
				<input type="checkbox" <?php if ($category_restrictions_ignore_empty_sets) echo ' checked="checked"'; ?> name="openinghours-category_restrictions_ignore_empty_sets" id="openinghours-category_restrictions_ignore_empty_sets" value="1"><br>
				<em><label for="openinghours-category_restrictions_ignore_empty_sets"><?php echo __("By default, per-category restrictions (if you have any) are applied such that a product in the cart only needs to be in one category that has currently allowed times.", 'openinghours').' '.__('This means that if a category has no restrictions at all, then any products in it will always be allowed, whatever other categories they are in. If you instead wish empty categories to be ignored when handling products in multiple categories, then activate this option.', 'openinghours'); ?></label></em>
			</td>
		</tr>

		<tr valign="top">
			<th scope="row" class="titledesc">
				<?php _e('Relevant Shipping Classes', 'openinghours');?>
			</th>
			<td class="forminp">
			<?php

				$wc_shipping = $woocommerce->shipping();
				$classes = $wc_shipping->get_shipping_classes();
				$relevant_classes = (!empty($opts['shippingclasses'])) ? $opts['shippingclasses'] : array();
				if (empty($classes) || !is_array($classes)) {
					echo '<em>'.__('There are no shipping classes; so there is nothing to set here.', 'openinghours').'</em>';
				} else {
					foreach ($classes as $class) {
						if (!is_object($class) || empty($class->term_id) || empty($class->name)) continue;
						echo '<input'.((in_array($class->term_id, $relevant_classes)) ? ' checked="checked"' : '').' type="checkbox" name="openinghours-shippingclasses[]" id="openinghours_shippingclass_'.$class->term_id.'" value="'.$class->term_id.'"><span class="openinghours_shippingclass" title="'.esc_attr($class->description).'"><label for="openinghours_shippingclass_'.$class->term_id.'">'.htmlspecialchars($class->name).'</label></span>';
					}
				}
			?>
			<br>
			<em><?php
				echo '<p>'.htmlspecialchars(__('If you select any shipping classes here then your chosen times will only be consulted if there are products in the shopping cart from those classes.', 'openinghours')).'</p>';
			?></em>
			</td>
		</tr>
		
		<tr valign="top">
			<th scope="row" class="titledesc">
				<?php _e('Export settings', 'openinghours');?>
			</th>
			<td class="forminp">
				<button id="openinghours-export-settings"><?php _e('Export settings', 'openinghours');?></button>
				<img id="openinghours_export_spinner" src="<?php echo esc_attr(admin_url('images/spinner.gif'));?>" style="width:18px; height: 18px;padding-left: 18px;display:none;">
				<br>
				<p><?php _e('The main use of this button is for debugging purposes - it allows a third party who does not have access to your WP dashboard to easily see/analyse/reproduce your settings.', 'openinghours');?></p>
			</td>
		</tr>

		<?php

	}

	public function print_mingap_settings($opts, $shipping_method_id = 'default', $instance_id = null) {
	
		// Historical settings for mingap / mingap_further / mingap_ifpast_hour / mingap_ifpast_minute were integers. If these are found, they should be applied to all shipping methods. Since 1.6.1, the option is per-shipping method; in that case, it is an array (but if no key is found, then the default should be used).

		$mingap = empty($opts['mingap']) ? 0 : $opts['mingap'];
		$mingap_further = empty($opts['mingap_further']) ? 0 : $opts['mingap_further'];

		$mingap_ifpast_hour = isset($opts['mingap_ifpast_hour']) ? $opts['mingap_ifpast_hour'] : 0;
		$mingap_ifpast_minute = isset($opts['mingap_ifpast_minute']) ? $opts['mingap_ifpast_minute'] : 0;

		$full_shipping_method = ('default' != $shipping_method_id && $instance_id != null) ? $shipping_method_id.'__I-'.$instance_id : $shipping_method_id;
		
		// Minimum gap
		if (is_array($mingap)) {
			$mingap = isset($mingap[$full_shipping_method]) ? $mingap = $mingap[$full_shipping_method] : (isset($mingap['default']) ? $mingap['default'] : 0);
		}
		$mingap = (int)$mingap;

		// Add a further number of minutes
		if (is_array($mingap_further)) {
			$mingap_further = isset($mingap_further[$full_shipping_method]) ? $mingap_further = $mingap_further[$full_shipping_method] : (isset($mingap_further['default']) ? $mingap_further['default'] : 0);
		}
		$mingap_further = absint($mingap_further);
		
		// If time has gone past (hour)
		if (is_array($mingap_ifpast_hour)) {
			$mingap_ifpast_hour = isset($mingap_ifpast_hour[$full_shipping_method]) ? $mingap_ifpast_hour = $mingap_ifpast_hour[$full_shipping_method] : (isset($mingap_ifpast_hour['default']) ? $mingap_ifpast_hour['default'] : 0);
		}
		$mingap_ifpast_hour = absint($mingap_ifpast_hour);
		
		// If time has gone past (minutes)
		if (is_array($mingap_ifpast_minute)) {
			$mingap_ifpast_minute = isset($mingap_ifpast_minute[$full_shipping_method]) ? $mingap_ifpast_minute = $mingap_ifpast_minute[$full_shipping_method] : (isset($mingap_ifpast_minute['default']) ? $mingap_ifpast_minute['default'] : 0);
		}
		$mingap_ifpast_minute = absint($mingap_ifpast_minute);
		
		$full_shipping_method_escaped = esc_attr($full_shipping_method);
		
		$mingap_ifpast_selector = '<select name="openinghours-mingap_ifpast_hour['.$full_shipping_method_escaped.']">';
		for ($i=0; $i<24; $i++) {
			$mingap_ifpast_selector .= '<option value="'.$i.'"'.(($i==$mingap_ifpast_hour) ? ' selected="selected"' : '').'>'.sprintf('%02d', $i).'</option>';
		}
		$mingap_ifpast_selector .= '</select>';

		$mingap_ifpast_selector .= ' : <select name="openinghours-mingap_ifpast_minute['.$full_shipping_method_escaped.']">';
		for ($i=0; $i<60; $i += 5) {
			$mingap_ifpast_selector .= '<option value="'.$i.'"'.(($i==$mingap_ifpast_minute) ? ' selected="selected"' : '').'>'.sprintf('%02d', $i).'</option>';
		}
		$mingap_ifpast_selector .= '</select>';
	
		?>
			<div class="openinghours-mingap-rules">
			
			<strong><?php _e('Minimum Order Fulfilment Time', 'opening_hours');?></strong><br>
			
			<em><?php
					echo htmlspecialchars(__('Enter the minimum number of minutes from ordering time that a customer is allowed to choose. Note that this only applies if the customer is indeed being asked to make a choice (otherwise, "as soon as possible" is implied).', 'openinghours'));
				?></em><br>
			
			<?php
			
			if ('default' != $full_shipping_method) {
			
				$mingap_usedefault = isset($opts['mingap_usedefault'][$full_shipping_method]) ? (bool)$opts['mingap_usedefault'][$full_shipping_method] : true;
				
				?>
				<div style="padding: 8px 0 8px;" class="mingap_usedefault_container" data-shipping_method="<?php echo $full_shipping_method_escaped;?>">
					<input type="radio" value="1" class="mingap_usedefault mingap_usedefault_yes" name="openinghours-mingap_usedefault[<?php echo $full_shipping_method_escaped;?>]" id="mingap_usedefault_<?php echo $full_shipping_method_escaped;?>_yes" data-shipping_method="<?php echo $full_shipping_method_escaped;?>" <?php if ($mingap_usedefault) echo ' checked="checked"';?>> <label for="mingap_usedefault_<?php echo $full_shipping_method_escaped;?>_yes"><?php _e('Use the default settings with this shipping method', 'opening_hours');?></label><br>
				
					<input type="radio" value="0" class="mingap_usedefault" name="openinghours-mingap_usedefault[<?php echo $full_shipping_method_escaped;?>]" id="mingap_usedefault_<?php echo $full_shipping_method_escaped;?>_no" data-shipping_method="<?php echo $full_shipping_method_escaped;?>" <?php if (!$mingap_usedefault) echo ' checked="checked"';?>> <label for="mingap_usedefault_<?php echo $full_shipping_method_escaped;?>_no"><?php _e('Over-ride the default settings', 'opening_hours');?></label>
				</div>

				<?php
			}
			
			?>
			
			<div class="openinghours-mingap-method-settings">
				<input name="openinghours-mingap[<?php echo $full_shipping_method_escaped;?>]" id="openinghours_mingap_<?php echo $full_shipping_method_escaped;?>" type="number" style="width:64px;" value="<?php echo $mingap; ?>" class="" min="-1440" step="1"> <span style="margin-top: 2px;"><span style="margin-top: 2px;"><?php _e('minutes', 'openinghours');?></span>. <?php printf(__('If the time today has gone past %s, then add another %s minutes.', 'openinghours_mingapifpast'), '<span id="openinghours_mingapifpast_span">'.$mingap_ifpast_selector.'</span>', '<input name="openinghours-mingap_further['.$full_shipping_method_escaped.']" id="openinghours_mingap_further_'.$full_shipping_method_escaped.'" type="number" style="width:64px;" value="'.$mingap_further.'" class="" min="0" step="1">');?>
			</div>
			
			</div>
			
		<?php
	}
	
	/*
	public function settings_page() {
	
		// This way of doing things is legacy debt, from when we used to be on the WooCommerce settings page
		$settings = $this->woocommerce_general_settings(array());
		
		$header = $settings[0];
		echo '<h1>'.$header['title'].'</h1>';
		
		echo '<p>'.$header['desc'].'</p>';
		
		$this->woocommerce_admin_field_openinghours();
	
	}
	*/
	
	/**
	 * Called by the WP filter woocommerce_general_settings
	 *
	 * @param Array $settings
	 *
	 * @return Array - filtered value
	 */
	public function woocommerce_general_settings($settings) {

		$date_now = get_date_from_gmt(gmdate('Y-m-d H:i:s'), get_option('date_format').' '.get_option('time_format'));

		$settings[] = array(
			'title' => __('Opening Times / Delivery Hours', 'openinghours'),
			'type' => 'title',
			'id' => 'openinghours',
			'desc'  => sprintf(__('Enter the times when your %s is open / can deliver.', 'openinghours'), apply_filters('openinghours_shopsubjectnoun', __('shop', 'openinghours'))).' '.sprintf(__('If there are no times at all, then by default the %s is assumed to be open / able to deliver at all hours.', 'openinghours').' '.sprintf(__('The current time when this page loaded: %s', 'openinghours'), $date_now), apply_filters('openinghours_shopsubjectnoun', __('shop', 'openinghours'))).' <a href="'.admin_url('options-general.php').'">'.__('(Configure your time-zone in your general WordPress settings)', 'openinghours').'</a> | <a href="https://www.simbahosting.co.uk/s3/support/faqs/woocommerce-opening-hours-and-delivery-times-faqs/">'.__('Plugin FAQs', 'openinghours').'</a>'
		);

		$settings[] = array('type' => 'openinghours');
		
		$settings[] = array('type' => 'sectionend', 'id' => 'openinghours');

		add_action('admin_footer', array($this, 'footer'));

		return $settings;

	}

	public function footer() {

		?>
		<style type="text/css">
			<?php if (is_admin() || isset($this->use_these_times)) { ?>
			#openinghours-rules .openinghours-row, #openinghours-holidays .openinghours-holiday-row {
				clear: left;
				padding-bottom: 8px;
			}

			#openinghours-rules .openinghours-rules {
				margin-top: 28px;
				<?php if (!isset($this->use_these_times)) { ?>
				margin-left: 80px;
				<?php } ?>
			}

			#openinghours-rules .openinghours-shipping-methods-navtab-content {
				border-bottom: 1px solid #ccc; padding-bottom: 14px;
			}

			.openinghours-mingap-rules {
				margin-left: 80px;
				margin-top: 20px;
			}
			
			.openinghours_shippingclass {
				margin-right: 16px;
			}

			.openinghours-row .openinghours-timewarning {
				padding-left: 20px;
				color: red;
				font-style: italic;
				max-width: 600px;
			}

			/* Over-ride a new addition in WooCommerce 3.2 which set the width to 400px */
			.woocommerce table.form-table #openinghours-rules input.regular-input, .woocommerce table.form-table #openinghours-rules input[type="email"], .woocommerce table.form-table #openinghours-rules input[type="number"], .woocommerce table.form-table #openinghours-rules input[type="text"], .woocommerce table.form-table #openinghours-rules select, .woocommerce table.form-table #openinghours-rules textarea, .woocommerce table.form-table #openinghours-holidays input.regular-input, .woocommerce table.form-table #openinghours-holidays input[type="email"], .woocommerce table.form-table #openinghours-holidays input[type="number"], .woocommerce table.form-table #openinghours-holidays input[type="text"], .woocommerce table.form-table #openinghours-holidays select, .woocommerce table.form-table #openinghours-holidays textarea {
				height: auto;
				width: auto;
			}
			
			.openinghours-row-delete, .openinghours-holiday-row-delete {
				cursor: pointer;
				color: red;
				font-size: 120%;
				font-weight: bold;
				border: 0px;
				border-radius: 3px;
				padding: 2px;
				margin: 0 6px;
			}
			.openinghours-row-delete:hover, .openinghours-holiday-row-delete:hover {
				cursor: pointer;
				color: white;
				background: red;
			}
			<?php } else { ?>

			/* Don't show the "optional" label in WC 3.4+ */
			label[for="openinghours_time"] .optional { display: none; }
			
			/* Don't show the "Now" button */
			.ui-datepicker-buttonpane .ui-datepicker-current { display: none; }

			/* https://github.com/trentrichardson/jQuery-Timepicker-Addon/blob/master/src/jquery-ui-timepicker-addon.css, https://github.com/trentrichardson/jQuery-Timepicker-Addon/issues/794 */
			.ui-timepicker-div .ui-widget-header { margin-bottom: 8px; }
			.ui-timepicker-div dl { text-align: left; }
			.ui-timepicker-div dl dt { float: left; clear:left; padding: 0 0 0 5px; }
			.ui-timepicker-div dl dd { margin: 0 10px 10px 40%; }
			.ui-timepicker-div td { font-size: 90%; }
			.ui-tpicker-grid-label { background: none; border: none; margin: 0; padding: 0; }
			.ui-timepicker-div .ui_tpicker_unit_hide{ display: none; }

			.ui-timepicker-rtl{ direction: rtl; }
			.ui-timepicker-rtl dl { text-align: right; padding: 0 5px 0 0; }
			.ui-timepicker-rtl dl dt{ float: right; clear: right; }
			.ui-timepicker-rtl dl dd { margin: 0 40% 10px 10px; }

			/* Over-ride a rule in TwentySeventeen theme */
			.ui-timepicker-div input.ui_tpicker_time_input[disabled] { opacity: 1; }

			/* Added for timepicker 1.6 series to change back to previous style */
			.ui-timepicker-div input.ui_tpicker_time_input { border: 0; background-color: transparent; }
			
			/* Shortened version style */
			.ui-timepicker-div.ui-timepicker-oneLine { padding-right: 2px; }
			.ui-timepicker-div.ui-timepicker-oneLine .ui_tpicker_time, 
			.ui-timepicker-div.ui-timepicker-oneLine dt { display: none; }
			.ui-timepicker-div.ui-timepicker-oneLine .ui_tpicker_time_label { display: block; padding-top: 2px; }
			.ui-timepicker-div.ui-timepicker-oneLine dl { text-align: right; }
			.ui-timepicker-div.ui-timepicker-oneLine dl dd, 
			.ui-timepicker-div.ui-timepicker-oneLine dl dd > div { display:inline-block; margin:0; }
			.ui-timepicker-div.ui-timepicker-oneLine dl dd.ui_tpicker_minute:before,
			.ui-timepicker-div.ui-timepicker-oneLine dl dd.ui_tpicker_second:before { content:':'; display:inline-block; }
			.ui-timepicker-div.ui-timepicker-oneLine dl dd.ui_tpicker_millisec:before,
			.ui-timepicker-div.ui-timepicker-oneLine dl dd.ui_tpicker_microsec:before { content:'.'; display:inline-block; }
			.ui-timepicker-div.ui-timepicker-oneLine .ui_tpicker_unit_hide,
			.ui-timepicker-div.ui-timepicker-oneLine .ui_tpicker_unit_hide:before{ display: none; }
			<?php } ?>

		</style>
		<?php

		echo "<script>var openinghours_debug_mode = ".($this->debug_mode ? 'true' : 'false').";</script>\n";
		
		if (!is_admin()) return;

		$settings = isset($this->use_these_times) ? $this->use_these_times : $this->get_options();
		if (!is_array($settings)) return;

		$add_js = array();

		if (!isset($this->use_these_times) && version_compare(WC()->version, '2.6', '>=') && (empty($settings['last_wc_saved_on']) || version_compare($settings['last_wc_saved_on'], '2.6', '<'))) {
			// In this case, we want to show the non-zonal settings (if any), rather than force the user to set them up again
			$wc26_but_not_yet_saved = true;
			$shipping_methods_and_zones = $this->get_shipping_methods_and_zones();
		}
		
		foreach ($settings as $key => $val) {
			if (preg_match('/^openinghours-(shipmethod-(\S+)-)?(\d+)-day$/', $key, $matches_now)) {

				$which_shipping_method = empty($matches_now[1]) ? 'default' : $matches_now[2];
				$full_shipping_method = $which_shipping_method;
				$which_instance_id = false;
				
				if (preg_match('/^(\S+)__I-(\d+)$/', $which_shipping_method, $imatches)) {
					$which_shipping_method = $imatches[1];
					$which_instance_id = $imatches[2];
				}

				$ind = ('default' == $full_shipping_method) ? $matches_now[3] : 'shipmethod-'.$full_shipping_method.'-'.$matches_now[3];
				// Only print something if all the expected parts are found
				if (isset($settings["openinghours-".$ind."-hour"]) && isset($settings["openinghours-".$ind."-minute"]) && isset($settings["openinghours-".$ind."-close-hour"]) && isset($settings["openinghours-".$ind."-close-minute"])) {
					if (!isset($add_js[$val])) $add_js[$val] = '';
					$add_js[$val] .= "openinghours_addrule($val, ".$settings["openinghours-$ind-hour"].", ".$settings["openinghours-$ind-minute"].", ".$settings["openinghours-$ind-close-hour"].", ".$settings["openinghours-$ind-close-minute"].", '".esc_js($full_shipping_method)."');\n";
					
					// This section has one purpose: when somebody is newly upgraded to WC 2.6 and has set up shipping zones, but not yet set settings for them, it renders the settings from the non-zonal settings. This is intended to be done only until they saved, which a dashboard message requests them to do immediately.
					if (!empty($wc26_but_not_yet_saved) && empty($which_instance_id) && !empty($shipping_methods_and_zones[$which_shipping_method]) && !empty($shipping_methods_and_zones[$which_shipping_method]['zones'])) {

						foreach ($shipping_methods_and_zones[$which_shipping_method]['zones'] as $zone_id => $instance_ids) {
						
							foreach ($instance_ids as $instance_id) {
								$shipping_method_prefix = $which_shipping_method.'__I-'.$instance_id;
							
								$add_js[$val] .= "openinghours_addrule($val, ".$settings["openinghours-$ind-hour"].", ".$settings["openinghours-$ind-minute"].", ".$settings["openinghours-$ind-close-hour"].", ".$settings["openinghours-$ind-close-minute"].", '".esc_js($shipping_method_prefix)."');\n";
							}
						}
					}
				}
			} elseif (!isset($this->use_these_times) && preg_match('/^holiday-(\d+)-date$/', $key)) {
				$repeat = empty($settings[$key."-repeat"]) ? '0' : '1';
				if (!isset($add_js[10])) $add_js[10] = '';
				// Remove -date
				$shipping_method_key = substr($key, 0, strlen($key)-5).'-shipping_method';
				$shipping_id = empty($settings[$shipping_method_key]) ? 'all' : $settings[$shipping_method_key];
				$add_js[10] .= "openinghours_addholiday('".$val."', $repeat, '".$shipping_id."');\n";
			}
		}
		if (count($add_js)) {
			echo "<script>jQuery(document).ready(function() {\n";
			
			// Outputs the variable openinghours_shipping_method_labels
			$this->output_openinghours_shipping_method_labels_js(true);
			
			$shipping_methods_and_zones = $this->get_shipping_methods_and_zones();
			
			echo "openinghours_shipping_ids = [";
			$first_shipping_id = true;
			
			foreach ($shipping_methods_and_zones as $method_id => $method) {
				if (is_array($method['zones'])) {
					foreach ($method['zones'] as $zone_id => $instance_ids) {
						foreach ($instance_ids as $instance_id) {
							if ($first_shipping_id) {
								$first_shipping_id = false;
							} else {
								echo ', ';
							}
							echo "'".$method_id."__I-".$instance_id."'";
						}
					}
				}
			}
			
			echo "];\n";
			
			ksort($add_js);
			foreach ($add_js as $add) echo $add;
			echo "});\n</script>";
		}

	}

	// WC 2.6 notes that get_woocommerce_term_meta and update_woocommerce_term_meta will be deprecated in future; so, we've funnelled all ours through this single point, to be ready for that.
	private function get_woocommerce_term_meta($term_id, $key, $single = true) {
		return get_woocommerce_term_meta($term_id, $key, $single);
	}
	
	private function update_woocommerce_term_meta( $term_id, $meta_key, $meta_value, $prev_value = '' ) {
		return update_woocommerce_term_meta($term_id, $meta_key, $meta_value, $prev_value);
	}
	
	# Adds the settings link under the plugin on the plugin screen.
	public function plugin_action_links($links, $file) {
		if ('woocommerce-opening-hours/opening-hours.php' == $file && function_exists('WC')) {
			$woocommerce = WC();
			if (is_a($woocommerce, 'WooCommerce')) {
				$settings_link = '<a href="'.$this->our_page().'">'.__("Settings", "openinghours").'</a>';
				array_unshift($links, $settings_link);
			}
		}
		return $links;
	}

}
