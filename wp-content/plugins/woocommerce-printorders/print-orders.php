<?php
/*
Plugin Name: WooCommerce Print Orders
Plugin URI: https://www.simbahosting.co.uk/s3/shop/
Description: A plugin to automatically print completed orders via Google Cloud Print.
Author: David Anderson
Version: 2.7.15
License: MIT and GPLv2+
Text Domain: woocommerce-printorders
Domain Path: /languages
Author URI: https://david.dw-perspective.org.uk
WC requires at least: 3.0.0
WC tested up to: 3.5.0
// N.B. WooCommerce doesn't check the minor version. So, '3.5.0' means 'the entire 3.5 series'
*/

if (!defined('ABSPATH')) die('No direct access allowed');

global $googlecloudprintlibrary_plugin;

if (!class_exists('GoogleCloudPrintLibrary_Plugin')) require_once(dirname(__FILE__).'/gcpl/google-cloud-print-library.php');
if (!isset($googlecloudprintlibrary_plugin) || !is_a($googlecloudprintlibrary_plugin, 'GoogleCloudPrintLibrary_Plugin'))
$googlecloudprintlibrary_plugin = new GoogleCloudPrintLibrary_Plugin($googlecloudprintlibrary_gcpl);

$woocommerce_ext_printorders = new WooCommerce_Ext_PrintOrders;

class WooCommerce_Ext_PrintOrders {

	private $option_name = 'woocommerce_printorders_options';
	private $log = array();
	private $debug_logs_emailed = false;
	private $order_id = false;
	private $already_printed = array();
	private $wc_logger;
	private $wc_logger_id = 'woocommerce_printorders';
	private $wc_compat;

	/**
	 * Plugin constructor, run upon plugin load
	 */
	public function __construct() {
		// Order processed happens for *all* orders/payment methods
		add_action('woocommerce_checkout_order_processed', array($this, 'woocommerce_checkout_order_processed'));
		// Payment complete happens only for some payment methods - ones that can be carried out over the Internet. For those, we don't want to print the order until they are completed. But when payment is complete, we always want to print.
		add_action('woocommerce_payment_complete', array($this, 'woocommerce_payment_complete'));
		add_filter('woocommerce_general_settings', array($this, 'woocommerce_general_settings'));

		add_action('plugins_loaded', array($this, 'load_translations'));

		add_action('admin_init', array($this, 'admin_init'));

		add_action('init', array($this, 'wp_init'));
		
		add_action('woocommerce_admin_field_printorders', array($this, 'woocommerce_admin_field_printorders'));

		add_filter('plugin_action_links', array($this, 'plugin_action_links'), 10, 2);
	
		add_filter('wpo_wcpdf_billing_address', array($this, 'wpo_wcpdf_billing_address'));

		add_action('add_meta_boxes_shop_order', array($this, 'add_meta_boxes_shop_order'));

		add_action('wp_ajax_wc_googlecloudprint', array($this, 'ajax'));

		/*
		if (!class_exists('WooCommerce_Compat_0_2')) require_once(__DIR__.'/vendor/davidanderson684/woocommerce-compat/woocommerce-compat.php');
		$this->wc_compat = new WooCommerce_Compat_0_2();
		*/
		
		add_action('plugins_loaded', array($this, 'load_updater'), 0);

		add_action('woocommerce_print_order_go', array($this, 'woocommerce_print_order_go'));
		
		add_action('woocommerce_order_status_changed', array($this, 'woocommerce_order_status_changed'), 10, 3);
		
		// Add entries to the 'order action' menu on an order's post page
		add_filter('woocommerce_order_actions', array($this, 'woocommerce_order_actions'));
		
		// Automate Woo integration
		add_action( 'automatewoo/background_process/before_handle', array($this, 'register_woocommerce_order_actions'));
	}

	public function load_updater() {
		if (file_exists(dirname(__FILE__).'/wpo_update.php')) {
			require(dirname(__FILE__).'/wpo_update.php');
		} elseif (file_exists(dirname(__FILE__).'/updater.php')) {
			require(dirname(__FILE__).'/updater.php');
		}
	}

	public function woocommerce_order_status_changed($order_id, $old_status, $new_status) {
		// Allow the user to easily print on an order status change
		if (apply_filters('woocommerce_print_orders_order_status_changed_fire_print', false, $order_id, $old_status, $new_status)) {
			$this->print_order_or_schedule($order_id, 'order_status_changed_'.$old_status.'_'.$new_status);
		}
	}
	
	public function woocommerce_order_action_google_cloud_print($order) {
	
		$current_action = current_filter();
		
		$printer_id = substr($current_action, strlen('woocommerce_order_action_google-cloud-print-'));
		
		if (preg_match('/(\S+)___(\S+)$/', $printer_id, $matches)) {
			$printer_id = $matches[1];
			$type_key = $matches[2];
		}
		
		$order_id = is_callable(array($order, 'get_id')) ? $order->get_id() : $order->id;

		$this->print_go($order_id, 'woocommerce_order_action_google-cloud', $printer_id, $type_key);
	
	}
	
	/**
	 * WordPress filter woocommerce_order_actions, for adding items to the order action menu
	 *
	 * @param Array $actions - current actions
	 *
	 * @return Array - filtered actions
	 */
	public function woocommerce_order_actions($actions) {
	
		$gcpl = new GoogleCloudPrintLibrary_GCPL_v2();
	
		$printers = $gcpl->get_printers();

		if (is_wp_error($printers) || 0 == count($printers)) return $actions;

		$types = $this->get_types();

		foreach ($printers as $printer) {
		
			foreach ($types as $key => $type) {
			
				list($enabled, $enabled_msg) = $this->is_type_enabled($key, $type);
				
				if (!$enabled) continue;
				
				$actions['google-cloud-print-'.$printer->id.'___'.$key] = sprintf(__('Print %s via %s', 'woocommerce-printorders'), $type['description_short'], $printer->displayName);
			
			}
		
		}
		
		return $actions;
	
	}
	
	public function add_meta_boxes_shop_order() {
		add_meta_box('wc_cloudprint_print_button',
			__('Google Cloud Print', 'woocommerce-printorders'),
			array($this, 'meta_box_shop_order'),
			'shop_order',
			'side',
			'default'
		);
	}

	// This relies on WP's cron system working on the website, of course
	private function schedule_print($order_id) {
		wp_schedule_single_event(time(), 'woocommerce_print_order_go', array($order_id));
	}

	public function ajax() {

		if (!isset($_REQUEST['_wpnonce']) || !wp_verify_nonce($_REQUEST['_wpnonce'], 'wc-googlecloudprint-nonce')) die;

		if (isset($_REQUEST['subaction']) && 'print-internal' == $_REQUEST['subaction']) {

			if (empty($_REQUEST['order_id'])) die;
			$order_id = $_REQUEST['order_id'];

			$order = wc_get_order($order_id);

			$order_items = apply_filters('woocommerce_printorders_print_order_items', $order->get_items(), $order, array(), array());
			
			$output = $this->getdocument_internal($order, $order_id, $order_items);
			
			if (is_array($output)) {
				if (isset($output['data-raw'])) {
					header('Content-type: '.$output['mime-type']);
					echo $output['data-raw'];
				}
			} else {
				echo $output;
			}
		} elseif (isset($_REQUEST['subaction']) && 'download_log' == $_REQUEST['subaction']) {

			if (class_exists('WC_Log_Handler_File')) {
		
				$log_path = WC_Log_Handler_File::get_log_file_path($this->wc_logger_id);
			
				header("Content-Length: ".filesize($log_path));
				header("Content-type: text/plain");
				header("Content-Disposition: attachment; filename=\"".basename($log_path)."\";");
				readfile($log_path);
			
			} else {
			
				echo "Class WC_Log_Handler_File not found. Download requires WooCommerce 3.0 or later. On earlier versions, you can use FTP to download the log file from wp-content/uploads/wc-logs.";
			
			}
	
		} else {

			if (empty($_REQUEST['order_id'])) die;
			$order_id = $_REQUEST['order_id'];

			$this->initialise_log();
			set_error_handler(array($this, 'get_php_errors'), E_ALL & ~E_STRICT);
			$this->log("Manual print via order page: order_id=$order_id backtrace=".wp_debug_backtrace_summary());
			$this->woocommerce_process_order($order_id);
			$this->email_debug_logs();
			restore_error_handler();

			echo '<p>'.__('Request sent (if you configured a debugging email address, then more feedback will be sent to it).', 'woocommerce-printorders').'</p>';

			if (defined('WOOCOMMERCE_PRINTORDER_RETURNFEEDBACK') && WOOCOMMERCE_PRINTORDER_RETURNFEEDBACK) {
				$results = '<pre>';
				foreach ($this->log as $logline) {
					$results .= '['.$logline['level'].'] '.htmlspecialchars($logline['message'])."<br>";
				}
				$results .= '</pre>';
				echo $results;
			}
		}

		exit;

	}

