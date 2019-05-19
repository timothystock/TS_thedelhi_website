<?php

if (!current_user_can('manage_options')) {
    die('The account you\'re logged in to doesn\'t have permission to access this page.');
}

function fbrev_has_valid_nonce() {
    $nonce_actions = array('fbrev_settings', 'fbrev_active');
    $nonce_form_prefix = 'fbrev-form_nonce_';
    $nonce_action_prefix = 'fbrev-wpnonce_';
    foreach ($nonce_actions as $key => $value) {
        if (isset($_POST[$nonce_form_prefix.$value])) {
            check_admin_referer($nonce_action_prefix.$value, $nonce_form_prefix.$value);
            return true;
        }
    }
    return false;
}

if (!empty($_POST)) {
    $nonce_result_check = fbrev_has_valid_nonce();
    if ($nonce_result_check === false) {
        die('Unable to save changes. Make sure you are accessing this page from the Wordpress dashboard.');
    }
}

// Post fields that require verification.
$valid_fields = array(
    'fbrev_active' => array(
        'key_name' => 'fbrev_active',
        'values' => array('Disable', 'Enable')
    ));

// Check POST fields and remove bad input.
foreach ($valid_fields as $key) {

    if (isset($_POST[$key['key_name']]) ) {

        // SANITIZE first
        $_POST[$key['key_name']] = trim(sanitize_text_field($_POST[$key['key_name']]));

        // Validate
        if (isset($key['regexp']) && $key['regexp']) {
            if (!preg_match($key['regexp'], $_POST[$key['key_name']])) {
                unset($_POST[$key['key_name']]);
            }

        } else if (isset($key['type']) && $key['type'] == 'int') {
            if (!intval($_POST[$key['key_name']])) {
                unset($_POST[$key['key_name']]);
            }

        } else {
            $valid = false;
            $vals = $key['values'];
            foreach ($vals as $val) {
                if ($_POST[$key['key_name']] == $val) {
                    $valid = true;
                }
            }
            if (!$valid) {
                unset($_POST[$key['key_name']]);
            }
        }
    }
}

if (isset($_POST['fbrev_active']) && isset($_GET['fbrev_active'])) {
    update_option('fbrev_active', ($_GET['fbrev_active'] == '1' ? '1' : '0'));
}

if (isset($_POST['fbrev_setting'])) {
    update_option('fbrev_app_id', $_POST['fbrev_app_id']);
    update_option('fbrev_app_secret', $_POST['fbrev_app_secret']);
}

wp_register_style('rplg_setting_css', plugins_url('/static/css/rplg-setting.css', __FILE__));
wp_enqueue_style('rplg_setting_css', plugins_url('/static/css/rplg-setting.css', __FILE__));

wp_enqueue_script('jquery');

$tab              = isset($_GET['fbrev_tab']) && strlen($_GET['fbrev_tab']) > 0 ? $_GET['fbrev_tab'] : 'about';
$fbrev_app_id     = get_option('fbrev_app_id');
$fbrev_app_secret = get_option('fbrev_app_secret');
$fbrev_enabled    = get_option('fbrev_active') == '1';
?>

<span class="rplg-version"><?php echo fbrev_i('Free Version: %s', esc_html(FBREV_VERSION)); ?></span>

