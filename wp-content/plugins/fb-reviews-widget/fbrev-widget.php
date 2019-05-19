<?php

/**
 * Facebook Reviews Widget
 *
 * @description: The Facebook Reviews Widget
 * @since      : 1.0
 */

class Fb_Reviews_Widget extends WP_Widget {

    public $options;

    public $widget_fields = array(
        'title'                => '',
        'page_id'              => '',
        'page_name'            => '',
        'page_access_token'    => '',
        'text_size'            => '120',
        'dark_theme'           => '',
        'view_mode'            => 'list',
        'pagination'           => '7',
        'disable_user_link'    => '',
        'max_width'            => '',
        'max_height'           => '',
        'centered'             => false,
        'open_link'            => true,
        'nofollow_link'        => true,
        'show_success_api'     => true,
        'lazy_load_img'        => true,
        'cache'                => '24',
        'api_ratings_limit'    => FBREV_API_RATINGS_LIMIT,
    );

    public function __construct() {
        parent::__construct(
            'fbrev_widget', // Base ID
            'Facebook Reviews', // Name
            array(
                'classname'   => 'fb-reviews-widget',
                'description' => fbrev_i('Display Facebook Reviews on your website.', 'fbrev')
            )
        );

        add_action('admin_enqueue_scripts', array($this, 'fbrev_widget_scripts'));

        wp_register_script('wpac_time_js', plugins_url('/static/js/wpac-time.js', __FILE__), array(), FBREV_VERSION);
        wp_enqueue_script('wpac_time_js', plugins_url('/static/js/wpac-time.js', __FILE__));

        wp_register_style('fbrev_css', plugins_url('/static/css/facebook-review.css', __FILE__), array(), FBREV_VERSION);
        wp_enqueue_style('fbrev_css', plugins_url('/static/css/facebook-review.css', __FILE__));
    }

    function fbrev_widget_scripts($hook) {
        if ($hook == 'widgets.php' || ($hook == 'post.php' && defined('SITEORIGIN_PANELS_VERSION'))) {

            wp_register_style('rplg_wp_css', plugins_url('/static/css/rplg-wp.css', __FILE__));
            wp_enqueue_style('rplg_wp_css', plugins_url('/static/css/rplg-wp.css', __FILE__));

            wp_enqueue_script('jquery');

            wp_register_script('wpac_js', plugins_url('/static/js/wpac.js', __FILE__), array(), FBREV_VERSION);
            wp_enqueue_script('wpac_js', plugins_url('/static/js/wpac.js', __FILE__));

            wp_register_script('fbrev_connect_js', plugins_url('/static/js/fbrev-connect.js', __FILE__), array(), FBREV_VERSION);
            wp_enqueue_script('fbrev_connect_js', plugins_url('/static/js/fbrev-connect.js', __FILE__));
        }
    }

    function widget($args, $instance) {
        global $wpdb;

        if (fbrev_enabled()) {
            extract($args);
            foreach ($this->widget_fields as $variable => $value) {
                ${$variable} = !isset($instance[$variable]) ? $this->widget_fields[$variable] : esc_attr($instance[$variable]);
            }

            if (empty($page_id)) { ?>
                <div class="fbrev-error" style="padding:10px;color:#B94A48;background-color:#F2DEDE;border-color:#EED3D7;">
                    <?php echo fbrev_i('Please check that this widget <b>Facebook Reviews</b> has a connected Facebook.'); ?>
                </div> <?php
                return false;
            }

            echo $before_widget;
            $response = fbrev_api_rating($page_id, $page_access_token, $instance, $this->id, $cache, $api_ratings_limit, $show_success_api);
            $response_data = $response['data'];
            $response_json = rplg_json_decode($response_data);
            if (isset($response_json->data)) {
                $reviews = $response_json->data;
                if ($title) { ?><h2 class="fbrev-widget-title widget-title"><?php echo $title; ?></h2><?php }
                include(dirname(__FILE__) . '/fbrev-reviews.php');
            } else {
                ?>
                <div class="fbrev-error" style="padding:10px;color:#B94A48;background-color:#F2DEDE;border-color:#EED3D7;">
                    <?php echo fbrev_i('Facebook API Error: ') . $response_data . fbrev_i('<br><b>Reconnecting to Facebook may fix the issue.</b>'); ?>
                </div>
                <?php
            }
            echo $after_widget;
        }
    }

    function update($new_instance, $old_instance) {
        $instance = $old_instance;
        foreach ($this->widget_fields as $field => $value) {
            $instance[$field] = strip_tags(stripslashes($new_instance[$field]));
        }
        return $instance;
    }

    function form($instance) {
        global $wp_version;
        foreach ($this->widget_fields as $field => $value) {
            if (array_key_exists($field, $this->widget_fields)) {
                ${$field} = !isset($instance[$field]) ? $value : esc_attr($instance[$field]);
            }
        }

        /*$fbrev_app_id = get_option('fbrev_app_id');
        $fbrev_app_secret = get_option('fbrev_app_secret');

        if ($fbrev_app_id && $fbrev_app_secret) {

            ?>
            <div id="<?php echo $this->id; ?>" class="rplg-widget">
                <?php include(dirname(__FILE__) . '/fbrev-options.php'); ?>
                <img src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7" data-widget-id="<?php echo $this->id; ?>" data-app-id="<?php echo $fbrev_app_id; ?>" data-app-secret="<?php echo $fbrev_app_secret; ?>" onload="fbrev_init({widgetId: this.getAttribute('data-widget-id'), appId: this.getAttribute('data-app-id'), appSecret: this.getAttribute('data-app-secret')})" style="display:none">
            </div>
            <?php

        } else {

            ?>
            <p>To add a widget, please fill 'App ID' and 'App Secret' on the <a href="<?php echo admin_url('options-general.php?page=fbrev'); ?>">setting page</a></p>
            <?php
        }*/

        ?>
        <div id="<?php echo $this->id; ?>" class="rplg-widget">
            <?php include(dirname(__FILE__) . '/fbrev-options.php'); ?>
            <img src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7" onload="(function(el) { var t = setInterval(function () {if (window.fbrev_init){fbrev_init({el: el});clearInterval(t);}}, 200); })(this.parentNode);" style="display:none">
        </div>
        <script type="text/javascript">
            function fbrev_load_js(src, cb) {
                var script = document.createElement('script');
                script.type = 'text/javascript';
                script.src = src;
                script.async = 'true';
                if (cb) {
                    script.addEventListener('load', function (e) { cb(null, e); }, false);
                }
                document.getElementsByTagName('head')[0].appendChild(script);
            }

            function fbrev_load_css(href) {
                var link = document.createElement('link');
                link.rel = 'stylesheet';
                link.href = href;
                document.getElementsByTagName('head')[0].appendChild(link);
            }

            if (!window.fbrev_init) {
                fbrev_load_css('<?php echo plugins_url('/static/css/rplg-wp.css?ver=' . FBREV_VERSION, __FILE__); ?>');
                fbrev_load_js('<?php echo plugins_url('/static/js/wpac.js?ver=' . FBREV_VERSION, __FILE__); ?>');
                fbrev_load_js('<?php echo plugins_url('/static/js/fbrev-connect.js?ver=' . FBREV_VERSION, __FILE__); ?>');
            }
        </script>
        <?php
    }
}
?>