	public function meta_box_shop_order() {
		$gcp_options = apply_filters('google_cloud_print_options', array());

		if (empty($gcp_options['printer'])) {
			echo '<p><a href="'.admin_url('options-general.php?page=google_cloud_print_library').'">'.htmlspecialchars(__('Before anything can be printed, you must configure your Google Cloud Print settings - follow this link to do so.', 'woocommerce_printorders')).'</a></p>';
		} else {
			echo "<button class=\"woocommerce_printorders_print\" onclick=\"return false;\">".__('Print Order', 'woocommerce-printorders')."</button>";

			echo "<button class=\"woocommerce_printorders_printinternal\" onclick=\"return false;\">".__('Debug internal format output', 'woocommerce-printorders')."</button>";

			echo '<div id="woocommerce_printorders_results"></div>';
		}

		add_action('admin_footer', array($this, 'shop_order_footer'));
	}

	public function shop_order_footer() {
		global $post;
		$order_id = $post->ID;
		?>
		<script>
			jQuery(document).ready(function($) {
				$('.woocommerce_printorders_print').click(function() {
					$('#woocommerce_printorders_results').html('<p><em><?php echo esc_js(__('Requesting...', 'woocommerce-printorders'));?></em></p>');
					$.post(ajaxurl, {
						action: 'wc_googlecloudprint',
						_wpnonce: '<?php echo wp_create_nonce("wc-googlecloudprint-nonce");?>',
						order_id: <?php echo $order_id;?>
					}, function(response) {
						$('#woocommerce_printorders_results').html(response);
					});
				});
				$('.woocommerce_printorders_printinternal').click(function() {
					window.location.href = ajaxurl+'?action=wc_googlecloudprint&subaction=print-internal&_wpnonce=<?php echo wp_create_nonce("wc-googlecloudprint-nonce"); ?>&order_id=<?php echo $order_id;?>';
				});
				// Move the actions into their own optgroup, for better formatting
				if ($('#actions').length > 0) {
					var $items = $('#actions option[value^="google-cloud-print-"');
					if ($items.length > 0) {
						$items.first().before('<optgroup id="google-cloud-print-optgroup" label="<?php echo esc_js(__('Google Cloud Print', 'woocommerce-printorders'));?>"></optgroup>');
						$items.appendTo('#google-cloud-print-optgroup');
					}
				}
			});
		</script>
		<?php
	}

	public function wpo_wcpdf_billing_address($address) {
	
		// Version 2.0 changes the main class name - so, if this class exists, then we know we're on >= 2.0
		if (class_exists('WPO_WCPDF')) return $address;
	
		global $wpo_wcpdf;
		
		if (!is_a($wpo_wcpdf, 'WooCommerce_PDF_Invoices')) return $address;
		$version = $wpo_wcpdf::$version;

		// Since 1.5.7, the PDF plugin added its own option to control adding phone/email - so, leave that up to them
		if ($version && version_compare($version, '1.5.7', '>=')) return $address;

		$order = $wpo_wcpdf->export->order;
		if (!is_a($order, 'WC_Order')) return $address;
		$email = $order->billing_email;
		$phone = $order->billing_phone;
		if (!empty($email) && false === strpos($address, $email)) $address .= "<br/>$email";
		if (!empty($phone) && false === strpos($address, $phone)) $address .= "<br/>$phone";
		return $address;
	}

	private function log($message, $level = 'notice') {
		if (is_wp_error($message)) {
			foreach ($message->get_error_messages() as $msg) {
				$this->log[] = array('level' => $level, 'message' => "Error message: $msg");
				if (!empty($this->wc_logger)) {
					$this->wc_logger->add($this->wc_logger_id, "[$level] $msg");
				}
			}
			$codes = $message->get_error_codes();
			if (is_array($codes)) {
				foreach ($codes as $code) {
					$data = $message->get_error_data($code);
					if (!empty($data)) {
						$ll = (is_string($data)) ? $data : serialize($data);
						$this->log[] = array('level' => $level, 'message' => "Error data (".$code."): ".$ll);
					}
				}
			}
		} else {
			$this->log[] = array('level' => $level, 'message' => $message);
			if (!empty($this->wc_logger)) {
				$this->wc_logger->add($this->wc_logger_id, "[$level] $message");
			}
		}
		
		if (defined('WP_DEBUG') && WP_DEBUG && is_string($message)) error_log("$level: $message");
	}

	public function load_translations() {
		load_plugin_textdomain('woocommerce-printorders', false, basename(dirname(__FILE__)).'/languages/');
	}
	
	public function wp_init() {
		if (defined('DOING_CRON') && DOING_CRON) $this->register_woocommerce_order_actions();
	}
	
	public function register_woocommerce_order_actions() {

		static $registered = false;
		if ($registered) return;
		$registered = true;
	
		$gcpl = new GoogleCloudPrintLibrary_GCPL_v2();
	
		$printers = $gcpl->get_printers(true);

		if (is_wp_error($printers) || !is_array($printers)) return;

		$types = $this->get_types();
		
		foreach ($printers as $printer) {
		
			foreach ($types as $key => $type) {
			
				list($enabled, $enabled_msg) = $this->is_type_enabled($key, $type);
				
				if (!$enabled) continue;
		
				$action_name = 'woocommerce_order_action_google-cloud-print-'.$printer->id.'___'.$key;
		
				add_action($action_name, array($this, 'woocommerce_order_action_google_cloud_print'));
				
			}
		
		}
		
	}

	/**
	 * Called upon the WordPress action admin_init
	 */
	public function admin_init() {

		if (!current_user_can('manage_options')) return;

		$this->register_woocommerce_order_actions();
		
		global $pagenow;

		if ('admin.php' == $pagenow && !empty($_REQUEST['page']) && 'wc-settings' == $_REQUEST['page'] && !empty($_POST['printorders_settingsform'])) {
			# All options are re-built here
			$opts = array();
			foreach ($_POST as $key => $val) {
				if (0 === strpos($key, 'printorders_') && 'printorders_settingsform' != $key) {
					$nkey = substr($key, 12);
					$opts[$nkey] = $val;
				}
			}
			$this->set_opts($opts);
		}
	}
	
	private function version_info() {
		$version = '??';
		if ($fp = fopen(__FILE__, 'r')) {
			$file_data = fread($fp, 1024);
			if (preg_match("/Version: ([\d\.]+)(\r|\n)/", $file_data, $matches)) {
				$version = $matches[1];
			}
			fclose($fp);
		}

		@include(ABSPATH.'wp-includes/version.php');

		global $woocommerce;
		$wc_version = $woocommerce->version;

		if (function_exists('gd_info')) {
			$gd_info = gd_info();
			$gd_version = $gd_info['GD Version'];
		} else {
			$gd_version = 'none';
		}

		$dom_installed = extension_loaded('dom') ? 'Y' : 'N';
		
		return "WooCommerce Cloud Print $version, Google Cloud Print Library ".GOOGLECLOUDPRINTLIBRARY_VERSION.' / '.GOOGLECLOUDPRINTLIBRARY_PLUGINVERSION.", GD $gd_version, Dom Extension: $dom_installed, WooCommerce $wc_version, WordPress $wp_version, PHP ".PHP_VERSION." ".date('Y-m-d H:i:s');
	}

	private function email_debug_logs() {

		if (!empty($this->debug_logs_emailed)) return;

		$opts = $this->get_opts();
		$debug_email = empty($opts['debug_email']) ? '' : $opts['debug_email'];
		if (empty($debug_email) || !is_string($debug_email)) return;

		$email_body = '<pre>';

		array_unshift($this->log, array('level' => 'info', 'message' => $this->version_info()));
		array_unshift($this->log, array('level' => 'info', 'message' => 'Running on: '.home_url().' / '.site_url()));

		foreach ($this->log as $logline) {
			$email_body .= '['.$logline['level'].'] '.htmlspecialchars($logline['message'])."<br>";
		}
		$email_body .= '</pre>';

		$email_body = apply_filters('woocommerce_print_orders_debug_email_body', $email_body, $this->log);

		$subject = apply_filters('woocommerce_print_orders_debug_email_title', sprintf(__('WooCommerce Print Orders - Order #%s Debugging Log', 'woocommerce-printorders'), $this->order_id), $this->order_id);

		foreach (explode(',', $debug_email) as $email) {
			$email = trim($email);
			if (strpos($email, '@') !== false) {
				wc_mail( $email, $subject, $email_body, "Content-Type: text/html\r\n");
			}
		}

		$this->debug_logs_emailed = true;
	}

	private function set_opts($opts) {
		return update_option($this->option_name, $opts);
	}

	private function get_opts() {
		$o = get_option($this->option_name, array());
		return is_array($o) ? $o : array();
	}

