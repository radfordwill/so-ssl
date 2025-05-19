<?php
/**
 * Login Page Enhancements for So SSL Plugin
 *
 * This file handles login page enhancements when two-factor authentication is active.
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

class So_SSL_Login_Page {

	/**
	 * Initialize login page enhancements
	 */
	public static function init() {
		// Only proceed if 2FA is enabled
		if ( ! get_option( 'so_ssl_enable_2fa', 0 ) ) {
			return;
		}

		// Enqueue styles and scripts
		add_action( 'login_enqueue_scripts', array(
			__CLASS__,
			'enqueue_login_assets'
		) );

		// Modify login form
		add_action( 'login_form', array( __CLASS__, 'enhance_login_form' ) );

		// Modify login message
		add_filter( 'login_message', array(
			__CLASS__,
			'enhance_login_message'
		) );
	}

	/**
	 * Enqueue login page assets with enhanced styles
	 */
	public static function enqueue_login_assets() {
		// Enqueue base styles
		wp_enqueue_style( 'so-ssl-login', SO_SSL_URL . 'assets/css/so-ssl-login.css', array(), SO_SSL_VERSION );

		// Enqueue scripts
		wp_enqueue_script( 'so-ssl-login', SO_SSL_URL . 'assets/js/so-ssl-login.js', array( 'jquery' ), SO_SSL_VERSION, true );

		// Add custom inline styles that match the plugin's color palette
		$custom_css = "
        /* Two-Factor Authentication Form Styling */
        .login form .so-ssl-2fa-input {
            font-size: 18px;
            padding: 8px 10px;
            border-color: #c3c4c7;
            border-radius: 3px;
            text-align: center;
            letter-spacing: 2px;
            font-family: monospace;
        }
        
        .login form .so-ssl-2fa-input:focus {
            border-color: #2271b1;
            box-shadow: 0 0 0 1px #2271b1;
        }
        
        .login #login_error {
            border-left-color: #d63638;
        }
        
        .login .message {
            border-radius: 3px;
            border-left: 4px solid #2271b1;
            background-color: #f0f6fc;
            padding: 12px 15px;
            margin-bottom: 20px;
        }
        
        .login .message strong {
            color: #2271b1;
            font-weight: 600;
        }
        
        /* Style for the 2FA input field */
        .login-form-2fa-code {
            position: relative;
            margin-bottom: 20px;
        }
        
        .login-form-2fa-code:before {
            content: '\\f332';
            font-family: dashicons;
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: #2271b1;
            font-size: 20px;
        }
        
        .login .button.button-primary {
            background: #2271b1;
            border-color: #2271b1;
            color: #fff;
            transition: all 0.2s;
        }
        
        .login .button.button-primary:hover {
            background: #135e96;
            border-color: #135e96;
        }
        
        /* Add an animated highlight effect to 2FA fields */
        @keyframes so-ssl-input-highlight {
            0% { border-color: #2271b1; }
            50% { border-color: #72aee6; }
            100% { border-color: #2271b1; }
        }
        
        .login form .so-ssl-2fa-input.active {
            animation: so-ssl-input-highlight 2s infinite;
        }
    ";

		wp_add_inline_style( 'so-ssl-login', $custom_css );
	}

	/**
	 * Enhance login form for 2FA
	 *
	 * @since    1.4.5
	 *
	 */
	public static function enhance_login_form() {
		// Check if 2FA is active on the login form
		if ( isset( $_SESSION['so_ssl_2fa_required'] ) && sanitize_text_field( $_SESSION['so_ssl_2fa_required'] ) ) {
			// Add custom classes to 2FA code field and enhance UI
			?>
            <script type="text/javascript">
                jQuery(document).ready(function ($) {
                    // Add custom classes to the 2FA field
                    $('input[name="so_ssl_2fa_code"]').addClass('so-ssl-2fa-input active');

                    // Add custom label and enhance layout
                    $('label[for="so_ssl_2fa_code"]').text('Authentication Code:');

                    // Add icon and styling to input container
                    $('input[name="so_ssl_2fa_code"]').parent().addClass('login-form-2fa-code');

                    // Auto-focus the 2FA field
                    $('input[name="so_ssl_2fa_code"]').focus();

                    // Format the code with a space after 3 digits
                    $('input[name="so_ssl_2fa_code"]').on('input', function () {
                        var value = $(this).val().replace(/[^0-9]/g, '');
                        if (value.length > 6) {
                            value = value.substr(0, 6);
                        }
                        if (value.length > 3) {
                            value = value.substr(0, 3) + ' ' + value.substr(3);
                        }
                        $(this).val(value);
                    });
                });
            </script>
			<?php
		}
	}

	/**
	 * Enhanced login message for 2FA
	 *
	 * @param string $message The login message
	 *
	 * @return string Enhanced login message
	 */
	public static function enhance_login_message( $message ) {
		// Check if 2FA is active on the login form
		if ( isset( $_SESSION['so_ssl_2fa_required'] ) && sanitize_text_field( $_SESSION['so_ssl_2fa_required'] ) ) {
			$method = get_option( 'so_ssl_2fa_method', 'email' );

			// Create enhanced message based on authentication method
			if ( $method === 'email' ) {
				$message = '<div class="message">' .
				           '<p><strong>' . __( 'Two-Factor Authentication Required', 'so-ssl' ) . '</strong></p>' .
				           '<p>' . __( 'Please enter the verification code sent to your email address. For your security, this code will expire in 10 minutes.', 'so-ssl' ) . '</p>' .
				           '</div>';
			} else {
				$message = '<div class="message">' .
				           '<p><strong>' . __( 'Two-Factor Authentication Required', 'so-ssl' ) . '</strong></p>' .
				           '<p>' . __( 'Please enter the verification code from your authenticator app (like Google Authenticator, Authy, or Microsoft Authenticator).', 'so-ssl' ) . '</p>' .
				           '</div>';
			}
		}

		return $message;
	}
}

// Initialize the class
So_SSL_Login_Page::init();
