<?php
/**
 * So SSL Privacy Compliance Module - Using Admin Agreement Method
 *
 * This file implements a GDPR and US privacy law compliance module for the So SSL plugin.
 * Updated to use the same modal approach as the Admin Agreement class.
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

class So_SSL_Privacy_Compliance {

	/**
	 * Initialize the privacy compliance module
	 */
	public static function init() {
		// Only proceed if privacy compliance is enabled
		if ( ! get_option( 'so_ssl_enable_privacy_compliance', 0 ) ) {
			return;
		}

		// Register admin settings
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );

		// Handle form processing
		add_action( 'init', array( __CLASS__, 'process_privacy_form' ), 1 );

		// Check if user has acknowledged the privacy notice
		add_action( 'template_redirect', array(
			__CLASS__,
			'check_privacy_acknowledgment'
		), 20 );
		add_action( 'admin_init', array(
			__CLASS__,
			'check_privacy_acknowledgment'
		), 20 );

		// Add the privacy page - using 'admin_menu' priority 5 like admin agreement
		add_action( 'admin_menu', array( __CLASS__, 'add_privacy_menu' ), 5 );

		// Add AJAX handler for saving acknowledgment
		add_action( 'wp_ajax_so_ssl_save_privacy_acknowledgment', array(
			__CLASS__,
			'ajax_save_privacy_acknowledgment'
		) );

		// Add hook for admin scripts (for TinyMCE editor)
		add_action( 'admin_enqueue_scripts', array(
			__CLASS__,
			'enqueue_admin_scripts'
		) );

		/**
		 * Emergency override for admin agreement
		 * This must be loaded very early to catch the query parameter
		 */
		add_action( 'init', function () {
			// Check for emergency override
			if ( isset( $_GET['disable_so_ssl_agreement'] ) && $_GET['disable_so_ssl_agreement'] == '1' ) {
				// Verify user is an admin
				if ( current_user_can( 'manage_options' ) ) {
					// Verify nonce for security
					if ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'disable_so_ssl_agreement' ) ) {
						// Disable admin agreement
						update_option( 'so_ssl_enable_admin_agreement', 0 );

						// Redirect to admin with success message
						wp_safe_redirect( admin_url( 'options-general.php?page=so-ssl&agreement_disabled=1' ) );
						exit;
					} else {
						// Invalid nonce
						wp_die( 'Security check failed. Please try again with a valid link.', 'Security Error', array( 'response' => 403 ) );
					}
				}
			}
		}, 1 );
	}

	/**
	 * Process privacy form submission (non-AJAX fallback)
	 */
	public static function process_privacy_form() {
		// Only process if form was submitted via POST
		if ( ! isset( $_POST['so_ssl_privacy_fallback'] ) || ! isset( $_POST['so_ssl_privacy_nonce'] ) ) {
			return;
		}

		// Verify nonce
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['so_ssl_privacy_nonce'] ) ), 'so_ssl_privacy_acknowledgment' ) ) {
			wp_die( esc_html_e( 'Security verification failed.', 'so-ssl' ) );

			return;
		}

		// Check if accepted
		if ( ! isset( $_POST['so_ssl_privacy_accept'] ) || $_POST['so_ssl_privacy_accept'] != '1' ) {
			wp_die( esc_html_e( 'You must accept the privacy notice to continue.', 'so-ssl' ) );

			return;
		}

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_die( esc_html_e( 'You must be logged in.', 'so-ssl' ) );

			return;
		}

		// Save acknowledgment
		update_user_meta( $user_id, 'so_ssl_privacy_acknowledged', time() );

		// Clear caches
		clean_user_cache( $user_id );
		wp_cache_delete( $user_id, 'user_meta' );

		// Redirect
		$redirect = isset( $_POST['so_ssl_redirect_url'] ) ? esc_url_raw( wp_unslash( $_POST['so_ssl_redirect_url'] ) ) : admin_url();
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Check if user has acknowledged the privacy notice
	 * Using the same approach as admin agreement
	 */
	public static function check_privacy_acknowledgment() {
		// Skip for AJAX, Cron, CLI requests
		if ( wp_doing_ajax() || wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return;
		}

		// Only check for logged-in users
		if ( ! is_user_logged_in() ) {
			return;
		}

		// Skip logout requests
		if (
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			isset( $_GET['action'] )
			&&
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			sanitize_key( wp_unslash( $_GET['action'] ) )
			=== 'logout' ) {
			return;
		}

		// Exception for the privacy page itself
		// Skip logout requests
		if (
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			isset( $_GET['page'] )
			&&
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			sanitize_key( wp_unslash( $_GET['page'] ) )
			=== 'so-ssl-privacy' ) {
			return;
		}

		// Get current user
		$current_user = wp_get_current_user();
		$user_id      = $current_user->ID;

		// Check if admins are exempt
		$exempt_admins = get_option( 'so_ssl_privacy_exempt_admins', true );
		if ( $exempt_admins && current_user_can( 'manage_options' ) ) {
			return;
		}

		// Check if original admin (user ID 1) is exempt
		$exempt_original_admin = get_option( 'so_ssl_privacy_exempt_original_admin', true );
		if ( $exempt_original_admin && $user_id === 1 ) {
			return;
		}

		// Check user role requirements
		$required_roles      = get_option( 'so_ssl_privacy_required_roles', array(
			'subscriber',
			'contributor',
			'author',
			'editor'
		) );
		$user_requires_check = false;

		foreach ( $current_user->roles as $role ) {
			if ( in_array( $role, $required_roles ) ) {
				$user_requires_check = true;
				break;
			}
		}

		if ( ! $user_requires_check ) {
			return;
		}

		// Check if user has already acknowledged
		$acknowledgment = get_user_meta( $user_id, 'so_ssl_privacy_acknowledged', true );
		$expiry_days    = intval( get_option( 'so_ssl_privacy_expiry_days', 30 ) );

		// Check if acknowledgment has expired or doesn't exist
		if ( empty( $acknowledgment ) || ( time() - intval( $acknowledgment ) ) > ( $expiry_days * DAY_IN_SECONDS ) ) {
			// Display notice and overlay like admin agreement does
			if ( is_admin() ) {
				add_action( 'admin_notices', array(
					__CLASS__,
					'display_privacy_notice'
				) );
				add_action( 'admin_footer', array(
					__CLASS__,
					'add_privacy_overlay_script'
				) );
			} else {
				// For frontend, use footer
				add_action( 'wp_footer', array(
					__CLASS__,
					'add_privacy_overlay_script'
				) );
			}
		}
	}

	/**
	 * Display privacy notice (similar to admin agreement notice)
	 */
	public static function display_privacy_notice() {
		$privacy_url = admin_url( 'admin.php?page=so-ssl-privacy' );

		// Custom CSS to style the notice (same style as admin agreement)
		$custom_css = "
        .so-ssl-privacy-notice {
            background-color: #f0f6fc;
            border-left: 4px solid #2271b1;
            padding: 20px;
            margin: 20px 0;
            box-shadow: 0 1px 4px rgba(0,0,0,0.07);
            position: relative;
            border-radius: 0 3px 3px 0;
        }
        .so-ssl-privacy-notice h3 {
            margin-top: 0;
            color: #2271b1;
            font-size: 16px;
            font-weight: 600;
            display: flex;
            align-items: center;
            line-height: 1.4;
        }
        .so-ssl-privacy-notice h3 .dashicons {
            margin-right: 8px;
            color: #2271b1;
            font-size: 20px;
            width: 20px;
            height: 20px;
        }
        .so-ssl-privacy-notice p {
            margin: 0.5em 0;
            color: #1d2327;
            font-size: 14px;
        }
        .so-ssl-privacy-actions {
            margin-top: 15px;
        }
        .so-ssl-privacy-notice .button-primary {
            background: #2271b1;
            border-color: #2271b1;
            color: #fff;
            text-decoration: none;
            padding: 6px 15px;
            transition: all 0.2s ease;
        }
        .so-ssl-privacy-notice .button-primary:hover {
            background: #135e96;
            border-color: #135e96;
        }
        ";

		// Output the CSS
		echo '<style>' . wp_kses_post( $custom_css ) . '</style>';

		// Output the notice
		echo '<div class="so-ssl-privacy-notice">';
		echo '<h3><span class="dashicons dashicons-privacy"></span>' . esc_html__( 'Privacy Acknowledgment Required', 'so-ssl' ) . '</h3>';
		echo '<p>' . esc_html__( 'A privacy acknowledgment is required before using this site. Please review and accept the privacy notice to continue.', 'so-ssl' ) . '</p>';
		echo '<div class="so-ssl-privacy-actions">';
		echo '<a href="' . esc_url( $privacy_url ) . '" class="button button-primary">' . esc_html__( 'View & Accept Privacy Notice', 'so-ssl' ) . '</a>';
		echo '</div>';
		echo '</div>';

	}

	/**
	 * Add JavaScript to overlay content until privacy is acknowledged
	 * Using the same approach as admin agreement
	 */
	public static function add_privacy_overlay_script() {
		?>
        <style>

            #so-ssl-privacy-overlay {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(255, 255, 255, 0.95);
                z-index: 999999;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            #so-ssl-privacy-content {
                background: white;
                padding: 30px;
                max-width: 600px;
                text-align: center;
                box-shadow: 0 0 20px rgba(0, 0, 0, 0.2);
                border-radius: 5px;
            }

            #so-ssl-privacy-content h2 {
                color: #2271b1;
                margin-bottom: 20px;
            }

            #so-ssl-privacy-content .button-primary {
                margin: 0 5px;
            }

            /* Adjust for admin bar if present */
            body.admin-bar #so-ssl-privacy-overlay {
                top: 32px;
            }

            @media screen and (max-width: 782px) {
                body.admin-bar #so-ssl-privacy-overlay {
                    top: 46px;
                }
            }
        </style>

        <script>
            jQuery(document).ready(function ($) {
                // Create overlay elements
                var $overlay = $('<div id="so-ssl-privacy-overlay"></div>');
                var $content = $('<div id="so-ssl-privacy-content"></div>');

                $content.html(
                    '<h2><?php echo esc_js( __( 'Privacy Acknowledgment Required', 'so-ssl' ) ); ?></h2>' +
                    '<p><?php echo esc_js( __( 'You must accept the privacy notice before accessing this site.', 'so-ssl' ) ); ?></p>' +
                    '<p>' +
                    '<a href="<?php echo esc_url( admin_url( 'admin.php?page=so-ssl-privacy' ) ); ?>" class="button button-primary"><?php echo esc_js( __( 'View & Accept Privacy Notice', 'so-ssl' ) ); ?></a> ' +
                    '<a href="<?php echo esc_url( wp_logout_url( home_url() ) ); ?>" class="button"><?php echo esc_js( __( 'Logout', 'so-ssl' ) ); ?></a>' +
                    '</p>'
                );

                $overlay.append($content);
                $('body').append($overlay);
            });
        </script>
		<?php
	}

	/**
	 * Add agreement menu
	 */
	public static function add_privacy_menu() {
		add_submenu_page(
			'so-ssl',
			__( 'Privacy Agreement', 'so-ssl' ),
			__( 'Privacy Agreement', 'so-ssl' ),
			'read', // Allow any logged-in user to access the privacy page
			'so-ssl-privacy',
			array( __CLASS__, 'display_privacy_page' ),
			999
		);
		// remove_submenu_page results in an error that can't be solved. DEBUG mode only
		// Hide this from the menu - it's only for direct access
		//remove_submenu_page('so-ssl','so-ssl-privacy');
	}

	/**
	 * Display privacy page (similar to admin agreement page)
	 */
	public static function display_privacy_page() {
		// Check user capabilities - any logged in user can view
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html_e( 'You must be logged in to view this page.', 'so-ssl' ) );

			return;
		}

		$page_title    = get_option( 'so_ssl_privacy_page_title', 'Privacy Acknowledgment Required' );
		$notice_text   = get_option( 'so_ssl_privacy_notice_text', '' );
		$checkbox_text = get_option( 'so_ssl_privacy_checkbox_text', '' );

		// Get the referring page (for return after acceptance)
		$referer      = isset( $_SERVER['HTTP_REFERER'] ) ? wp_unslash( sanitize_key( $_SERVER['HTTP_REFERER'] ) ) : '';
		$redirect_url = ! empty( $referer ) ? $referer : ( is_admin() ? admin_url() : home_url() );

		// Add CSS for the privacy page - using the same style as admin agreement
		$custom_css = '
        
            .so-ssl-privacy-wrap {
                max-width: 800px;
                margin: 40px auto;
            }
            .so-ssl-privacy-container {
                background: #fff;
                border: 1px solid #c3c4c7;
                border-radius: 4px;
                box-shadow: 0 1px 4px rgba(0, 0, 0, 0.07);
                padding: 25px;
                margin-top: 20px;
            }
            .so-ssl-privacy-header {
                border-bottom: 1px solid #c3c4c7;
                margin-bottom: 20px;
                padding-bottom: 15px;
            }
            .so-ssl-privacy-header h1 {
                color: #2271b1;
                font-size: 24px;
                font-weight: 600;
                margin: 0;
                padding: 0;
                display: flex;
                align-items: center;
            }
            .so-ssl-privacy-content {
                background: #f0f6fc;
                border-left: 4px solid #2271b1;
                padding: 20px;
                margin-bottom: 25px;
                color: #1d2327;
                line-height: 1.5;
            }
            .so-ssl-privacy-form {
                background: #f8f9fa;
                border: 1px solid #dcdcde;
                border-radius: 4px;
                padding: 20px;
                margin-bottom: 20px;
            }
            .so-ssl-privacy-checkbox {
                margin-bottom: 20px;
            }
            .so-ssl-privacy-checkbox input[type="checkbox"] {
                margin-right: 8px;
            }
            .so-ssl-privacy-checkbox label {
                font-weight: 500;
                color: #1d2327;
            }
            .so-ssl-privacy-actions button {
                background: #2271b1;
                border-color: #2271b1;
                color: #fff;
                padding: 8px 15px;
                height: auto;
                transition: all 0.2s ease;
            }
            .so-ssl-privacy-actions button:hover {
                background: #135e96;
            }
            .so-ssl-privacy-actions button:disabled {
                background: #c3c4c7 !important;
                border-color: #c3c4c7 !important;
                color: #50575e !important;
                cursor: not-allowed;
            }
            .so-ssl-privacy-message {
                padding: 10px 15px;
                margin-top: 15px;
                border-radius: 4px;
            }
            .so-ssl-privacy-message.success {
                background-color: #f0f8ee;
                border-left: 4px solid #46b450;
                color: #1d2327;
            }
            .so-ssl-privacy-message.error {
                background-color: #fcf0f1;
                border-left: 4px solid #dc3232;
                color: #1d2327;
            }
            .so-ssl-security-icon {
                margin-right: 10px;
                color: #2271b1;
                font-size: 20px;
                width: 20px;
                height: 20px;
            }
            .so-ssl-alternate-button {
                background: #f6f7f7;
                border: 1px solid #c3c4c7;
                color: #50575e;
                text-decoration: none;
                display: inline-block;
                padding: 6px 12px;
                border-radius: 3px;
                margin-left: 10px;
                transition: all 0.2s ease;
            }
            .so-ssl-alternate-button:hover {
                background: #f0f0f1;
                border-color: #8c8f94;
                color: #1d2327;
            }
        ';