	// Some code here from https://wordpress.org/plugins/woocommerce-pdf-invoices-packing-slips/ under the GPL v2+#
	// $template_type is 'invoice' or 'packing-slip'
	private function _pips_generate_output($order_id, $template_type) {

		if (!class_exists('WooCommerce_PDF_Invoices') && (!function_exists('WPO_WCPDF') || !function_exists('wcpdf_get_document'))) return $this->not_found_message('class WooCommerce_PDF_Invoices/functions WPO_WCPDF+wcpdf_get_document', 'WooCommerce PDF Invoices and Packing Slips');

		$pips_is_pre_20 = false;
		
		// N.B. On 2.0 the *function* ceases to exist (but the *class* still exists)
		if (class_exists('WPO_WCPDF')) {
			$wpo_wcpdf = WPO_WCPDF();
		} else {
			global $wpo_wcpdf;
			$our_wcpdf = (is_object($wpo_wcpdf) && is_a($wpo_wcpdf, 'WooCommerce_PDF_Invoices')) ? $wpo_wcpdf : new WooCommerce_PDF_Invoices();
			$pips_is_pre_20 = true;
		}

		$order_ids = array($order_id);

		if ($pips_is_pre_20) {
			$export = $our_wcpdf->export;
		} else {
			// This gets you the object that now (in PIPS 2.0+) has the get_pdf() method
			// 3rd parameter: $init=true - without this, the invoice number may be missing
			$export = wcpdf_get_document($template_type, $order_ids, true);
		}

		$add_top_padding = apply_filters('woocommerce_printorders_wcpdf_add_body_padding', true);

		if ($add_top_padding) add_action('wpo_wcpdf_custom_styles', array($this, 'wpo_wcpdf_custom_styles'));

		// N.B. It's only on 2.0+ that they added a second parameter. But sending it on earlier versions is no problem.
		do_action('wpo_wcpdf_before_pdf', $template_type, $export);

// 		if (apply_filters('wpo_wcpdf_output_html', false, $template_type)) {
// 			// Output html to browser for debug
// 			// NOTE! images will be loaded with the server path by default
// 			// use the wpo_wcpdf_use_path filter (return false) to change this to http urls
// 			die($this->process_template( $template_type, $order_ids ));
// 		}
	
		if ( $pips_is_pre_20 && !($invoice = $export->get_pdf( $template_type, $order_ids )) ) {
			$this->log("PIPS (Legacy) Exporter: Failed to get_pdf()", 'error');
			return false;
		}
		
		if ( !$pips_is_pre_20 && !($invoice = $export->get_pdf()) ) {
			$this->log("PIPS Exporter: Failed to get_pdf()", 'error');
			return false;
		}

		if ($add_top_padding) remove_action('wpo_wcpdf_custom_styles', array($this, 'wpo_wcpdf_custom_styles'));

		do_action( 'wpo_wcpdf_after_pdf', $template_type );

		$filename = $pips_is_pre_20 ? $export->build_filename( $template_type, $order_ids, 'download' ) : $export->get_filename();

		$this->log("Size of PDF returned: ".strlen($invoice)." bytes");

		return array('pdf-raw' => $invoice);
		
	}

	public function wpo_wcpdf_custom_styles() {
		echo "body { padding-top: 56px; }";
	}
	
	/**
	 * Generate the output for the WooCommerce (SkyVerge) PIPS plugin
	 *
	 * @param Integer $order_id - the WooCommerce order ID
	 * @param String $template_type - 'invoice' or 'packing-list' (N.B. pick-list also supported by the plugin).
	 *
	 * @return
	 */
	public function _woocommercecom_pips_generate_output($order_id, $template_type) {
	
		if (!function_exists('wc_pip')) return $this->not_found_message('function wc_pip', 'WooCommerce.Com Print Invoices and Packing Slips');
	
		$order = wc_get_order($order_id);
	
		add_filter('wc_pip_show_print_dialog', array($this, 'return_false'));
	
		$document = wc_pip()->get_document($template_type, array('order' => $order));
		
		ob_start();
		$document->output_template(array('action' => 'wc_pip_document'));
		$print_contents = ob_get_clean();

		remove_filter('wc_pip_show_print_dialog', array($this, 'return_false'));

		return $print_contents;
	
	}
	
	public function return_false() {
		return false;
	}
	
	private function getdocument_yith_pdf_premium($order, $order_id, $order_items) {

		if (!function_exists('YITH_PDF_Invoice')) return $this->not_found_message('function YITH_PDF_Invoice', 'YITH WooCommerce PDF Invoice and Shipping List Premium');
		
		$pdf_invoice = YITH_PDF_Invoice();
		
		$invoice = new YITH_Invoice( $order_id );
		
		$valid = $invoice->is_valid();
		if (!$invoice->generated()) {
			$pdf_invoice->create_document($order_id, 'invoice');
			$invoice = new YITH_Invoice( $order_id );
		}

		$attachment = $pdf_invoice->get_invoice_attachment('customer_invoice', $order_id);
		
		if (empty($attachment)) return $this->not_found_message('PDF invoice file for this order', 'YITH WooCommerce PDF Invoice and Shipping List Premium');
		
		$invoice_raw = file_get_contents($attachment);
		
		$this->log("Size of PDF returned: ".strlen($invoice_raw)." bytes");

		return array('pdf-raw' => $invoice_raw);

	
	}

	private function getdocument_woocommerce_pips_packing_slip($order, $order_id, $order_items) {
		return $this->_pips_generate_output($order_id, 'packing-slip');
	}

	private function getdocument_woocommerce_pips_invoice($order, $order_id, $order_items) {
		return $this->_pips_generate_output($order_id, 'invoice');
	}

	private function getdocument_woocommercecom_pips_invoice($order, $order_id, $order_items) {
		return $this->_woocommercecom_pips_generate_output($order_id, 'invoice');
	}
	
	private function getdocument_woocommercecom_pips_packing_slip($order, $order_id, $order_items) {
		return $this->_woocommercecom_pips_generate_output($order_id, 'packing-list');
	}
	
	private function getdocument_internal($order, $order_id, $order_items, $opts = false) {

		if (false === $opts) $opts = $this->get_opts();
		
		$format = (is_array($opts) && !empty($opts['print_format']) && $opts['print_format'] == 'text/plain') ? 'text/plain' : 'text/html';
		
		$template_basename = ('text/plain' == $format) ? 'cloud-print-plain-text.php' : 'cloud-print.php';
	
		# By default, the template is sought in your child theme, in your theme, and then in this plugin. Can be over-ridden using a filter.
		$template_locations = apply_filters('woocommerce_printorders_printtemplate', array(
			get_stylesheet_directory().'/templates/'.$template_basename,
			get_template_directory().'/templates/'.$template_basename,
			dirname(__FILE__).'/templates/'.$template_basename,
		), $format);

		if (is_string($template_locations)) $template_locations = array($template_locations);

		foreach ($template_locations as $locat) {
			if (file_exists($locat) && is_readable($locat)) {
				$template = $locat;
				break;
			}
		}

		if (!isset($template)) {
			$this->log("No readable template file found. Tried: ".serialize($template_locations));
			return false;
		}

		$this->log("Using template: $template");

		ob_start();
		require($template);
		$printout = ob_get_clean();
		
		if ('text/plain' == $format) {
			$dom_document = new DOMDocument;
			$mock = new DOMDocument;
			$dom_document->loadHTML('<?xml encoding="UTF-8">'.$printout);
			$body = $dom_document->getElementsByTagName('body')->item(0);
			if (!empty($body->childNodes)) {
				foreach ($body->childNodes as $child){
					$mock->appendChild($mock->importNode($child, true));
				}
				$printout = html_entity_decode(strip_tags($mock->saveHTML()), null, 'UTF-8');
			} else {
				$printout = strip_tags($printout);
			}
		}
		
		$this->log("Template returned ".strlen($printout)." bytes");
		
		if ('text/plain' == $format) {
			$document = array(
				'data-raw' => $printout,
				'mime-type' => 'text/plain'
			);
		} else {
			$document = $printout;
		}
		
		return apply_filters('woocommerce_printorders_internaldocument', $document, $template, $format);

	}

	private function not_found_message($missing_entity, $plugin) {
		return "Malfunction. Expected $missing_entity not found. Check that the relevant plugin is installed and active. If reporting an error, please indicate the version numbers of WordPress, WooCommerce, WooCommerce Print Orders, and $plugin";
	}

	/**
	 * Get the output from "WooCommerce Print Invoice & Delivery Note"
	 *
	 * @param Integer $order_id - WooCommerce order ID
	 * @param String  $template_type - template type; we use 'invoice' and 'delivery-note'; 'receipt' and 'order-note' are also used by the plugin
	 *
	 * @return String - HTML
	 */
	private function _woocommerce_delivery_notes_output($order_id, $template_type) {
	
		if (!class_exists('WooCommerce_Delivery_Notes')) return $this->not_found_message('class WooCommerce_Delivery_Notes', 'WooCommerce Delivery Notes');
		
		if (function_exists('WCDN')) {
			$wcdn = WCDN();
		} else {
			global $wcdn;
		}
		
		$our_wcdn = (is_object($wcdn) && is_a($wcdn, 'WooCommerce_Delivery_Notes')) ? $wcdn : new WooCommerce_Delivery_Notes();
		if (empty($our_wcdn->print)) return $this->not_found_message('WooCommerce_Delivery_Notes->print object', 'WooCommerce Delivery Notes');
		
		// We'd prefer to just call generate_template() - but unfortunately, it calls die()
		// (So, that method is where we get this stuff from - still there as at 4.3.4)
		$our_wcdn->print->order_ids = array($order_id);
		
		$our_wcdn->print->template = $template_type; // Valid: receipt, order, delivery-note, invoice
		$our_wcdn->print->order_email = null;

		// This is now a long-winded way to call wc_get_order() to get the WC_Order object
		$populated = $this->_woocommerce_delivery_notes_populate_orders(array($order_id));

		if (empty($populated)) {
			$this->log("WCDN: failed to populate orders");
			return false;
		} 

		$our_wcdn->print->orders = $populated;

		$this->our_wcdn = $our_wcdn;

		// The default action adds a CSS link via HTTP
		remove_action( 'wcdn_head', 'wcdn_template_stylesheet' );
		add_action('wcdn_head', array($this, '_wcdn_head'));
		
		// Seems to be from version 4.3.2 onwards
		if (is_callable(array($our_wcdn->print, 'get_template_file_location'))) {
			$template_path = $our_wcdn->print->get_template_file_location('print-order.php');
			$default_path = $template_path;
		} else {
			// Old-style. The template_path_theme property was removed.
			$template_path = $our_wcdn->print->template_path_theme;
			$default_path = $our_wcdn->print->template_path_plugin;
		}

		ob_start();
		
		wc_get_template('print-order.php', null, $template_path, $default_path);
		
		$output = ob_get_clean();

		return $output;
	}