<div class="rplg-setting">

    <div class="rplg-page-title">
        <span style="font-weight:700;color:#3b5998">Facebook</span> Reviews Widget
    </div>

    <div class="rplg-settings-workspace">

        <div data-nav-tabs="">
            <div class="nav-tab-wrapper">
                <a href="#about"     class="nav-tab<?php if ($tab == 'about')     { ?> nav-tab-active<?php } ?>">About</a>
                <a href="#setting"   class="nav-tab<?php if ($tab == 'setting')   { ?> nav-tab-active<?php } ?>">Settings</a>
                <a href="#shortcode" class="nav-tab<?php if ($tab == 'shortcode') { ?> nav-tab-active<?php } ?>">Shortcode Builder</a>
                <a href="#support"   class="nav-tab<?php if ($tab == 'support')   { ?> nav-tab-active<?php } ?>">Support</a>
            </div>

            <div id="about" class="tab-content" style="display:<?php echo $tab == 'about' ? 'block' : 'none'?>;">
                <h3>Facebook Reviews Widget for WordPress</h3>
                <div class="rplg-flex-row">
                    <div class="rplg-flex-col">
                        <span>Facebook Reviews plugin is an easy and fast way to integrate Facebook business reviews right into your WordPress website. This plugin works instantly and show Facebook reviews in sidebar widget.</span>
                        <p>Please see Introduction Video to understand how it works. Also you can find most common answers and solutions for most common questions and issues in next tabs.</p>
                        <div class="rplg-alert rplg-alert-success">
                            <strong>Try more features in the Business version</strong>: Merge Google, Facebook and Yelp reviews, Beautiful themes (Slider, Grid, Trust Badges), Shortcode support, Rich Snippets, Rating filter, Any sorting, Include/Exclude words filter, Hide/Show any elements, Priority support and many others.
                            <a class="button-primary button" href="https://richplugins.com/business-reviews-bundle-wordpress-plugin" target="_blank" style="margin-left:10px">Upgrade to Business</a>
                        </div>
                        <br>
                        <div class="rplg-socials">
                            <div id="fb-root"></div>
                            <script>(function(d, s, id) {
                              var js, fjs = d.getElementsByTagName(s)[0];
                              if (d.getElementById(id)) return;
                              js = d.createElement(s); js.id = id;
                              js.src = "//connect.facebook.net/en_EN/sdk.js#xfbml=1&version=v2.6&appId=1501100486852897";
                              fjs.parentNode.insertBefore(js, fjs);
                            }(document, 'script', 'facebook-jssdk'));</script>
                            <div class="fb-like" data-href="https://richplugins.com/" data-layout="button_count" data-action="like" data-show-faces="true" data-share="false"></div>
                            <a href="https://twitter.com/richplugins?ref_src=twsrc%5Etfw" class="twitter-follow-button" data-show-count="false">Follow @richplugins</a><script async src="https://platform.twitter.com/widgets.js" charset="utf-8"></script>
                            <div class="g-plusone" data-size="medium" data-annotation="inline" data-width="200" data-href="https://plus.google.com/101080686931597182099"></div>
                            <script type="text/javascript">
                                window.___gcfg = { lang: 'en-US' };
                                (function () {
                                    var po = document.createElement('script');
                                    po.type = 'text/javascript';
                                    po.async = true;
                                    po.src = 'https://apis.google.com/js/plusone.js';
                                    var s = document.getElementsByTagName('script')[0];
                                    s.parentNode.insertBefore(po, s);
                                })();
                            </script>
                        </div>
                    </div>
                    <div class="rplg-flex-col">
                        <iframe width="100%" height="315" src="https://www.youtube.com/embed/o0HV-bJ6_qE" frameborder="0" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                    </div>
                </div>
            </div>

            <div id="setting" class="tab-content" style="display:<?php echo $tab == 'setting' ? 'block' : 'none'?>;">
                <h3>General Settings</h3>
                <form method="POST" action="?page=fbrev&amp;fbrev_tab=setting&amp;fbrev_active=<?php echo (string)((int)($fbrev_enabled != true)); ?>">
                    <?php wp_nonce_field('fbrev-wpnonce_fbrev_active', 'fbrev-form_nonce_fbrev_active'); ?>
                    <div class="rplg-field">
                        <div class="rplg-field-label">
                            <label>The plugin is currently <b><?php echo $fbrev_enabled ? 'enabled' : 'disabled' ?></b></label>
                        </div>
                        <div class="wp-review-field-option">
                            <input type="submit" name="fbrev_active" class="button" value="<?php echo $fbrev_enabled ? fbrev_i('Disable') : fbrev_i('Enable'); ?>" />
                        </div>
                    </div>
                </form>
                <div id="debug_info" class="rplg-field">
                    <div class="rplg-field-label">
                        <label>DEBUG INFORMATION</label>
                    </div>
                    <div class="wp-review-field-option">
                        <input type="button" value="Copy Debug Information" name="reset_all" onclick="window.rplg_debug_info.select();document.execCommand('copy');window.rplg_debug_msg.innerHTML='Debug Information copied, please paste it to your email to support';" class="button" />
                        <textarea id="rplg_debug_info" style="display:block;width:30em;height:100px;margin-top:10px" onclick="window.rplg_debug_info.select();document.execCommand('copy');window.rplg_debug_msg.innerHTML='Debug Information copied, please paste it to your email to support';" readonly><?php rplg_debug(FBREV_VERSION, fbrev_options(), 'widget_fbrev_widget'); ?></textarea>
                        <p id="rplg_debug_msg"></p>
                    </div>
                </div>
            </div>

            <div id="shortcode" class="tab-content" style="display:<?php echo $tab == 'shortcode' ? 'block' : 'none'?>;">
                <h3>Shortcode Builder is available in the Business version of the plugin</h3>
                <a href="https://richplugins.com/business-reviews-bundle-wordpress-plugin" target="_blank" style="color:#00bf54;font-size:16px;text-decoration:underline;"><?php echo fbrev_i('Upgrade to Business'); ?></a>
            </div>

            <div id="support" class="tab-content" style="display:<?php echo $tab == 'support' ? 'block' : 'none'?>;">
                <h3>Most Common Questions</h3>
                <div class="rplg-flex-row">
                    <div class="rplg-flex-col">
                        <div class="rplg-support-question">
                            <h3>How many reviews the plugin shows?</h3>
                            <p>The plugin uses a Facebook Graph API to show your reviews and there is no limitation on the number of reviews, like in Google or Yelp. The plugin shows all Facebook reviews.</p>
                        </div>
                    </div>
                    <div class="rplg-flex-col">
                        <div class="rplg-support-question">
                            <h3>I can't connect my Facebook Page.</h3>
                            <p>Please check:</p>
                            <ul>
                                <li>Your Facebook account has an admin right for the page;</li>
                                <li>Your browser supports (has enabled) Cookies for external websites;</li>
                                <li>Your Facebook page is public and visible.</li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="rplg-flex-row">
                    <div class="rplg-flex-col">
                        <div class="rplg-support-question">
                            <h3>When I connect Facebook, the popup closed and there's needed FB page(s)</h3>
                            <p>Try to remove our integration <b>WidgetPack</b> from Facebook integration list <a href="https://facebook.com/settings?tab=business_tools" target="_blank">https://facebook.com/settings?tab=business_tools</a> and then re-connect Facebook page(s). You should see the popup where you need to allow all permission requests.</p>
                        </div>
                    </div>
                    <div class="rplg-flex-col">
                        <div class="rplg-support-question">
                            <h3>I have error message: <b style="color:red">Error validating access token: The session has been invalidated...</b></h3>
                            <p>The plugin uses a Facebook Graph API to show your reviews and if connected FB account changed the password or invalidate the session, such error message will appear. Please re-connect your Facebook page(s) in the widget.</p>
                        </div>
                    </div>
                </div>
                <div class="rplg-flex-row">
                    <div class="rplg-flex-col">
                        <div class="rplg-support-question">
                            <h3>If you need support</h3>
                            <p>You can contact us directly by email <a href="mailto:support@richplugins.com">support@richplugins.com</a> and would be great and save us a lot of time if each request to the support will contain the following data:</p>
                            <ul>
                                <li><b>1.</b> Clear and understandable description of the issue;</li>
                                <li><b>2.</b> Direct links to your reviews on Facebook;</li>
                                <li><b>3.</b> Link to the page of your site where the plugin installed;</li>
                                <li><b>4.</b> Better if you attach a screenshot(s) (or screencast) how you determine the issue;</li>
                                <li><b>5. The most important:</b> please always copy & paste the DEBUG INFORMATION from the <b>Settings</b> tab.</li>
                            </ul>
                        </div>
                    </div>
                    <div class="rplg-flex-col">
                        <div class="rplg-support-question">
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

</div>
<script type="text/javascript">
jQuery(document).ready(function($) {
    $('a.nav-tab').on('click', function(e)  {
        var $this = $(this), activeId = $this.attr('href');
        $(activeId).show().siblings('.tab-content').hide();
        $this.addClass('nav-tab-active').siblings().removeClass('nav-tab-active');
        e.preventDefault();
    });
});
</script>