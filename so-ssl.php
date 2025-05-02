<?php
/**
 * Plugin Name: So SSL
 * Plugin URI: https://github.com/radfordwill/so-ssl
 * Description: A plugin to activate and enforce SSL on your WordPress site with additional security headers.
 * Version: 1.4.4
 * Author: Will Radford
 * Author URI: https://github.com/radfordwill/
 * License: GPL-3.0+
 * License URI: http://www.gnu.org/licenses/gpl-3.0.txt
 * Text Domain: so-ssl
 * Domain Path: /languages
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Current plugin version.
 */
define('SO_SSL_VERSION', '1.4.4');

/**
 * Plugin path.
 */
define('SO_SSL_PATH', plugin_dir_path(__FILE__));

/**
 * Plugin URL.
 */
define('SO_SSL_URL', plugin_dir_url(__FILE__));

/**
 * The code that runs during plugin activation.
 */
function activate_so_ssl() {
    // SSL options
    add_option('so_ssl_force_ssl', 0);

    // HSTS options
    add_option('so_ssl_enable_hsts', 0);
    add_option('so_ssl_hsts_max_age', 31536000); // Default: 1 year
    add_option('so_ssl_hsts_subdomains', 0);
    add_option('so_ssl_hsts_preload', 0);

    // X-Frame-Options
    add_option('so_ssl_enable_xframe', 1);
    add_option('so_ssl_xframe_option', 'sameorigin');

    // CSP Frame-Ancestors
    add_option('so_ssl_enable_csp_frame_ancestors', 0);
    add_option('so_ssl_csp_frame_ancestors_option', 'none');
    add_option('so_ssl_csp_include_self', 0);
    add_option('so_ssl_csp_frame_ancestors_domains', '');

    // Referrer Policy
    add_option('so_ssl_enable_referrer_policy', 0);
    add_option('so_ssl_referrer_policy_option', 'strict-origin-when-cross-origin');

    // Content Security Policy
    add_option('so_ssl_enable_csp', 0);
    add_option('so_ssl_csp_mode', 'report-only');
    add_option('so_ssl_csp_default_src', "'self'");
    add_option('so_ssl_csp_script_src', "'self'");
    add_option('so_ssl_csp_style_src', "'self'");
    add_option('so_ssl_csp_img_src', "'self'");
    add_option('so_ssl_csp_connect_src', "'self'");
    add_option('so_ssl_csp_font_src', "'self'");
    add_option('so_ssl_csp_object_src', "'none'");
    add_option('so_ssl_csp_media_src', "'self'");
    add_option('so_ssl_csp_frame_src', "'self'");
    add_option('so_ssl_csp_base_uri', "'self'");
    add_option('so_ssl_csp_form_action', "'self'");
    add_option('so_ssl_csp_upgrade_insecure_requests', "");

    // Permissions Policy
    add_option('so_ssl_enable_permissions_policy', 0);

    // Two-Factor Authentication
    add_option('so_ssl_enable_2fa', 0);
    add_option('so_ssl_2fa_user_roles', array('administrator'));
    add_option('so_ssl_2fa_method', 'email');

    // Login Protection
    add_option('so_ssl_disable_weak_passwords', 0);

    // Define default permissions
    $permissions = array(
        'accelerometer', 'ambient-light-sensor', 'autoplay', 'battery', 'camera',
        'display-capture', 'document-domain', 'encrypted-media', 'execution-while-not-rendered',
        'execution-while-out-of-viewport', 'fullscreen', 'geolocation', 'gyroscope',
        'microphone', 'midi', 'navigation-override', 'payment', 'picture-in-picture',
        'publickey-credentials-get', 'screen-wake-lock', 'sync-xhr', 'usb', 'web-share',
        'xr-spatial-tracking'
    );

    // Set default values for each permission
    foreach ($permissions as $permission) {
        $option_name = 'so_ssl_permissions_policy_' . str_replace('-', '_', $permission);
        $default_value = ($permission === 'picture-in-picture') ? '*' : 'self';
        add_option($option_name, $default_value);
    }

    // Cross-Origin Policies
    add_option('so_ssl_enable_cross_origin_embedder_policy', 0);
    add_option('so_ssl_cross_origin_embedder_policy_value', 'require-corp');
    add_option('so_ssl_enable_cross_origin_opener_policy', 0);
    add_option('so_ssl_cross_origin_opener_policy_value', 'same-origin');
    add_option('so_ssl_enable_cross_origin_resource_policy', 0);
    add_option('so_ssl_cross_origin_resource_policy_value', 'same-origin');
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_so_ssl() {
    // Deactivation code here...
}

register_activation_hook(__FILE__, 'activate_so_ssl');
register_deactivation_hook(__FILE__, 'deactivate_so_ssl');

/**
 * The core plugin class.
 */
require_once SO_SSL_PATH . 'includes/class-so-ssl.php';

/**
 * User Sessions Management functionality.
 */
require_once SO_SSL_PATH . 'includes/so-ssl-user-sessions.php';

/**
 * Login Limiting functionality.
 */
require_once SO_SSL_PATH . 'includes/so-ssl-login-limit.php';

/**
 * Begins execution of the plugin.
 */
function run_so_ssl() {
    $plugin = new So_SSL();
    $plugin->run();
}
run_so_ssl();
