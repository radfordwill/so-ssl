<?php
/**
 * Plugin Name: So SSL
 * Plugin URI: https://example.com/plugins/so-ssl
 * Description: A plugin to activate and enforce SSL on your WordPress site with additional security headers.
 * Version: 1.0.2
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
define('SO_SSL_VERSION', '1.0.2');

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
    // Activation code here...
    
    // Add SSL options with defaults
    add_option('so_ssl_force_ssl', 0);
    add_option('so_ssl_enable_hsts', 0);
    add_option('so_ssl_hsts_max_age', 31536000); // Default: 1 year
    add_option('so_ssl_hsts_subdomains', 0);
    add_option('so_ssl_hsts_preload', 0);
    
    // Add X-Frame-Options with default (SAMEORIGIN)
    add_option('so_ssl_enable_xframe', 1);
    add_option('so_ssl_xframe_option', 'sameorigin');
    
    // Add CSP Frame-Ancestors options with defaults
    add_option('so_ssl_enable_csp_frame_ancestors', 0);
    add_option('so_ssl_csp_frame_ancestors_option', 'none');
    add_option('so_ssl_csp_include_self', 0);
    add_option('so_ssl_csp_frame_ancestors_domains', '');
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
 * Begins execution of the plugin.
 */
function run_so_ssl() {
    $plugin = new So_SSL();
    $plugin->run();
}
run_so_ssl();