	public function _wcdn_head() {
		$name = apply_filters('wcdn_template_stylesheet_name', 'style.css');
		// The extra rule for .order-addresses is needed to help DOMPDF not mess the layout
		?>
		<style type="text/css">
			#navigation { display: none; }
			@page { margin: 50px 28px; }
			.order-addresses { height: 120px; }
			<?php readfile($this->_wcdn_get_template_path( $name )); ?>
		</style>
		<?php
	}

	/**
	 * Return a path to a CSS stylesheet
	 *
	 * @param String $name - basename for the CSS file
	 *
	 * @return String - the full path
	 */
	private function _wcdn_get_template_path( $name ) {

		// New-style
		if (is_callable(array($this->our_wcdn->print, 'get_template_file_location'))) {
			$template_url_path = $this->our_wcdn->print->get_template_file_location('print-order.php', true);
			return trailingslashit($template_url_path).$name;
		}
		
		// Old-style - I think it's before 4.3.2
		$template_path = $this->our_wcdn->print->template_path_theme;
		$template_url_path = $this->our_wcdn->print->template_url_plugin;
	
		$child_theme_path = get_stylesheet_directory().'/'.$template_path;
		$theme_path = get_template_directory().'/'.$template_path;
		
		// Build the url depending upon where the file is
		if( file_exists( $child_theme_path . $name ) ) {
			return $child_theme_path . $name;
		} elseif( file_exists( $theme_path . $name ) ) {
			return $theme_path . $name;
		}

		return $template_url_path.$name;
	}

	/**
	 * This function is based upon WooCommerce_Delivery_Notes_Print::populate_orders(), which we cannot call directly because it is private. We have re-written it somewhat because it uses various old/deprecated WC practices.
	 *
	 * The result could now pretty much be replaced with wc_get_orders(), though there's no need to fix what isn't broken.
	 *
	 * @param Array $order_ids - list of WooCommerce orders
	 *
	 * @return Array - list of WC_Order objects
	 */
	private function _woocommerce_delivery_notes_populate_orders($order_ids) {
			
		$orders = array();
		
		// Check permissons of the user to determine 
		// if the orders should be populated.
		foreach( $order_ids as $order_id ) {
			
			try {
				$order = wc_get_order($order_id);
			} catch (Exception $e) {
				error_log('_woocommerce_delivery_notes_populate_orders: '.$e->getMessage());
				return false;
			}
			
			if (!$order) return false;

			// Logged in users			
			if( is_user_logged_in() && ( !current_user_can( 'edit_shop_orders' ) && !current_user_can( 'view_order', $order_id ) && !current_user_can('cloudprint_shop_orders') ) ) {
				return false;
			} 

			// An email is required for not logged in-users  
// 				if( !is_user_logged_in() && ( empty( $this->order_email ) || strtolower( $order->billing_email ) != $this->order_email ) ) {
// 					$orders = null;
// 					return false;
// 				}
			
			// Save the order to get it without an additional database call
			$orders[$order_id] = $order;
		}
		
		return $orders;
	}

	private function getdocument_woocommerce_delivery_notes_invoice($order, $order_id, $order_items) {
		return $this->_woocommerce_delivery_notes_output($order_id, 'invoice');
	}

	private function getdocument_woocommerce_delivery_notes_deliverynote($order, $order_id, $order_items) {
		return $this->_woocommerce_delivery_notes_output($order_id, 'delivery-note');
	}

	private function extraconfig_internal($opts) {

		// The variables were called "paper_width"; but, it would have been cleared to call them pdf_width, as this is not a printer setting.
		$width = (is_array($opts) && !empty($opts['internal_paper_width'])) ? $opts['internal_paper_width'] : 216.90;
		$height = (is_array($opts) && !empty($opts['internal_paper_height'])) ? $opts['internal_paper_height'] : 279.40;
		
		$print_format = (is_array($opts) && !empty($opts['print_format']) && $opts['print_format'] == 'text/plain') ? 'text/plain' : 'text/html';

//			1mm = 2.83464567 points
//		 	1mm = 1000 microns
		?>
		
		<div style="border:1px dotted; padding: 2px 6px; margin: 8px 0 0 14px;">
		
			<p>
			
				<input id="printorders_print_format_plain" type="radio" <?php if ('text/plain' == $print_format) echo ' checked="checked"';?> name="printorders_print_format" value="text/plain"> <label for="printorders_print_format_plain"><?php _e('Send as plain text only (no formatting, and thus the page size is controlled only by your printer)', 'woocommerce-printorders'); ?></label><br>
			
			
				<input id="printorders_print_format_html" type="radio" <?php if ('text/html' == $print_format) echo ' checked="checked"';?> name="printorders_print_format" value="text/html"> <label for="printorders_print_format_html"><?php _e('Generate a PDF file (from HTML)', 'woocommerce-printorders'); ?></label>
			
			</p>

			<div style="margin: 4px 0 0 24px;" id="printorders_print_format_html_settings">

				<em><?php _e('If setting your own PDF page size with a printer that prints continuously (i.e. onto unbroken rolls of paper), then choosing a good height is a trade-off between how high long you want the minimum print-out to be, and having more frequent page-breaks (though, you may find your printer driver will allow you to remove margins on page-breaks - but this plugin cannot control that).', 'woocommerce-printorders');?><br>

				<?php _e('N.B. 1mm = 0.393700787 inches; 1 inch = 2.54 cm', 'woocommerce-printorders'); ?></em></p>
				
				<?php _e('Page width:', 'woocommerce-printorders'); ?> <input id="printorders_internal_paper_width" type="text" name="printorders_internal_paper_width" size="6" value="<?php echo sprintf('%0.2f', $width); ?>"> <?php _e('mm', 'woocommerce-printorders'); ?>.
				<?php _e('Height:', 'woocommerce-printorders'); ?> <input id="printorders_internal_paper_height" type="text" name="printorders_internal_paper_height" size="6" value="<?php echo sprintf('%0.2f', $height); ?>"> <?php _e('mm', 'woocommerce-printorders'); ?>.

				<br><?php _e('Use this to enter above the values for a common paper size:', 'woocommerce-printorders'); ?>

				<select id="printorders_internal_paper_size">
					<?php
		// 				$size_selected = (is_array($opts) && !empty($opts['internal_paper_size'])) ? $opts['internal_paper_size'] : 'letter';
						$size_selected = 'letter';
						$paper_sizes = $this->get_paper_sizes();
						foreach ($paper_sizes as $key => $sizeinfo) {
							echo '<option value="'.esc_attr(json_encode($sizeinfo)).'"';
							if ($key == $size_selected) echo ' selected="selected"';
							echo '>'.htmlspecialchars($key)."</option>\n";
						}
					?>
				</select>
				<select id="printorders_internal_paper_orientation">
					<?php
						$orientation = 'portait';
						$paper_orientations = array(
							'portrait' => __('Portait', 'woocommerce-printorders'),
							'landscape' => __('Landscape', 'woocommerce-printorders')
						);
						foreach ($paper_orientations as $key => $description) {
							echo '<option value="'.esc_attr($key).'"';
							if ($key == $size_selected) echo ' selected="selected"';
							echo '>'.htmlspecialchars($description)."</option>\n";
						}
					?>
				</select>

				<button id="printorders_internal_paper_size_choose" class="button button-primary"><?php _e('Enter values (above)', 'woocommerce-printorders');?></button>
			</div>

		</div>
		
		<?php
	}

	public function extraconfig_getopts_internal($opts) {
// 		$size_selected = (is_array($opts) && !empty($opts['internal_paper_size'])) ? $opts['internal_paper_size'] : 'letter';
// 		$orientation = (is_array($opts) && !empty($opts['internal_paper_orientation'])) ? $opts['internal_paper_orientation'] : 'portrait';
// 		$paper_sizes = $this->get_paper_sizes();
		$width = (is_array($opts) && !empty($opts['internal_paper_width'])) ? $opts['internal_paper_width'] : 216.90;
		$height = (is_array($opts) && !empty($opts['internal_paper_height'])) ? $opts['internal_paper_height'] : 279.40;
		
		$format = (is_array($opts) && !empty($opts['print_format']) && $opts['print_format'] == 'text/plain') ? 'text/plain' : 'text/html';
		
		return array(
			'format' => $format,
			'paper_width' => $width * 2.83464567,
			'paper_height' => $height * 2.83464567
		);
	}

