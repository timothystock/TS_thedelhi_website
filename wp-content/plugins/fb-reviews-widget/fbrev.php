<?php
/*
Plugin Name: Facebook Reviews Widget
Plugin URI: https://richplugins.com/business-reviews-bundle-wordpress-plugin
Description: Instantly Facebook Page Reviews on your website to increase user confidence and SEO.
Author: RichPlugins <support@richplugins.com>
Version: 1.5.4
Author URI: https://richplugins.com
*/

require(ABSPATH . 'wp-includes/version.php');

include_once(dirname(__FILE__) . '/api/urlopen.php');
include_once(dirname(__FILE__) . '/helper/debug.php');

define('FBREV_VERSION',            '1.5.4');
define('FBREV_GRAPH_API',          'https://graph.facebook.com/v3.1/');
define('FBREV_API_RATINGS_LIMIT',  '500');
define('FBREV_PLUGIN_URL',         plugins_url(basename(plugin_dir_path(__FILE__ )), basename(__FILE__)));
define('FBREV_AVATAR',             FBREV_PLUGIN_URL . '/static/img/avatar.png');

function fbrev_options() {
    return array(
        'fbrev_app_id',
        'fbrev_app_secret',
        'fbrev_version',
        'fbrev_active',
    );
}

/*-------------------------------- Widget --------------------------------*/
function fbrev_init_widget() {
    if (!class_exists('Fb_Reviews_Widget' ) ) {
        require 'fbrev-widget.php';
    }
}
add_action('widgets_init', 'fbrev_init_widget');

function fbrev_register_widget() {
    return register_widget("Fb_Reviews_Widget");
}
add_action('widgets_init', 'fbrev_register_widget');

/*-------------------------------- Menu --------------------------------*/
function fbrev_setting_menu() {
     add_submenu_page(
         'options-general.php',
         'Facebook Reviews',
         'Facebook Reviews',
         'moderate_comments',
         'fbrev',
         'fbrev_setting'
     );
}
add_action('admin_menu', 'fbrev_setting_menu', 10);

function fbrev_setting() {
    include_once(dirname(__FILE__) . '/fbrev-setting.php');
}

/*-------------------------------- Links --------------------------------*/
function fbrev_plugin_action_links($links, $file) {
    $plugin_file = basename(__FILE__);
    if (basename($file) == $plugin_file) {
        $settings_link = '<a href="' . admin_url('options-general.php?page=fbrev') . '">' . fbrev_i('Settings') . '</a>';
        array_unshift($links, $settings_link);
    }
    return $links;
}
add_filter('plugin_action_links', 'fbrev_plugin_action_links', 10, 2);

/*-------------------------------- Row Meta --------------------------------*/
function fbrev_plugin_row_meta($input, $file) {
    if ($file != plugin_basename( __FILE__ )) {
        return $input;
    }

    $links = array(
        //'<a href="' . esc_url('https://richplugins.com') . '" target="_blank">' . fbrev_i('View Documentation') . '</a>',
        '<a href="' . esc_url('https://richplugins.com/business-reviews-bundle-wordpress-plugin') . '" target="_blank">' . fbrev_i('Upgrade to Business') . ' &raquo;</a>',
    );
    $input = array_merge($input, $links);
    return $input;
}
add_filter('plugin_row_meta', 'fbrev_plugin_row_meta', 10, 2);

/*-------------------------------- Activation --------------------------------*/
function fbrev_activation() {
    if (fbrev_does_need_update()) {
        fbrev_install();
    }
}
register_activation_hook(__FILE__, 'fbrev_activation');

function fbrev_install() {
    $version = (string)get_option('fbrev_version');
    if (!$version) {
        $version = '0';
    }

    if (version_compare($version, FBREV_VERSION, '=')) {
        return;
    }

    add_option('fbrev_active', '1');
    update_option('fbrev_version', FBREV_VERSION);
}

function fbrev_lang_init() {
    $plugin_dir = basename(dirname(__FILE__));
    load_plugin_textdomain('fbrev', false, basename( dirname( __FILE__ ) ) . '/languages');
}
add_action('plugins_loaded', 'fbrev_lang_init');

/*-------------------------------- Helpers --------------------------------*/
function fbrev_enabled() {
    $active = get_option('fbrev_active');
    if (empty($active) || $active === '0') { return false; }
    return true;
}

function fbrev_does_need_update() {
    $version = (string)get_option('fbrev_version');
    if (empty($version)) {
        $version = '0';
    }
    if (version_compare($version, '1.0', '<')) {
        return true;
    }
    return false;
}

function fbrev_api_rating($page_id, $page_access_token, $options, $cache_name, $cache_option, $limit, $show_success_api) {

    $response_cache_key = 'fbrev_' . FBREV_VERSION . '_' . $cache_name . '_api_' . $page_id;
    $options_cache_key = 'fbrev_' . FBREV_VERSION . '_' . $cache_name . '_options_' . $page_id;

    if (!isset($limit) || $limit == null) {
        $limit=FBREV_API_RATINGS_LIMIT;
    }

    $api_response = get_transient($response_cache_key);
    $widget_options = get_transient($options_cache_key);
    $serialized_instance = serialize($options);

    if ($api_response === false || $serialized_instance !== $widget_options) {
        $expiration = $cache_option;
        switch ($expiration) {
            case '1':
                $expiration = 3600;
                break;
            case '3':
                $expiration = 3600 * 3;
                break;
            case '6':
                $expiration = 3600 * 6;
                break;
            case '12':
                $expiration = 3600 * 12;
                break;
            case '24':
                $expiration = 3600 * 24;
                break;
            case '48':
                $expiration = 3600 * 48;
                break;
            case '168':
                $expiration = 3600 * 168;
                break;
            default:
                $expiration = 3600 * 24;
        }

        //string concatenation instead of 'http_build_query', because 'http_build_query' doesn't always work
        $api_url = FBREV_GRAPH_API . $page_id . "/ratings?access_token=" . $page_access_token . "&limit=" . $limit . "&fields=reviewer{id,name,picture.width(120).height(120)},created_time,rating,recommendation_type,review_text,open_graph_story{id}";

        $api_response = rplg_urlopen($api_url);

        set_transient($response_cache_key, $api_response, $expiration);
        set_transient($options_cache_key, $serialized_instance, $expiration);
    }

    //show the latest success API response if the error happened
    if ($show_success_api) {
        $response_cache_key_success = 'fbrev_' . $cache_name . '_suc_api_' . $page_id;
        $api_response_json = rplg_json_decode($api_response['data']);
        if (isset($api_response_json->data)) {
            set_transient($response_cache_key_success, $api_response, 0);
        } else {
            $last_success_api_response = get_transient($response_cache_key_success);
            if ($last_success_api_response !== false) {
                return $last_success_api_response;
            }
        }
    }
    return $api_response;
}

function fbrev_i($text, $params=null) {
    if (!is_array($params)) {
        $params = func_get_args();
        $params = array_slice($params, 1);
    }
    return vsprintf(__($text, 'fbrev'), $params);
}

?>