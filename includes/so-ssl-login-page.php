<?php
/**
 * Login Page Enhancements for So SSL Plugin
 *
 * This file handles login page enhancements when two-factor authentication is active.
 */

class So_SSL_Login_Page {

    /**
     * Initialize login page enhancements
     */
    public static function init() {
        // Only proceed if 2FA is enabled
        if (!get_option('so_ssl_enable_2fa', 0)) {
            return;
        }

        // Enqueue styles and scripts
        add_action('login_enqueue_scripts', array(__CLASS__, 'enqueue_login_assets'));
        
        // Modify login form
        add_action('login_form', array(__CLASS__, 'enhance_login_form'));
        
        // Modify login message
        add_filter('login_message', array(__CLASS__, 'enhance_login_message'));
    }

    /**
     * Enqueue login page assets
     */
    public static function enqueue_login_assets() {
        // Enqueue styles
        wp_enqueue_style('so-ssl-login', SO_SSL_URL . 'assets/css/so-ssl-login.css', array(), SO_SSL_VERSION);
        
        // Enqueue scripts
        wp_enqueue_script('so-ssl-login', SO_SSL_URL . 'assets/js/so-ssl-login.js', array('jquery'), SO_SSL_VERSION, true);
    }

    /**
     * Enhance login form
     */
    public static function enhance_login_form() {
        // Check if 2FA is active on the login form
        if (isset($_SESSION['so_ssl_2fa_required']) && $_SESSION['so_ssl_2fa_required']) {
            // Add custom classes to 2FA code field
            ?>
            <script type="text/javascript">
                jQuery(document).ready(function($) {
                    // Add custom classes to the 2FA field
                    $('input[name="so_ssl_2fa_code"]').addClass('so-ssl-2fa-input');
                    
                    // Add custom label
                    $('label[for="so_ssl_2fa_code"]').text('Authentication Code:');
                });
            </script>
            <?php
        }
    }

    /**
     * Enhance login message
     *
     * @param string $message The login message
     * @return string Enhanced login message
     */
    public static function enhance_login_message($message) {
        // Check if 2FA is active on the login form
        if (isset($_SESSION['so_ssl_2fa_required']) && $_SESSION['so_ssl_2fa_required']) {
            $method = get_option('so_ssl_2fa_method', 'email');
            
            // Create enhanced message based on authentication method
            if ($method === 'email') {
                $message = '<div class="message">' . 
                    '<p><strong>' . __('Two-Factor Authentication Required', 'so-ssl') . '</strong></p>' . 
                    '<p>' . __('Please enter the verification code sent to your email.', 'so-ssl') . '</p>' . 
                '</div>';
            } else {
                $message = '<div class="message">' . 
                    '<p><strong>' . __('Two-Factor Authentication Required', 'so-ssl') . '</strong></p>' . 
                    '<p>' . __('Please enter the verification code from your authenticator app.', 'so-ssl') . '</p>' . 
                '</div>';
            }
        }
        
        return $message;
    }
}

// Initialize the class
So_SSL_Login_Page::init();