	private function get_paper_sizes() {
		// From gcpl/dompdf/include/cpdf_adapter.cls.php
		// In points. 1mm = 0.393700787 inches.
		$paper_sizes = array(
			"4a0" => array(0,0,4767.87,6740.79),
			"2a0" => array(0,0,3370.39,4767.87),
			"a0" => array(0,0,2383.94,3370.39),
			"a1" => array(0,0,1683.78,2383.94),
			"a2" => array(0,0,1190.55,1683.78),
			"a3" => array(0,0,841.89,1190.55),
			"a4" => array(0,0,595.28,841.89),
			"a5" => array(0,0,419.53,595.28),
			"a6" => array(0,0,297.64,419.53),
			"a7" => array(0,0,209.76,297.64),
			"a8" => array(0,0,147.40,209.76),
			"a9" => array(0,0,104.88,147.40),
			"a10" => array(0,0,73.70,104.88),
			"b0" => array(0,0,2834.65,4008.19),
			"b1" => array(0,0,2004.09,2834.65),
			"b2" => array(0,0,1417.32,2004.09),
			"b3" => array(0,0,1000.63,1417.32),
			"b4" => array(0,0,708.66,1000.63),
			"b5" => array(0,0,498.90,708.66),
			"b6" => array(0,0,354.33,498.90),
			"b7" => array(0,0,249.45,354.33),
			"b8" => array(0,0,175.75,249.45),
			"b9" => array(0,0,124.72,175.75),
			"b10" => array(0,0,87.87,124.72),
			"c0" => array(0,0,2599.37,3676.54),
			"c1" => array(0,0,1836.85,2599.37),
			"c2" => array(0,0,1298.27,1836.85),
			"c3" => array(0,0,918.43,1298.27),
			"c4" => array(0,0,649.13,918.43),
			"c5" => array(0,0,459.21,649.13),
			"c6" => array(0,0,323.15,459.21),
			"c7" => array(0,0,229.61,323.15),
			"c8" => array(0,0,161.57,229.61),
			"c9" => array(0,0,113.39,161.57),
			"c10" => array(0,0,79.37,113.39),
			"ra0" => array(0,0,2437.80,3458.27),
			"ra1" => array(0,0,1729.13,2437.80),
			"ra2" => array(0,0,1218.90,1729.13),
			"ra3" => array(0,0,864.57,1218.90),
			"ra4" => array(0,0,609.45,864.57),
			"sra0" => array(0,0,2551.18,3628.35),
			"sra1" => array(0,0,1814.17,2551.18),
			"sra2" => array(0,0,1275.59,1814.17),
			"sra3" => array(0,0,907.09,1275.59),
			"sra4" => array(0,0,637.80,907.09),
			"letter" => array(0,0,612.00,792.00),
			"legal" => array(0,0,612.00,1008.00),
			"ledger" => array(0,0,1224.00, 792.00),
			"tabloid" => array(0,0,792.00, 1224.00),
			"executive" => array(0,0,521.86,756.00),
			"folio" => array(0,0,612.00,936.00),
			"commercial #10 envelope" => array(0,0,684,297),
			"catalog #10 1/2 envelope" => array(0,0,648,864),
			"8.5x11" => array(0,0,612.00,792.00),
			"8.5x14" => array(0,0,612.00,1008.0),
			"11x17"  => array(0,0,792.00, 1224.00),
		);
		return $paper_sizes;
	}

	private function get_types() {
	
		$wcdn_old_style = (class_exists('WooCommerce_Delivery_Notes') && !empty(WooCommerce_Delivery_Notes::$plugin_version) && version_compare(WooCommerce_Delivery_Notes::$plugin_version, '4.2', '<')) ? true : false;
	
		return apply_filters('woocommerce_printorders_types', array(
			'internal' => array(
				'description' => __('Simple order summary (generated internally - always available, and suitable for almost any printer).', 'woocommerce-printorders'),
				'description_short' => __('Simple summary', 'woocommerce-printorders'),
				'url' => 'https://www.simbahosting.co.uk/s3/print-orders-formatting/',
				'output_type' => 'html',
				'output_source_type' => 'internal-function',
				'output_source_function' => 'getdocument_internal',
				'extra_config_output_function' => 'extraconfig_internal',
				'extra_config_options_function' => 'extraconfig_getopts_internal'
			),

			'pdf_invoices_invoice' => array(
				'title' => 'WooCommerce PDF Invoices and Packing Slips',
				'description' => sprintf(__('Invoice generated by the %s plugin.', 'woocommerce-printorders'), 'WooCommerce PDF Invoices and Packing Slips (WP Overnight)'),
				'description_short' => __('Invoice', 'woocommerce-printorders').' (PIPS)',
				'url' => 'https://wordpress.org/plugins/woocommerce-pdf-invoices-packing-slips/',
				'install_url' => admin_url('plugin-install.php?tab=search&s=woocommerce+pdf+invoices+packing+slips&plugin-search-input=Search+Plugins'),
				'enabled_type' => 'plugin',
				'enabled_data' => 'woocommerce-pdf-invoices-packing-slips/woocommerce-pdf-invoices-packingslips.php',
				'output_type' => 'pdf-raw',
				'output_source_type' => 'internal-function',
				'output_source_function' => 'getdocument_woocommerce_pips_invoice',
				'configure_url' => 'admin.php?page=wpo_wcpdf_options_page&tab=general'
			),

			'pdf_invoices_packing_slip' => array(
				'title' => 'WooCommerce PDF Invoices and Packing Slips',
				'description' => sprintf(__('Packing slip generated by the %s plugin.', 'woocommerce-printorders'), 'WooCommerce PDF Invoices and Packing Slips (WP Overnight)'),
				'description_short' => __('Packing slip', 'woocommerce-printorders').' (PIPS)',
				'url' => 'https://wordpress.org/plugins/woocommerce-pdf-invoices-packing-slips/',
				'install_url' => admin_url('plugin-install.php?tab=search&s=woocommerce+pdf+invoices+packing+slips&plugin-search-input=Search+Plugins'),
				'enabled_type' => 'plugin',
				'enabled_data' => 'woocommerce-pdf-invoices-packing-slips/woocommerce-pdf-invoices-packingslips.php',
				'output_type' => 'pdf-raw',
				'output_source_type' => 'internal-function',
				'output_source_function' => 'getdocument_woocommerce_pips_packing_slip',
				'configure_url' => 'admin.php?page=wpo_wcpdf_options_page&tab=general'
			),

			'yith_woocommerce_pdf_invoice' => array(
				'title' => 'YITH WooCommerce PDF Invoice and Shipping List (Premium)',
				'description' => sprintf(__('Invoice generated by the %s plugin.', 'woocommerce-printorders'), 'YITH WooCommerce PDF Invoice and Shipping List'),
				'description_short' => __('Invoice', 'woocommerce-printorders').' (YITH)',
				'url' => 'https://yithemes.com/themes/plugins/yith-woocommerce-pdf-invoice/',
				'install_url' => 'https://yithemes.com/themes/plugins/yith-woocommerce-pdf-invoice/',
				'enabled_type' => 'plugin',
				'enabled_data' => 'yith-woocommerce-pdf-invoice-premium/init.php',
				'output_type' => 'pdf-raw',
				'output_source_type' => 'internal-function',
				'output_source_function' => 'getdocument_yith_pdf_premium',
				'configure_url' => 'admin.php?page=yith_woocommerce_pdf_invoice_panel'
			),
			
			'woocommercecom_print_invoices_packing_slips' => array(
				'title' => 'WooCommerce.Com Print Invoices/Packing Slips',
				'description' => sprintf(__('(Beta, unsupported feature): Packing slip generated by the %s plugin.', 'woocommerce-printorders'), 'WooCommerce.Com Print Invoices and Packing Slips'),
				'description_short' => __('WooCommerce.Com', 'woocommerce-printorders').' (PIPS)',
				'url' => 'https://www.woocommerce.com/products/print-invoices-packing-lists/',
				'install_url' => 'https://www.woocommerce.com/products/print-invoices-packing-lists/',
				'enabled_type' => 'plugin',
				'enabled_data' => 'woocommerce-pip/woocommerce-pip.php',
				'output_type' => 'html',
				'output_source_type' => 'internal-function',
				'output_source_function' => 'getdocument_woocommercecom_pips_packing_slip',
				'configure_url' => 'admin.php?page=wc-settings&tab=pip'
			),
			
			'woocommercecom_print_invoices_invoice' => array(
				'title' => 'WooCommerce.Com Print Invoices/Packing Slips',
				'description' => sprintf(__('(Beta, unsupported feature): Invoice generated by the %s plugin.', 'woocommerce-printorders'), 'WooCommerce.Com Print Invoices and Packing Slips'),
				'description_short' => __('WooCommerce.Com', 'woocommerce-printorders').' (PIPS)',
				'url' => 'https://www.woocommerce.com/products/print-invoices-packing-lists/',
				'install_url' => 'https://www.woocommerce.com/products/print-invoices-packing-lists/',
				'enabled_type' => 'plugin',
				'enabled_data' => 'woocommerce-pip/woocommerce-pip.php',
				'output_type' => 'html',
				'output_source_type' => 'internal-function',
				'output_source_function' => 'getdocument_woocommercecom_pips_invoice',
				'configure_url' => 'admin.php?page=wc-settings&tab=pip'
			),

			'delivery_notes_invoice' => array(
				'title' => 'WooCommerce Delivery Notes',
				'description' => sprintf(__('Invoice generated by the %s plugin.', 'woocommerce-printorders'), 'WooCommerce Delivery Notes'),
				'description_short' => __('Invoice', 'woocommerce-printorders').' (WC-DN)',
				'url' => 'http://wordpress.org/plugins/woocommerce-delivery-notes/',
				'install_url' => admin_url('plugin-install.php?tab=search&s=woocommerce+delivery+notes&plugin-search-input=Search+Plugins'),
				'enabled_type' => 'plugin',
				'enabled_data' => 'woocommerce-delivery-notes/woocommerce-delivery-notes.php',
				'output_type' => 'html',
				'output_source_type' => 'internal-function',
				'output_source_function' => 'getdocument_woocommerce_delivery_notes_invoice',
				'configure_url' => 'admin.php?page=wc-settings&tab='.($wcdn_old_style ? 'woocommerce-delivery-notes' : 'wcdn-settings')
			),

			'delivery_notes_deliverynote' => array(
				'title' => 'WooCommerce Delivery Notes',
				'description' => sprintf(__('Delivery note generated by the %s plugin.', 'woocommerce-printorders'), 'WooCommerce Delivery Notes'),
				'description_short' => __('Delivery note', 'woocommerce-printorders').' (WC-DN)',
				'install_url' => admin_url('plugin-install.php?tab=search&s=woocommerce+delivery+notes&plugin-search-input=Search+Plugins'),
				'url' => 'http://wordpress.org/plugins/woocommerce-delivery-notes/',
				'enabled_type' => 'plugin',
				'enabled_data' => 'woocommerce-delivery-notes/woocommerce-delivery-notes.php',
				'output_type' => 'html',
				'output_source_type' => 'internal-function',
				'output_source_function' => 'getdocument_woocommerce_delivery_notes_deliverynote',
				'configure_url' => 'admin.php?page=wc-settings&tab='.($wcdn_old_style ? 'woocommerce-delivery-notes' : 'wcdn-settings')
			)
		));
	}