// Output the CSS
		echo '<style>' . wp_kses_post( $custom_css ) . '</style>'; ?>
        <div class="wrap so-ssl-privacy-wrap">
            <div class="so-ssl-privacy-container">
                <div class="so-ssl-privacy-header">
                    <h1>
                        <span class="dashicons dashicons-privacy so-ssl-security-icon"></span>
						<?php echo esc_html( $page_title ); ?>
                    </h1>
                </div>

                <div class="so-ssl-privacy-content">
					<?php echo wp_kses_post( $notice_text ); ?>
                </div>

                <div class="so-ssl-privacy-form">
                    <form id="so-ssl-privacy-form" method="post" action="">
						<?php wp_nonce_field( 'so_ssl_privacy_acknowledgment', 'so_ssl_privacy_nonce' ); ?>
                        <input type="hidden" id="so_ssl_redirect_url"
                               name="so_ssl_redirect_url"
                               value="<?php echo esc_url( $redirect_url ); ?>">

                        <div class="so-ssl-privacy-checkbox">
                            <label>
                                <input type="checkbox"
                                       id="so_ssl_privacy_accept"
                                       name="so_ssl_privacy_accept" value="1"
                                       required>
								<?php echo esc_html( $checkbox_text ); ?>
                            </label>
                        </div>

                        <div class="so-ssl-privacy-actions">
                            <button type="submit" id="so_ssl_privacy_submit"
                                    class="button button-primary" disabled>
								<?php esc_html_e( 'Accept and Continue', 'so-ssl' ); ?>
                            </button>

                            <a href="<?php echo esc_url( wp_logout_url( home_url() ) ); ?>"
                               class="so-ssl-alternate-button">
								<?php esc_html_e( 'Disagree and Logout', 'so-ssl' ); ?>
                            </a>
                        </div>

                        <div id="so-ssl-privacy-message"
                             class="so-ssl-privacy-message"
                             style="display: none;"></div>

                        <!-- Fallback for when AJAX fails -->
                        <noscript>
                            <input type="hidden" name="so_ssl_privacy_fallback"
                                   value="1">
                            <style>#so_ssl_privacy_submit {
                                    display: none;
                                }</style>
                            <button type="submit"
                                    name="so_ssl_privacy_fallback_submit"
                                    class="button button-primary">
								<?php esc_html_e( 'Accept and Continue (No JavaScript)', 'so-ssl' ); ?>
                            </button>
                        </noscript>
                    </form>
                </div>
            </div>
        </div>

        <script>
            jQuery(document).ready(function ($) {
                console.log('Privacy form initialized');

                // Enable/disable submit button based on checkbox
                $('#so_ssl_privacy_accept').on('change', function () {
                    console.log('Checkbox changed:', $(this).is(':checked'));
                    $('#so_ssl_privacy_submit').prop('disabled', !$(this).is(':checked'));
                });

                // Handle form submission
                $('#so-ssl-privacy-form').on('submit', function (e) {
                    e.preventDefault();
                    console.log('Form submitted');

                    // Show loading state
                    $('#so_ssl_privacy_submit').prop('disabled', true).text('<?php esc_html_e( 'Processing...', 'so-ssl' ); ?>');

                    var formData = {
                        action: 'so_ssl_save_privacy_acknowledgment',
                        nonce: $('#so_ssl_privacy_nonce').val(),
                        accept: $('#so_ssl_privacy_accept').is(':checked') ? 1 : 0
                    };

                    console.log('Sending AJAX with data:', formData);
                    console.log('AJAX URL:', ajaxurl);

                    // Send AJAX request
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: formData,
                        success: function (response) {
                            console.log('AJAX success response:', response);

                            if (response.success) {
                                $('#so-ssl-privacy-message')
                                    .removeClass('error')
                                    .addClass('success')
                                    .html('<p>' + response.data.message + '</p>')
                                    .show();

                                // Log debug info if available
                                if (response.data.debug) {
                                    console.log('Debug info:', response.data.debug);
                                }

                                // Redirect to the original page after 1 second
                                setTimeout(function () {
                                    var redirectUrl = $('#so_ssl_redirect_url').val();
                                    console.log('Redirecting to:', redirectUrl);
                                    window.location.href = redirectUrl;
                                }, 1000);
                            } else {
                                console.log('AJAX error response:', response);

                                $('#so-ssl-privacy-message')
                                    .removeClass('success')
                                    .addClass('error')
                                    .html('<p>' + response.data.message + '</p>')
                                    .show();

                                // Reset button
                                $('#so_ssl_privacy_submit').prop('disabled', false).text('<?php esc_html_e( 'Accept and Continue', 'so-ssl' ); ?>');
                            }
                        },
                        error: function (xhr, textStatus, errorThrown) {
                            console.log('AJAX error:', textStatus, errorThrown);
                            console.log('Response:', xhr.responseText);

                            $('#so-ssl-privacy-message')
                                .removeClass('success')
                                .addClass('error')
                                .html('<p><?php esc_html_e( 'An error occurred. Please try again.', 'so-ssl' ); ?></p>')
                                .show();

                            // Reset button
                            $('#so_ssl_privacy_submit').prop('disabled', false).text('<?php esc_html_e( 'Accept and Continue', 'so-ssl' ); ?>');
                        }
                    });
                });
            });</script>
		<?php
	}

	/**
	 * AJAX handler for saving privacy acknowledgment - DEBUG VERSION
	 */
	public static function ajax_save_privacy_acknowledgment() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No security token provided.', 'so-ssl' ) ) );

			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, 'so_ssl_privacy_acknowledgment' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security verification failed.', 'so-ssl' ) ) );

			return;
		}

		// Verify user is logged in
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in to perform this action.', 'so-ssl' ) ) );

			return;
		}

		$user_id = get_current_user_id();

		// Get acceptance value
		$accept = isset( $_POST['accept'] ) ? absint( $_POST['accept'] ) : 0;

		if ( $accept !== 1 ) {
			wp_send_json_error( array( 'message' => __( 'You must accept the privacy notice to continue.', 'so-ssl' ) ) );

			return;
		}

		// Save acceptance to user meta
		$result = update_user_meta( $user_id, 'so_ssl_privacy_acknowledged', time() );

		// Verify it was saved
		$check = get_user_meta( $user_id, 'so_ssl_privacy_acknowledged', true );

		// Clear caches
		clean_user_cache( $user_id );
		wp_cache_delete( $user_id, 'user_meta' );

		wp_send_json_success( array(
			'message' => __( 'Privacy notice accepted. Redirecting...', 'so-ssl' ),
			'debug'   => array(
				'user_id' => $user_id,
				'saved'   => $result,
				'check'   => $check
			)
		) );
	}

	/**
	 * Enqueue admin scripts
	 */
	public static function enqueue_admin_scripts( $hook ) {
		// Only load on our plugin's settings page
		if ( strpos( $hook, 'so-ssl' ) !== false || $hook === 'settings_page_so-ssl' ) {
			wp_enqueue_editor();
			wp_enqueue_media();
		}
	}

	/**
	 * Register settings for privacy compliance
	 */
	public static function register_settings() {
		// Privacy Compliance settings
		register_setting(
			'so_ssl_options',
			'so_ssl_enable_privacy_compliance',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'intval',
				'default'           => 0,
			)
		);

		register_setting(
			'so_ssl_options',
			'so_ssl_privacy_page_title',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => 'Privacy Acknowledgment Required',
			)
		);

		register_setting(
			'so_ssl_options',
			'so_ssl_privacy_notice_text',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'wp_kses_post',
				'default'           => 'This site tracks certain information for security purposes including IP addresses, login attempts, and session data. By using this site, you acknowledge and consent to this data collection in accordance with our Privacy Policy and applicable data protection laws including GDPR and US privacy regulations.',
			)
		);

		register_setting(
			'so_ssl_options',
			'so_ssl_privacy_checkbox_text',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => 'I acknowledge and consent to the privacy notice above',
			)
		);

		register_setting(
			'so_ssl_options',
			'so_ssl_privacy_expiry_days',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'intval',
				'default'           => 30,
			)
		);

		register_setting(
			'so_ssl_options',
			'so_ssl_privacy_required_roles',
			array(
				'type'              => 'array',
				'sanitize_callback' => function ( $input ) {
					if ( ! is_array( $input ) ) {
						return array();
					}

					return array_map( 'sanitize_text_field', $input );
				},
				'default'           => array(
					'subscriber',
					'contributor',
					'author',
					'editor'
				),
			)
		);

		register_setting(
			'so_ssl_options',
			'so_ssl_privacy_exempt_admins',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'intval',
				'default'           => true,
			)
		);

		register_setting(
			'so_ssl_options',
			'so_ssl_privacy_exempt_original_admin',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'intval',
				'default'           => true,
			)
		);
	}
}

// Initialize the class
So_SSL_Privacy_Compliance::init();