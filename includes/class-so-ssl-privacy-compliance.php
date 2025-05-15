


<?php
/**
 * So SSL Privacy Compliance Module
 *
 * This file implements a GDPR and US privacy law compliance module for the So SSL plugin.
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
	die;
}

class So_SSL_Privacy_Compliance {

	/**
	 * Initialize the privacy compliance module
	 */
	public static function init() {
		// Only proceed if privacy compliance is enabled
		if (!get_option('so_ssl_enable_privacy_compliance', 0)) {
			return;
		}

		// Register admin settings
		add_action('admin_init', array(__CLASS__, 'register_settings'));

		// Add the privacy page - using 'admin_menu' priority 5 to ensure it's registered early
		add_action('admin_menu', array(__CLASS__, 'add_privacy_menu'), 5);

		// Check if user has acknowledged the privacy notice - lower priority to run after menu is registered
		add_action('admin_init', array(__CLASS__, 'check_privacy_acknowledgment'), 20);

		// Add AJAX handler for saving acknowledgment
		add_action('wp_ajax_so_ssl_save_privacy_acknowledgment', array(__CLASS__, 'ajax_save_privacy_acknowledgment'));

		// Add notice on plugins page to inform users about the emergency override
		add_action('admin_notices', array(__CLASS__, 'maybe_show_emergency_notice'));

		// Check for front-end pages
		add_action('template_redirect', array(__CLASS__, 'check_privacy_acknowledgment_frontend'), 20);
	}

	/**
	 * Show emergency notice on plugins page
	 */
	public static function maybe_show_emergency_notice() {
		$screen = get_current_screen();
		if ($screen && ($screen->id === 'plugins' || strpos($screen->id, 'so-ssl') !== false)) {
			echo '<div class="notice notice-info is-dismissible">';
			echo '<p><strong>So SSL Tip:</strong> If you ever get locked out due to the privacy compliance, you can use this URL to disable it: <code>' . admin_url('index.php?disable_so_ssl_privacy=1') . '</code></p>';
			echo '</div>';
		}
	}

	/**
	 * Check if user has acknowledged the privacy notice for admin pages
	 */
	public static function check_privacy_acknowledgment() {
		// Skip for AJAX, Cron, CLI, or admin-ajax.php requests
		if (wp_doing_ajax() || wp_doing_cron() || (defined('WP_CLI') && WP_CLI) ||
		    (isset($_SERVER['SCRIPT_FILENAME']) && strpos(sanitize_text_field(wp_unslash($_SERVER['SCRIPT_FILENAME'])), 'admin-ajax.php') !== false)) {
			return;
		}

		// Only check for logged-in users
		if (!is_user_logged_in()) {
			return;
		}

		// Exception for the privacy page itself
		if (isset($_GET['page']) && $_GET['page'] === 'so-ssl-privacy') {
			return;
		}

		// Skip if this is a logout request
		if (isset($_GET['action']) && $_GET['action'] === 'logout') {
			return;
		}

		// Get current user
		$current_user = wp_get_current_user();
		$user_id = $current_user->ID;

		// Check if admins are exempt
		$exempt_admins = get_option('so_ssl_privacy_exempt_admins', true);
		if ($exempt_admins && current_user_can('manage_options')) {
			return;
		}

		// Special check for the original admin (user ID 1)
		$exempt_original_admin = get_option('so_ssl_privacy_exempt_original_admin', true);
		if ($exempt_original_admin && $user_id === 1) {
			return;
		}

		// Check user role requirements
		$required_roles = get_option('so_ssl_privacy_required_roles', array('subscriber', 'contributor', 'author', 'editor'));
		$user_requires_check = false;

		foreach ($current_user->roles as $role) {
			if (in_array($role, $required_roles)) {
				$user_requires_check = true;
				break;
			}
		}

		if (!$user_requires_check) {
			return;
		}

		// Check if user has already acknowledged
		$acknowledgment = get_user_meta($user_id, 'so_ssl_privacy_acknowledged', true);
		$expiry_days = intval(get_option('so_ssl_privacy_expiry_days', 30));

		// Check if acknowledgment has expired or doesn't exist
		if (empty($acknowledgment) || (time() - intval($acknowledgment)) > ($expiry_days * DAY_IN_SECONDS)) {
			// Display privacy notice instead of redirecting
			add_action('admin_notices', array(__CLASS__, 'display_privacy_notice'));

			// Use JavaScript to control access to site content
			add_action('admin_footer', array(__CLASS__, 'add_privacy_overlay_script'));
		}
	}

	/**
	 * Check privacy acknowledgment for front-end pages
	 */
	public static function check_privacy_acknowledgment_frontend() {
		// Skip for AJAX, Cron, CLI requests
		if (wp_doing_ajax() || wp_doing_cron() || (defined('WP_CLI') && WP_CLI)) {
			return;
		}

		// Only check for logged-in users
		if (!is_user_logged_in()) {
			return;
		}

		// Skip if this is a logout request
		if (isset($_GET['action']) && $_GET['action'] === 'logout') {
			return;
		}

		// Get current user
		$current_user = wp_get_current_user();
		$user_id = $current_user->ID;

		// Check if admins are exempt
		$exempt_admins = get_option('so_ssl_privacy_exempt_admins', true);
		if ($exempt_admins && current_user_can('manage_options')) {
			return;
		}

		// Special check for the original admin (user ID 1)
		$exempt_original_admin = get_option('so_ssl_privacy_exempt_original_admin', true);
		if ($exempt_original_admin && $user_id === 1) {
			return;
		}

		// Check user role requirements
		$required_roles = get_option('so_ssl_privacy_required_roles', array('subscriber', 'contributor', 'author', 'editor'));
		$user_requires_check = false;

		foreach ($current_user->roles as $role) {
			if (in_array($role, $required_roles)) {
				$user_requires_check = true;
				break;
			}
		}

		if (!$user_requires_check) {
			return;
		}

		// Check if user has already acknowledged
		$acknowledgment = get_user_meta($user_id, 'so_ssl_privacy_acknowledged', true);
		$expiry_days = intval(get_option('so_ssl_privacy_expiry_days', 30));

		// Check if acknowledgment has expired or doesn't exist
		if (empty($acknowledgment) || (time() - intval($acknowledgment)) > ($expiry_days * DAY_IN_SECONDS)) {
			// Add privacy overlay to front-end
			add_action('wp_footer', array(__CLASS__, 'add_privacy_overlay_script'));
		}
	}

	/**
	 * Display privacy notice about acknowledgment requirement
	 */
	public static function display_privacy_notice() {
		$privacy_url = admin_url('admin.php?page=so-ssl-privacy');

		// Custom CSS to style the notice
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
        .so-ssl-privacy-notice .button-secondary {
            background: #f6f7f7;
            border: 1px solid #c3c4c7;
            color: #50575e;
            text-decoration: none;
            padding: 6px 12px;
            margin-left: 10px;
            transition: all 0.2s ease;
        }
        .so-ssl-privacy-notice .button-secondary:hover {
            background: #f0f0f1;
            border-color: #8c8f94;
            color: #1d2327;
        }
    ";

		// Output the CSS
		echo '<style>' . $custom_css . '</style>';

		// Output the enhanced notice
		echo '<div class="so-ssl-privacy-notice">';
		echo '<h3><span class="dashicons dashicons-shield"></span>' . esc_html__('Privacy Acknowledgment Required', 'so-ssl') . '</h3>';
		echo '<p>' . esc_html__('A privacy acknowledgment is required before using this site. Please review and accept the privacy notice to continue.', 'so-ssl') . '</p>';
		echo '<div class="so-ssl-privacy-actions">';
		echo '<a href="' . esc_url($privacy_url) . '" class="button button-primary">' . esc_html__('View & Accept Privacy Notice', 'so-ssl') . '</a>';
		echo '<a href="' . esc_url(home_url()) . '" class="button-secondary">' . esc_html__('Back to Home', 'so-ssl') . '</a>';
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Add JavaScript to overlay site content until privacy notice is accepted
	 */
	public static function add_privacy_overlay_script() {
		?>
        <script>
            jQuery(document).ready(function($) {
                // Create overlay elements
                var $overlay = $('<div id="so-ssl-privacy-overlay" style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(255,255,255,0.9); z-index: 99999; display: flex; align-items: center; justify-content: center;"></div>');
                var $content = $('<div style="background: white; padding: 30px; max-width: 600px; text-align: center; box-shadow: 0 0 20px rgba(0,0,0,0.2); border-radius: 5px;"></div>');

                $content.html('<h2>Privacy Acknowledgment Required</h2>' +
                    '<p>You must accept the privacy notice before accessing site content.</p>' +
                    '<p><a href="<?php echo esc_url(admin_url('admin.php?page=so-ssl-privacy')); ?>" class="button button-primary">View & Accept Privacy Notice</a> ' +
                    '<a href="<?php echo esc_url(home_url()); ?>" class="button">Back to Home</a></p>');

                $overlay.append($content);
                $('body').append($overlay);
            });
        </script>
		<?php
	}

	/**
	 * Add privacy menu
	 */
	public static function add_privacy_menu() {
		// Add a direct admin page (not under options-general.php)
		// This ensures it's available even if there are permission issues
		add_menu_page(
			__('Privacy Notice', 'so-ssl'),
			__('Privacy Notice', 'so-ssl'),
			'read', // Allow any logged-in user to access the privacy page
			'so-ssl-privacy',
			array(__CLASS__, 'display_privacy_page'),
			'dashicons-shield',
			998
		);

		// Hide this from the menu - it's only for direct access
		remove_menu_page('so-ssl-privacy');
	}

	/**
	 * Display privacy page
	 */
	public static function display_privacy_page() {
		// Check user capabilities - any logged in user can view
		if (!is_user_logged_in()) {
			wp_die(__('You must be logged in to view this page.', 'so-ssl'));
			return;
		}

		$page_title = get_option('so_ssl_privacy_page_title', 'Privacy Acknowledgment Required');
		$notice_text = get_option('so_ssl_privacy_notice_text', '');
		$checkbox_text = get_option('so_ssl_privacy_checkbox_text', '');

		// Get the referring page (for return after acceptance)
		$referer = isset($_SERVER['HTTP_REFERER']) ? wp_unslash($_SERVER['HTTP_REFERER']) : '';
		$redirect_url = !empty($referer) ? $referer : admin_url();

		// Add CSS for the privacy page - using the plugin's color palette
		?>
        <style>
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
                border-radius: 0 4px 4px 0;
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
                border-color: #135e96;
            }
            .so-ssl-privacy-actions button:disabled {
                background: #c3c4c7 !important;
                border-color: #c3c4c7 !important;
                color: #50575e !important;
                cursor: not-allowed;
            }
            .so-ssl-privacy-options {
                margin-top: 25px;
                padding-top: 15px;
                border-top: 1px solid #dcdcde;
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
            .so-ssl-emergency-button {
                background: #f6f7f7;
                border: 1px solid #c3c4c7;
                color: #50575e;
                text-decoration: none;
                display: inline-block;
                padding: 6px 12px;
                border-radius: 3px;
                margin-top: 10px;
                transition: all 0.2s ease;
            }
            .so-ssl-emergency-button:hover {
                background: #f0f0f1;
                border-color: #8c8f94;
                color: #1d2327;
            }
        </style>

        <div class="wrap so-ssl-privacy-wrap">
            <div class="so-ssl-privacy-container">
                <div class="so-ssl-privacy-header">
                    <h1>
                        <span class="dashicons dashicons-shield so-ssl-security-icon"></span>
						<?php echo esc_html($page_title); ?>
                    </h1>
                </div>

                <div class="so-ssl-privacy-content">
					<?php echo wp_kses_post($notice_text); ?>
                </div>

                <div class="so-ssl-privacy-form">
                    <form id="so-ssl-privacy-form">
						<?php wp_nonce_field('so_ssl_privacy_acknowledgment', 'so_ssl_privacy_nonce'); ?>
                        <input type="hidden" id="so_ssl_redirect_url" value="<?php echo esc_url($redirect_url); ?>">

                        <div class="so-ssl-privacy-checkbox">
                            <label>
                                <input type="checkbox" id="so_ssl_privacy_accept" name="so_ssl_privacy_accept" value="1" required>
								<?php echo esc_html($checkbox_text); ?>
                            </label>
                        </div>

                        <div class="so-ssl-privacy-actions">
                            <button type="submit" id="so_ssl_privacy_submit" class="button button-primary" disabled>
								<?php esc_html_e('Accept and Continue', 'so-ssl'); ?>
                            </button>

                            <a href="<?php echo esc_url(wp_logout_url(home_url())); ?>" class="so-ssl-alternate-button">
								<?php esc_html_e('Disagree and Logout', 'so-ssl'); ?>
                            </a>
                        </div>

                        <div id="so-ssl-privacy-message" class="so-ssl-privacy-message" style="display: none;"></div>
                    </form>
                </div>

                <div class="so-ssl-privacy-options">
                    <p>
						<?php esc_html_e('Need to disable this privacy compliance feature?', 'so-ssl'); ?>
                    </p>
                    <a href="<?php echo esc_url(admin_url('index.php?disable_so_ssl_privacy=1')); ?>" class="so-ssl-emergency-button">
                        <span class="dashicons dashicons-dismiss" style="margin-right: 4px; margin-top: 3px;"></span>
						<?php esc_html_e('Disable Privacy Compliance', 'so-ssl'); ?>
                    </a>
                    <a href="<?php echo esc_url(admin_url()); ?>" class="so-ssl-emergency-button">
                        <span class="dashicons dashicons-undo" style="margin-right: 4px; margin-top: 3px;"></span>
						<?php esc_html_e('Return to Admin', 'so-ssl'); ?>
                    </a>
                </div>
            </div>
        </div>

        <script>
            jQuery(document).ready(function($) {
                // Enable/disable submit button based on checkbox
                $('#so_ssl_privacy_accept').on('change', function() {
                    $('#so_ssl_privacy_submit').prop('disabled', !$(this).is(':checked'));
                });

                // Handle form submission
                $('#so-ssl-privacy-form').on('submit', function(e) {
                    e.preventDefault();

                    // Show loading state
                    $('#so_ssl_privacy_submit').prop('disabled', true).text('<?php esc_html_e('Processing...', 'so-ssl'); ?>');

                    // Send AJAX request
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'so_ssl_save_privacy_acknowledgment',
                            nonce: $('#so_ssl_privacy_nonce').val(),
                            accept: $('#so_ssl_privacy_accept').is(':checked') ? 1 : 0
                        },
                        success: function(response) {
                            if (response.success) {
                                $('#so-ssl-privacy-message')
                                    .removeClass('error')
                                    .addClass('success')
                                    .html('<p>' + response.data.message + '</p>')
                                    .show();

                                // Redirect to the original page after 1 second
                                setTimeout(function() {
                                    window.location.href = $('#so_ssl_redirect_url').val();
                                }, 1000);
                            } else {
                                $('#so-ssl-privacy-message')
                                    .removeClass('success')
                                    .addClass('error')
                                    .html('<p>' + response.data.message + '</p>')
                                    .show();

                                // Reset button
                                $('#so_ssl_privacy_submit').prop('disabled', false).text('<?php esc_html_e('Accept and Continue', 'so-ssl'); ?>');
                            }
                        },
                        error: function() {
                            $('#so-ssl-privacy-message')
                                .removeClass('success')
                                .addClass('error')
                                .html('<p><?php esc_html_e('An error occurred. Please try again.', 'so-ssl'); ?></p>')
                                .show();

                            // Reset button
                            $('#so_ssl_privacy_submit').prop('disabled', false).text('<?php esc_html_e('Accept and Continue', 'so-ssl'); ?>');
                        }
                    });
                });
            });
        </script>
		<?php
	}

	/**
	 * AJAX handler for saving privacy acknowledgment
	 */
	public static function ajax_save_privacy_acknowledgment() {
		// Verify nonce
		if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'so_ssl_privacy_acknowledgment')) {
			wp_send_json_error(array('message' => __('Security verification failed.', 'so-ssl')));
		}

		// Verify user is logged in
		if (!is_user_logged_in()) {
			wp_send_json_error(array('message' => __('You must be logged in to perform this action.', 'so-ssl')));
		}

		// Get acceptance value
		$accept = isset($_POST['accept']) ? absint($_POST['accept']) : 0;

		if ($accept !== 1) {
			wp_send_json_error(array('message' => __('You must accept the privacy notice to continue.', 'so-ssl')));
		}

		// Save acceptance to user meta
		$user_id = get_current_user_id();
		update_user_meta($user_id, 'so_ssl_privacy_acknowledged', time());

		wp_send_json_success(array('message' => __('Privacy notice accepted. Redirecting...', 'so-ssl')));
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
				'type' => 'boolean',
				'sanitize_callback' => 'intval',
				'default' => 0,
			)
		);

		register_setting(
			'so_ssl_options',
			'so_ssl_privacy_page_title',
			array(
				'type' => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default' => 'Privacy Acknowledgment Required',
			)
		);

		register_setting(
			'so_ssl_options',
			'so_ssl_privacy_page_slug',
			array(
				'type' => 'string',
				'sanitize_callback' => 'sanitize_title',
				'default' => 'privacy-acknowledgment',
			)
		);

		register_setting(
			'so_ssl_options',
			'so_ssl_privacy_notice_text',
			array(
				'type' => 'string',
				'sanitize_callback' => 'wp_kses_post',
				'default' => 'This site tracks certain information for security purposes including IP addresses, login attempts, and session data. By using this site, you acknowledge and consent to this data collection in accordance with our Privacy Policy and applicable data protection laws including GDPR and US privacy regulations.',
			)
		);

		register_setting(
			'so_ssl_options',
			'so_ssl_privacy_checkbox_text',
			array(
				'type' => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default' => 'I acknowledge and consent to the privacy notice above',
			)
		);

		register_setting(
			'so_ssl_options',
			'so_ssl_privacy_expiry_days',
			array(
				'type' => 'integer',
				'sanitize_callback' => 'intval',
				'default' => 30,
			)
		);

		// Privacy Compliance User Roles setting
		register_setting(
			'so_ssl_options',
			'so_ssl_privacy_required_roles',
			array(
				'type' => 'array',
				'sanitize_callback' => function($input) {
					if (!is_array($input)) {
						return array();
					}
					return array_map('sanitize_text_field', $input);
				},
				'default' => array('subscriber', 'contributor', 'author', 'editor'),
			)
		);

		register_setting(
			'so_ssl_options',
			'so_ssl_privacy_exempt_admins',
			array(
				'type' => 'boolean',
				'sanitize_callback' => 'intval',
				'default' => true,
			)
		);

		register_setting(
			'so_ssl_options',
			'so_ssl_privacy_exempt_original_admin',
			array(
				'type' => 'boolean',
				'sanitize_callback' => 'intval',
				'default' => true,
			)
		);

		// Privacy Compliance Settings Section
		add_settings_section(
			'so_ssl_privacy_compliance_section',
			__('Privacy Compliance Settings', 'so-ssl'),
			array(__CLASS__, 'privacy_compliance_section_callback'),
			'so-ssl-privacy'
		);

		add_settings_field(
			'so_ssl_enable_privacy_compliance',
			__('Enable Privacy Compliance', 'so-ssl'),
			array(__CLASS__, 'enable_privacy_compliance_callback'),
			'so-ssl-privacy',
			'so_ssl_privacy_compliance_section'
		);

		add_settings_field(
			'so_ssl_privacy_page_title',
			__('Privacy Page Title', 'so-ssl'),
			array(__CLASS__, 'privacy_page_title_callback'),
			'so-ssl-privacy',
			'so_ssl_privacy_compliance_section'
		);

		add_settings_field(
			'so_ssl_privacy_page_slug',
			__('Privacy Page Slug', 'so-ssl'),
			array(__CLASS__, 'privacy_page_slug_callback'),
			'so-ssl-privacy',
			'so_ssl_privacy_compliance_section'
		);

		add_settings_field(
			'so_ssl_privacy_notice_text',
			__('Privacy Notice Text', 'so-ssl'),
			array(__CLASS__, 'privacy_notice_text_callback'),
			'so-ssl-privacy',
			'so_ssl_privacy_compliance_section'
		);

		add_settings_field(
			'so_ssl_privacy_checkbox_text',
			__('Acknowledgment Checkbox Text', 'so-ssl'),
			array(__CLASS__, 'privacy_checkbox_text_callback'),
			'so-ssl-privacy',
			'so_ssl_privacy_compliance_section'
		);

		add_settings_field(
			'so_ssl_privacy_expiry_days',
			__('Acknowledgment Expiry (Days)', 'so-ssl'),
			array(__CLASS__, 'privacy_expiry_days_callback'),
			'so-ssl-privacy',
			'so_ssl_privacy_compliance_section'
		);

		add_settings_field(
			'so_ssl_privacy_required_roles',
			__('Required for User Roles', 'so-ssl'),
			array(__CLASS__, 'privacy_required_roles_callback'),
			'so-ssl-privacy',
			'so_ssl_privacy_compliance_section'
		);
	}

	/**
	 * Privacy compliance section description
	 */
	public static function privacy_compliance_section_callback() {
    echo '<p>' . esc_html__('Configure privacy compliance settings to inform users about data collection and tracking.', 'so-ssl') . '</p>';
   	echo '<p>' . esc_html__('When enabled, users will be required to acknowledge a privacy notice before accessing logged-in areas of the site.', 'so-ssl') . '</p>';
   	echo '<p><a href="#privacy-preview" class="so-ssl-preview-link">' . esc_html__('Jump to preview', 'so-ssl') . '</a></p>';
   }

   /**
    * Enable privacy compliance field callback
    */
   public static function enable_privacy_compliance_callback() {
   	$enable_privacy_compliance = get_option('so_ssl_enable_privacy_compliance', 0);

   	echo '<label for="so_ssl_enable_privacy_compliance">';
   	echo '<input type="checkbox" id="so_ssl_enable_privacy_compliance" name="so_ssl_enable_privacy_compliance" value="1" ' . checked(1, $enable_privacy_compliance, false) . '/>';
   	echo esc_html__('Enable privacy compliance acknowledgment page', 'so-ssl');
   	echo '</label>';
   	echo '<p class="description">' . esc_html__('Requires users to acknowledge a privacy notice after login.', 'so-ssl') . '</p>';
   }

   /**
    * Privacy page title field callback
    */
   public static function privacy_page_title_callback() {
   	$page_title = get_option('so_ssl_privacy_page_title', 'Privacy Acknowledgment Required');

   	echo '<input type="text" id="so_ssl_privacy_page_title" name="so_ssl_privacy_page_title" value="' . esc_attr($page_title) . '" class="regular-text" />';
   	echo '<p class="description">' . esc_html__('Title of the privacy acknowledgment page.', 'so-ssl') . '</p>';
   }

   /**
    * Privacy page slug field callback
    */
   public static function privacy_page_slug_callback() {
   	$page_slug = get_option('so_ssl_privacy_page_slug', 'privacy-acknowledgment');
   	// This field is now deprecated since we're using a query parameter
   	echo '<p class="description">' .
   	     esc_html__('Using query parameter: ', 'so-ssl') .
   	     '<code>' . site_url('/?'.$page_slug.'=1') . '</code></p>';

   	// Keep the input field for backward compatibility
   	$page_slug = get_option('so_ssl_privacy_page_slug', 'privacy-acknowledgment');
   	echo '<input type="hidden" id="so_ssl_privacy_page_slug" name="so_ssl_privacy_page_slug" value="' . esc_attr($page_slug) . '" />';

   	echo '<p class="description">' .
   	     esc_html__('The privacy page now uses a query parameter instead of a custom URL for improved compatibility.', 'so-ssl') .
   	     '</p>';
   }

   /**
    * Privacy notice text field callback
    */
   public static function privacy_notice_text_callback() {
   	$notice_text = get_option('so_ssl_privacy_notice_text', 'This site tracks certain information for security purposes including IP addresses, login attempts, and session data. By using this site, you acknowledge and consent to this data collection in accordance with our Privacy Policy and applicable data protection laws including GDPR and US privacy regulations.');

   	$editor_id = 'so_ssl_privacy_notice_text_editor';
   	$editor_settings = array(
   		'textarea_name' => 'so_ssl_privacy_notice_text',
   		'textarea_rows' => 10,
   		'media_buttons' => true,
   		'tinymce'       => true,
   		'quicktags'     => true,
   	);

   	wp_editor($notice_text, $editor_id, $editor_settings);

   	echo '<p class="description">' . esc_html__('Text explaining what data is collected and why. HTML is allowed.', 'so-ssl') . '</p>';
   }

   /**
    * Privacy checkbox text field callback
    */
   public static function privacy_checkbox_text_callback() {
   	$checkbox_text = get_option('so_ssl_privacy_checkbox_text', 'I acknowledge and consent to the privacy notice above');

   	echo '<input type="text" id="so_ssl_privacy_checkbox_text" name="so_ssl_privacy_checkbox_text" value="' . esc_attr($checkbox_text) . '" class="regular-text" />';
   	echo '<p class="description">' . esc_html__('Text for the acknowledgment checkbox.', 'so-ssl') . '</p>';
   }

   /**
    * Privacy expiry days field callback
    */
   public static function privacy_expiry_days_callback() {
   	$expiry_days = get_option('so_ssl_privacy_expiry_days', 30);

   	echo '<input type="number" id="so_ssl_privacy_expiry_days" name="so_ssl_privacy_expiry_days" value="' . esc_attr($expiry_days) . '" min="1" max="365" />';
   	echo '<p class="description">' . esc_html__('Number of days before users need to re-acknowledge the privacy notice. Set to 365 for annual acknowledgment.', 'so-ssl') . '</p>';
   }

   /**
    * Privacy required roles field callback
    */
   public static function privacy_required_roles_callback() {
   	$required_roles = get_option('so_ssl_privacy_required_roles', array('subscriber', 'contributor', 'author', 'editor'));
   	$roles = wp_roles()->get_names();

   	echo '<select multiple id="so_ssl_privacy_required_roles" name="so_ssl_privacy_required_roles[]" class="regular-text" style="height: 120px;">';
   	foreach ($roles as $role_value => $role_name) {
   		echo '<option value="' . esc_attr($role_value) . '" ' . selected(in_array($role_value, $required_roles), true, false) . '>' . esc_html($role_name) . '</option>';
   	}
   	echo '</select>';
   	echo '<p class="description">' . esc_html__('Select which user roles will be required to acknowledge the privacy notice. Hold Ctrl/Cmd to select multiple roles.', 'so-ssl') . '</p>';

   	// Add option to exempt administrators
   	$exempt_admins = get_option('so_ssl_privacy_exempt_admins', true);
   	echo '<div style="margin-top: 10px;">';
   	echo '<label for="so_ssl_privacy_exempt_admins">';
   	echo '<input type="checkbox" id="so_ssl_privacy_exempt_admins" name="so_ssl_privacy_exempt_admins" value="1" ' . checked(1, $exempt_admins, false) . '/>';
   	echo esc_html__('Always exempt administrators', 'so-ssl');
   	echo '</label>';
   	echo '<p class="description">' . esc_html__('When checked, administrators will never be required to acknowledge the privacy notice, regardless of role selection above.', 'so-ssl') . '</p>';
   	echo '</div>';

   	// Add option to exempt original admin (user ID 1)
   	$exempt_original_admin = get_option('so_ssl_privacy_exempt_original_admin', true);
   	echo '<div style="margin-top: 10px;">';
   	echo '<label for="so_ssl_privacy_exempt_original_admin">';
   	echo '<input type="checkbox" id="so_ssl_privacy_exempt_original_admin" name="so_ssl_privacy_exempt_original_admin" value="1" ' . checked(1, $exempt_original_admin, false) . '/>';
   	echo esc_html__('Always exempt original admin (user ID 1)', 'so-ssl');
   	echo '</label>';
   	echo '<p class="description">' . esc_html__('When checked, the original admin user (ID 1) will never be required to acknowledge the privacy notice.', 'so-ssl') . '</p>';
   	echo '</div>';
   }

   /**
    * Add JavaScript to admin footer
    */
   public static function add_admin_footer_js() {
   	?>
   	<script>
   	jQuery(document).ready(function($) {
   		// Function to update preview based on form values
   		function updatePrivacyPreview() {
   			var pageTitle = $('#so_ssl_privacy_page_title').val();
   			var noticeText = $('#so_ssl_privacy_notice_text').val();
   			var checkboxText = $('#so_ssl_privacy_checkbox_text').val();

   			// Update the preview elements
   			$('.so-ssl-preview-container h2').text(pageTitle);
   			$('.so-ssl-preview-container div:first').html(noticeText);
   			$('.so-ssl-preview-container label').contents().filter(function() {
   				return this.nodeType === 3; // Text nodes only
   			}).replaceWith(checkboxText);
   		}

   		// Add event listeners to form fields
   		$('#so_ssl_privacy_page_title, #so_ssl_privacy_notice_text, #so_ssl_privacy_checkbox_text').on('input', function() {
   			updatePrivacyPreview();
   		});

   		// Toggle preview visibility based on whether privacy compliance is enabled
   		$('#so_ssl_enable_privacy_compliance').on('change', function() {
   			if ($(this).is(':checked')) {
   				$('#privacy-preview').show();
   			} else {
   				$('#privacy-preview').hide();
   			}
   		});

   		// Initialize
   		if (!$('#so_ssl_enable_privacy_compliance').is(':checked')) {
   			$('#privacy-preview').hide();
   		}
   	});
   	</script>
   	<?php
   }

   /**
    * Register the privacy acknowledgment template
    */
   public static function register_privacy_template() {
   	// Register query var
   	add_filter('query_vars', array(__CLASS__, 'add_query_vars'));
   }

   /**
    * Add custom query vars
    *
    * @param array $vars The array of available query variables
    * @return array Modified array of query variables
    */
   public static function add_query_vars($vars) {
   	$page_slug = get_option('so_ssl_privacy_page_slug', 'privacy-acknowledgment');
   	$vars[] = $page_slug;
   	return $vars;
   }
}

// Initialize the class
So_SSL_Privacy_Compliance::init();
So_SSL_Privacy_Compliance::register_privacy_template();

// Add the JavaScript to admin footer
add_action('admin_footer', array('So_SSL_Privacy_Compliance', 'add_admin_footer_js'));