	private function is_type_enabled($key, $type) {

		if ('internal' == $key) return array(true, '');

		$enabled = false;
		$enabled_msg = '';

		$active_plugins = get_option('active_plugins');
		if (!is_array($active_plugins)) $active_plugins = array();
		if (is_multisite()) $active_plugins = array_merge($active_plugins, get_site_option('active_sitewide_plugins', array()));

		if (isset($type['enabled_type']) && 'plugin' == $type['enabled_type'] && is_array($active_plugins)) {
			if (in_array($type['enabled_data'], $active_plugins)) {
				$enabled = true;
				if (!empty($type['configure_url'])) $enabled_msg = '<a href="'.admin_url($type['configure_url']).'">'.__("You can configure this plugin's output by following this link.", 'woocommerce-printorders').'</a>';
			} else {
				if (file_exists(WP_PLUGIN_DIR.'/'.dirname($type['enabled_data']))) {
					$enabled_msg = '<a href="'.admin_url('plugins.php').'">'.htmlspecialchars(sprintf(__('The %s plugin needs to be activated before this option will take effect.', 'woocommerce-printorders'), $type['title'])).'</a>';;
				} else {
					$enabled_msg = '<a href="'.$type['install_url'].'">'.htmlspecialchars(sprintf(__('The %s plugin needs to be installed before this option will take effect.', 'woocommerce-printorders'), $type['title'])).'</a>';
				}
			}
		}
		return array($enabled, $enabled_msg);
	}

	public function admin_footer_settings() {
		?>
		<script>
			jQuery(document).ready(function($) {
			
				function enable_or_disable_html_settings() {
					var is_html = $('#printorders_print_format_html').is(':checked');
					if (is_html) {
						$('#printorders_print_format_html_settings select, #printorders_print_format_html_settings button').prop('disabled', false);
						$('#printorders_print_format_html_settings').css('opacity', 1);
					} else {
						$('#printorders_print_format_html_settings select, #printorders_print_format_html_settings button').prop('disabled', true);
						$('#printorders_print_format_html_settings').css('opacity', 0.5);
					}
				}
				
				enable_or_disable_html_settings();
				$('input[name="printorders_print_format"]').change(function() {
					enable_or_disable_html_settings();
				});

				$('#printorders_internal_paper_size_choose').click(function(e) {
					e.preventDefault();
					var size = $('#printorders_internal_paper_size').val();
					var orientation = $('#printorders_internal_paper_orientation').val();
					var size_as_array = JSON.parse(size);
					var width = size_as_array[2];
					var height = size_as_array[3];
					if ('landscape' == orientation) {
						var tmp = width;
						width = height;
						height = tmp;
					}
					// Convert from points to mm
					width = format_float(width / 2.83464567);
					height = format_float(height / 2.83464567);
					$('#printorders_internal_paper_width').val(width);
					$('#printorders_internal_paper_height').val(height);
				});

				function output_destination_set(whichone) {
					var show = $(whichone).is(':checked');
					var id = $(whichone).attr('id');
					if ('printorders_enabled_' != id.substr(0, 20)) { return; }
					var whichone = id.substr(20);
					if (show) {
						$('tr.printorders_output_destination .printorders_extraconfig_'+whichone).slideDown('slow');
					} else {
						$('tr.printorders_output_destination .printorders_extraconfig_'+whichone).slideUp('slow');
					}
				}

				function format_float(number, places) {
					return parseFloat(Math.round(number * 100) / 100).toFixed(places);
				}

				// Initial state
				$('tr.printorders_output_destination input[type="checkbox"]').each(function(index, element) {
					output_destination_set(element);
				});


				$('tr.printorders_output_destination  input[type="checkbox"]').change(function() {
					output_destination_set(this);
				});
				
			});
		</script>
		<?php
	}

	public function woocommerce_admin_field_printorders() {

		add_action('admin_footer', array($this, 'admin_footer_settings'));

		$opts = $this->get_opts();
		$types = $this->get_types();

		$gcp_options = apply_filters('google_cloud_print_options', array());

		if (empty($gcp_options['printer'])) {
			echo '<p style="font-size:120%"><strong><a href="'.admin_url('options-general.php?page=google_cloud_print_library').'">'.htmlspecialchars(__('Before anything can be printed, you must configure your Google Cloud Print settings - follow this link to do so.', 'woocommerce_printorders')).'</a></strong></p>';
		}

		foreach ($types as $key => $type) {

			if (!is_array($type)) continue;

			list($enabled, $enabled_msg) = $this->is_type_enabled($key, $type);

			?>
			<tr valign="top" class="printorders_output_destination">
				<th scope="row" class="titledesc">
				<label for="printorders_enabled_<?php echo $key; ?>"><?php echo htmlspecialchars($type['description_short']); ?></label>
				</th>
				<td>
					<?php
					if (!isset($printed_hidden_field)) {
						# Used to detect form submissions
						echo '<input type="hidden" name="printorders_settingsform" value="1">';
						$printed_hidden_field=false;
					}
					?>
					<input type="checkbox" <?php if (!empty($opts['enabled_'.$key])) echo ' checked="checked"'; ?> name="printorders_enabled_<?php echo $key; ?>" id="printorders_enabled_<?php echo $key; ?>" value="1">
					<label for="printorders_enabled_<?php echo $key; ?>"><?php echo htmlspecialchars($type['description']); ?></label> <?php if ($enabled_msg) echo $enabled_msg;?>
					<?php
						if (isset($type['extra_config_output_function'])) {
							echo '<div class="printorders_extraconfig_'.$key.'">';
							call_user_func(array($this, $type['extra_config_output_function']), $opts);
							echo '</div>';
						}
					?>
				</td>
			</tr>
			<?php
		}

		$debug_email = (!empty($opts['debug_email'])) ? $opts['debug_email'] : '';

		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
			<label for="printorders_debug_email"><?php echo htmlspecialchars(__('Debugging logs', 'woocommerce-printorders')); ?></label>
			</th>
			<td>
				<input type="text" name="printorders_debug_email" id="printorders_debug_email" value="<?php echo esc_attr($debug_email);?>" size="54">
				<br><em><label for="printorders_debug_email"><?php echo htmlspecialchars(__('Enter email addresses here (comma-separated), if you wish a debugging log to be emailed to you for each checkout or payment event.', 'woocommerce-printorders').' '.__("For payment methods for which payment notification is automated (e.g. PayPal, Stripe), a print job only begins when an order's payment is completed. So, if you see no email, then you should check that the order has been paid.", 'woocommerce-printorders')); ?></label></em><br>
				<?php _e('You can also:', 'woocommerce-printorders'); ?> <a href="<?php echo admin_url('admin-ajax.php', 'relative');?>?action=wc_googlecloudprint&subaction=download_log&_wpnonce=<?php echo wp_create_nonce("wc-googlecloudprint-nonce"); ?>"><?php _e('download the log file', 'woocommerce-printorders'); ?></a>
			</td>
		</tr>
		<?php

	}

	/**
	 * Sets up WooCommerce logging (a WC_Logger in $this->wc_logger)
	 *
	 * @param Boolean $initial_lines - whether to log some initial lines or not
	 *
	 */
	private function initialise_log($initial_lines = true) {
		$this->log = array();
		if (empty($this->wc_logger) && class_exists('WC_Logger')) {
			$this->wc_logger = new WC_Logger();
			if ($initial_lines) {
				$this->wc_logger->add($this->wc_logger_id, "[info] ".$this->version_info());
				$this->wc_logger->add($this->wc_logger_id, "[info] ".'Running on: '.home_url().' / '.site_url());
			}
		}
	}
	
