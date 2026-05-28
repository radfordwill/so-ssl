<?php
/**
 * Plugin Name: So SSL
 * Plugin URI: https://willradford.com/so-ssl/
 * Description: Comprehensive SSL, security headers, privacy tools, administrator agreement, 2FA, login protection, session management, and login limiting for WordPress.
 * Version: 1.85
 * Author: Will Radford
 * Contributors: willradford
 * Author URI: https://willradford.com
 * Text Domain: so-ssl
 * License: GPLv3 or later
 * License URI: http://www.gnu.org/licenses/gpl-3.0.txt
 * Requires at least: 6.5
 * Tested up to: 7.0
 * Requires PHP: 7.0
 */

/*
 * So SSL is free software: you can redistribute it and/or modify it under the
 * terms of the GNU General Public License as published by the Free Software
 * Foundation, either version 3 of the License, or any later version.
 */

if (!defined('ABSPATH')) { exit; }

define('SO_SSL_VERSION', '1.85');
define('SO_SSL_FILE', __FILE__);
define('SO_SSL_DIR', plugin_dir_path(__FILE__));
define('SO_SSL_URL', plugin_dir_url(__FILE__));


/**
 * Reduce PHP version disclosure where the host allows it.
 *
 * The most reliable fix is expose_php = Off at the server/PHP configuration
 * level. This runtime cleanup removes the X-Powered-By header when WordPress
 * is able to control response headers.
 */
function so_ssl_hide_php_version_header() {
    if (!headers_sent() && function_exists('header_remove')) {
        header_remove('X-Powered-By');
    }
}
so_ssl_hide_php_version_header();
add_action('init', 'so_ssl_hide_php_version_header', 0);
add_action('send_headers', 'so_ssl_hide_php_version_header', 0);

require_once SO_SSL_DIR . 'includes/class-so-ssl-totp.php';
require_once SO_SSL_DIR . 'includes/class-so-ssl.php';

function so_ssl() {
    static $plugin = null;
    if ($plugin === null) {
        $plugin = new So_SSL_Plugin();
    }
    return $plugin;
}

register_activation_hook(__FILE__, array('So_SSL_Plugin', 'activate'));
register_deactivation_hook(__FILE__, array('So_SSL_Plugin', 'deactivate'));

so_ssl();