	public function woocommerce_checkout_order_processed($order_id_or_order) {

		$order = is_a($order_id_or_order, 'WC_Order') ? $order_id_or_order : wc_get_order($order_id_or_order);
		
		$order_id = is_callable(array($order, 'get_id')) ? $order->get_id() : $order->id;
	
		$this->initialise_log();
		$this->log("checkout_order_processed event: order_id=$order_id backtrace=".wp_debug_backtrace_summary());

		$print_order_now = false;
		
		$payment_method = is_callable(array($order, 'get_payment_method')) ? $order->get_payment_method() : $order->payment_method;
		
		// 'cop' is from https://wordpress.org/plugins/wc-cash-on-pickup/
		// 'pis' is from https://wordpress.org/plugins/woocommerce-pay-in-store-gateway/
		// 'other_payment' is from https://wordpress.org/plugins/woocommerce-other-payment-gateway/
		if ($payment_method == 'cod' || $payment_method == 'adminoverride' || $payment_method == 'cheque' || $payment_method == 'bacs' || $payment_method == 'cop' || $payment_method == 'pis' || $payment_method == 'other_payment') {
			$print_order_now = true;
		}

		$pre_filter_choice = $print_order_now;
		
		$print_order_now = apply_filters('woocommerce_print_orders_print_order_upon_processed', $print_order_now, $order);

		// Allow new payment methods or other considerations to over-ride this
		$print_order_now = apply_filters('woocommerce_print_orders_order_now', $print_order_now, $payment_method, $order);

		$this->log("Order payment method: ".$payment_method.". Decision on whether to print now or wait for payment confirm: ".($print_order_now ? 'Now' : 'Wait')." (Pre-filter decision: ".($pre_filter_choice ? 'Now' : 'Wait').")");

		if ($print_order_now) {
			set_error_handler(array($this, 'get_php_errors'), E_ALL & ~E_STRICT);
			$this->print_order_or_schedule($order_id);
			restore_error_handler();
		}

		$this->email_debug_logs();
	}

	private function print_order_or_schedule($order_id, $log_description = '') {
		$via_cron = apply_filters('woocommerce_print_orders_via_cron', false);
		if ($via_cron) {
			$this->schedule_print($order_id);
		} else {
			$this->print_go($order_id, $log_description);
		}
	}

	// @param WC_Order $order
	public function get_order_date($order) {
	
		if (version_compare(WC_VERSION, '2.7', '<')) return strtotime($order->completed_date);
	
		$try_these = array('completed', 'paid', 'created');
	
		$the_date = $order->get_date_completed();
		if (null == $the_date) $the_date = $order->get_date_paid();
		if (null == $the_date && is_callable(array($order, 'get_date_created'))) $the_date = $order->get_date_created();

		if (is_object($the_date) && is_callable(array($the_date, 'getTimestamp'))) $the_date = $the_date->getTimestamp();

		if (is_object($the_date)) $the_date = 0;
		
		return $the_date;
	
	}
	
	public function get_detail($order, $which, $root = 'auto') {
	
		if ('auto' == $root) {
		
			$root = 'shipping_';
		
			if ('disabled' === get_option('woocommerce_ship_to_countries')) {
				$root = 'billing_';
			}
		
			$root = apply_filters('woocommerce_print_orders_address_root', $root, $order, $which);
		}
	
		$key = $root.$which;
		$detail = is_callable(array($order, "get_$key")) ? call_user_func(array($order, "get_$key")) : $order->$key;
		return apply_filters('woocommerce_print_orders_printdefault_'.$which, $detail, $which, $order, $key);
	}

	public function woocommerce_payment_complete($order_id) {
		if (apply_filters('woocommerce_print_orders_print_on_payment_complete', true, $order_id)) {
			$this->print_order_or_schedule($order_id, 'payment_complete');
		}
	}

	public function woocommerce_print_order_go($order_id) {
		$this->print_go($order_id, "cron woocommerce_print_order_go");
	}

	/**
	 * Perform a print operation
	 *
	 * @param Integer $order_id
	 * @param String $log_description
	 * @param String|Boolean $printer_id
	 * @param String|Boolean $type_key - optionally specify a particular destination
	 */
	private function print_go($order_id, $log_description, $printer_id = false, $type_key = false) {
		if ($log_description) {
			$this->initialise_log();
			set_error_handler(array($this, 'get_php_errors'), E_ALL & ~E_STRICT);
			$this->log("$log_description event: order_id=$order_id backtrace=".wp_debug_backtrace_summary());
		}
		try {
			$this->woocommerce_process_order($order_id, $printer_id, $type_key);
		} catch (Exception $e) {
			$log_message = 'Exception ('.get_class($e).") occurred during print_go($order_id): ".$e->getMessage().' (Code: '.$e->getCode().', line '.$e->getLine().' in '.$e->getFile().')';
			error_log($log_message);
			$this->log($log_message);
		} catch (Error $e) {
			$log_message = 'PHP fatal error ('.get_class($e).") occurred during print_go($order_id): ".$e->getMessage().' (Code: '.$e->getCode().', line '.$e->getLine().' in '.$e->getFile().')';
			error_log($log_message);
			$this->log($log_message);
		}
		if ($log_description) {
			$this->email_debug_logs();
			restore_error_handler();
		}
	}

	/** 
	 * Process a WooCommerce order
	 *
	 * @param Integer $order_id - the WooCommerce order ID
	 * @param Array|String|Boolean $printer_id - a printer ID, list of printer IDs, or false to pass through a request to use the default from the settings
	 * @param Array|Boolean $type_key - a list of destinations (i.e. printout types), or false to get the default from the options
	 */
	public function woocommerce_process_order($order_id, $printer_id = false, $type_key = false) {

		if (!class_exists('GoogleCloudPrintLibrary_GCPL_v2')) return false;

		$this->order_id = $order_id;

		$opts = $this->get_opts();
		$types = $this->get_types();

		$this->log("Options: ".serialize($opts));

		if (in_array($order_id, $this->already_printed) && !apply_filters('woocommerce_print_order_allow_duplicates', false, $order_id, $opts, $types)) {
			$this->log("Already printed; aborting ($order_id)");
			return;
		}
		$this->already_printed[] = $order_id;
		
		$order = wc_get_order($order_id);

		/* $order itself has properties including: status (= 'on-hold' for cash-on-delivery payments), shipping_(first|last_name, company, address1, address2, city, postcode, state, method (slug), method_title), payment_method (slug), payment_method_title, (float) order_discount, (float) cart_discount, order_tax, order_shipping, order_shipping_tax, order_total, and various others, incl. customer_note
		*/

		// This filter allows a user to either exclude, or re-order, the order items (without having to adjust the template)
		$order_items = apply_filters('woocommerce_printorders_print_order_items', $order->get_items(), $order, $opts, $types);

		$destinations = (false == $type_key) ? $this->get_destinations_from_options($opts) : array($type_key);
		
		$this->log("Chosen destinations (i.e. print templates): ".implode(', ', $destinations));

		$title = apply_filters(
			'woocommerce_printorders_printjobtitle',
			get_bloginfo('name').' - '.sprintf(__('Order Number %s', 'woocommerce-printorders'), $order->get_order_number($order_id)),
			$order_id
		);

		$this->log("Job title: ".$title);

		// Process $destinations
/*
			'delivery_notes_invoice' => array(
				'title' => 'WooCommerce Delivery Notes',
				'description' => __('Invoice generated by the WooCommerce Delivery Notes plugin.', 'woocommerce-printorders'),
				'description_short' => __('Invoice', 'woocommerce-printorders').' (WC-DN)',
				'url' => 'http://wordpress.org/plugins/woocommerce-delivery-notes/',
				'enabled_type' => 'plugin',
				'enabled_data' => 'woocommerce-delivery-notes/woocommerce-delivery-notes.php',
				'output_type' => 'html',
				'output_source_type' => 'internal-function',
				'output_source_function' => 'getdocument_woocommerce_delivery_notes_invoice',
			),
*/

		// These need defining early, before some other plugin (e.g. WooCommerce PDF Invoicing and Packing Slips) gets in and loads DomPDF
		// N.B. From DomPDF 0.8.0+, these do nothing. DomPDF has new options handling. But we set them in case some other plugin has loaded DomPDF already on an older version.
		// Handles tables better from WooCommerce Delivery Notes
		if (!defined('DOMPDF_ENABLE_CSS_FLOAT')) define("DOMPDF_ENABLE_CSS_FLOAT", true);
		// Load external logos
		if (!defined('DOMPDF_ENABLE_REMOTE')) define("DOMPDF_ENABLE_REMOTE", true);
		
		add_filter('google_cloud_print_dompdf_options', array($this, 'google_cloud_print_dompdf_options'));

		@set_time_limit(300);

		foreach ($destinations as $dest) {

			$this->log("Processing destination: $dest");
			
			if (!isset($types[$dest])) {
				$order->add_order_note(sprintf(__('Google Cloud Print: processing destination: %s', 'woocommerce-printorders'), "Does not exist: $dest"));
				$this->log("Unknown destination - aborting");
				continue;
			}

			$destinfo = $types[$dest];

			list($enabled, $enabled_msg) = $this->is_type_enabled($dest, $destinfo);

			if (!$enabled) {
				$this->log("Destination not available. ".$enabled_msg);
				continue;
			}

			$order_note_description = isset($destinfo['description_short']) ? $destinfo['description_short'] : $destinfo['description'];
			
			$order_note = sprintf(__('Google Cloud Print: processing destination: %s', 'woocommerce-printorders'), $order_note_description);
			if ($printer_id) {
				$order_note_ids = is_array($printer_id) ? implode(', ', $printer_id) : $printer_id;
				$order_note .= ' '.sprintf(__('Printer(s): %s', 'woocommerce-printorders'), $order_note_ids);
			}
			
			$order->add_order_note($order_note);
			
			$document = '';
			
			if ('internal-function' == $destinfo['output_source_type'] && strpos($destinfo['output_source_function'], 'getdocument_') === 0) {
			
				add_action('woocommerce_cloudprint_internaloutput_header', array($this, 'woocommerce_cloudprint_internaloutput_header'));
				$document = call_user_func(array($this, $destinfo['output_source_function']), $order, $order_id, $order_items, $opts);
				remove_action('woocommerce_cloudprint_internaloutput_header', array($this, 'woocommerce_cloudprint_internaloutput_header'));

				if (empty($document)) {
					$this->log("No document was returned - aborting");
					continue;
				}
			} else {
				$this->log("Unknown destination type: ".$destinfo['output_source_type']);
				continue;
			}

			if ('html' != $destinfo['output_type'] && 'pdf-raw' != $destinfo['output_type']) {
				$this->log("Unknown output type (".$destinfo['output_type'].") - aborting");
				continue;
			}

			$document = apply_filters('woocommerce_printorders_document', $document, $order, $order_id, $destinfo);

			$format_extra = '';
			if (is_array($document)) {
				if (isset($document['pdf-raw'])) {
					$format_extra = ', pdf-raw';
				} elseif (isset($document['pdf-file'])) {
					$format_extra = ', pdf-file';
				} elseif (isset($document['data-raw'])) {
					$format_extra = ', mime type:'.$document['mime-type'];
				}
			}
			$this->log("Output type: ".$destinfo['output_type']." (data format: ".gettype($document)."$format_extra)");
			
			# false means 'default'
			$copies = apply_filters('woocommerce_print_orders_copies', false, $dest, $destinfo);

			$options = array();
			if (isset($destinfo['extra_config_options_function'])) {
				$options = call_user_func(array($this, $destinfo['extra_config_options_function']), $opts);
			}

			$options['destination'] = $dest;
			$options['destination_info'] = $destinfo;
			$options['order_id'] = $order_id;
			
			if ($printer_id) $options['printer'] = is_array($printer_id) ? $printer_id : array($printer_id);
			
			$options = apply_filters('woocommerce_print_orders_options_pre_print', $options);
			
			$this->print_document($document, $title, $copies, $options);
			
		}
		
		remove_filter('google_cloud_print_dompdf_options', array($this, 'google_cloud_print_dompdf_options'));

	}

	/**
	 * Given the plugin options, return a list of selected destinations
	 *
	 * @param Array $opts - plugin options; as from get_opts()
	 *
	 * @return Array - a list of destinations, by ID
	 */
	public function get_destinations_from_options($opts) {
		$destinations = array();
		foreach ($opts as $key => $opt) {
			if (0 === strpos($key, 'enabled_')) $destinations[] = substr($key, 8);
		}

		return $destinations;
	}
	
	public function google_cloud_print_dompdf_options($dompdf_options) {
		$dompdf_options->setIsRemoteEnabled(true);
		// With DomPDF 0.7.0+, there isn't an option for CSS floats any more. The changelog says that support has been "taken out of beta".
		return $dompdf_options;
	}
	
	public function woocommerce_cloudprint_internaloutput_header() {
		$options = get_option('google_cloud_print_library_options', array());
		if (!empty($options['header'])) echo $options['header'];
	}

	public function get_php_errors($errno, $errstr, $errfile, $errline) {
		if (0 == error_reporting()) return true;
		list($level, $logline) = $this->php_error_to_logline($errno, $errstr, $errfile, $errline);
		$this->log($logline, $level);
		# Don't pass it up the chain (since it's going to be output to the user always)
		return true;
	}

	private function php_error_to_logline($errno, $errstr, $errfile, $errline) {
		$level = 'notice';
		switch ($errno) {
			case 1:		$e_type = 'E_ERROR'; $level = 'error'; break;
			case 2:		$e_type = 'E_WARNING'; $level = 'warning'; break;
			case 4:		$e_type = 'E_PARSE'; break;
			case 8:		$e_type = 'E_NOTICE'; break;
			case 16:	$e_type = 'E_CORE_ERROR'; $level = 'error'; break;
			case 32:	$e_type = 'E_CORE_WARNING'; $level = 'warning'; break;
			case 64:	$e_type = 'E_COMPILE_ERROR'; $level = 'error'; break;
			case 128:	$e_type = 'E_COMPILE_WARNING'; $level = 'warning'; break;
			case 256:	$e_type = 'E_USER_ERROR'; $level = 'error'; break;
			case 512:	$e_type = 'E_USER_WARNING'; $level = 'warning'; break;
			case 1024:	$e_type = 'E_USER_NOTICE'; break;
			case 2048:	$e_type = 'E_STRICT'; break;
			case 4096:	$e_type = 'E_RECOVERABLE_ERROR'; $level = 'error'; break;
			case 8192:	$e_type = 'E_DEPRECATED'; break;
			case 16384:	$e_type = 'E_USER_DEPRECATED'; break;
			case 32767:
			case 30719:	$e_type = 'E_ALL'; break;
			default:	$e_type = "E_UNKNOWN ($errno)"; break;
		}

		if (!is_string($errstr)) $errstr = serialize($errstr);

		if (0 === strpos($errfile, ABSPATH)) $errfile = substr($errfile, strlen(ABSPATH));

		return array($level, "PHP event: code $e_type: $errstr (line $errline, $errfile)");
	}

	private function print_document($document, $title, $copies, $extra_options = array()) {

		$gcpl = new GoogleCloudPrintLibrary_GCPL_v2();

		$this->log("Copies to print: ".serialize($copies)." : despatching...");

		$options = apply_filters('google_cloud_print_options', array());

		$all_options = array_merge($options, $extra_options);
		
		if (isset($all_options['printer_options'])) $this->log("Printer options: ".json_encode($all_options['printer_options']));

		// Despatch the thing to the printer
		$printed = $gcpl->print_document(false, $title, $document, '', $copies, $all_options);

		// What to do with whatever is returned from that call
		if (!is_object($printed) || !isset($printed->success)) {
			$this->log('Unknown response received from GoogleCloudPrintLibrary_GCPL->print_document()');
			trigger_error('Unknown response received from GoogleCloudPrintLibrary_GCPL->print_document()', E_USER_NOTICE);
		} elseif ($printed->success !== true) {
			$message = is_wp_error($printed->message) ? "WP_Error (follows)" : $printed->message;
			
			$this->log('GoogleCloudPrintLibrary_GCPL->print_document(): printing failed: '.$message);
			if (is_wp_error($printed->message)) {
				$this->log($printed->message, 'error');
			} else {
				trigger_error('GoogleCloudPrintLibrary_GCPL->print_document(): printing failed: '.$printed->message, E_USER_NOTICE);
			}
		} else {
			$this->log('GoogleCloudPrintLibrary_GCPL->print_document(): printing succeeded: '.$printed->message);
		}

	}

	public function woocommerce_general_settings($settings) {

		$settings[] = array(
			'title' => __( 'Automatic Order Printing', 'woocommerce-printorders' ),
			'type' => 'title',
			'id' => 'printorders',
			'desc'  => __('Configure automatic printing of new orders, via Google Cloud Print. Each of the documents that you select below will be printed for each completed order (as long as any relevant helper plugins shown are installed).', 'woocommerce-printorders').' <a href="https://www.simbahosting.co.uk/s3/woocommerce-automatic-order-printing-documentation/">'.__('Go here for documentation.','woocommerce-printorders').'</a> '.sprintf(__('Note: as well as the settings below, any configured paper size in %s will also be relevant (the printer paper size and the size of the sheets in the PDF that is delivered to Google Cloud Print are independent).', 'woocommerce-printorders'), '<a href="'.admin_url('options-general.php?page=google_cloud_print_library').'">'.__('the saved Google Cloud Print settings', 'woocommerce-printorders').'</a>')
		);

		$settings[] = array(
			'type' => 'printorders'
		);

		$settings[] = array('type' => 'sectionend', 'id' => 'printorders');

		return $settings;

	}

	# Adds the settings link under the plugin on the plugin screen.
	public function plugin_action_links($links, $file) {
		$us = basename(dirname(__FILE__)).'/'.basename(__FILE__);
		if ($us == $file) {
			global $woocommerce;
			if (is_a($woocommerce, 'WooCommerce')) {
				array_unshift($links, '<a href="https://www.simbahosting.co.uk/s3/woocommerce-automatic-order-printing-documentation/">'.__("Documentation", "woocommerce-printorders").'</a>');
				$sp = (version_compare($woocommerce->version, '2.1', '<')) ? "woocommerce_settings" : "wc-settings";
				array_unshift($links, '<a href="'.admin_url("admin.php?page=$sp&tab=general").'">'.__("Settings", "woocommerce-printorders").'</a>');
			}
		}
		return $links;
	}

}
