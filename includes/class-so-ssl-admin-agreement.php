<?php
/**
 * So SSL Admin Agreement Module
 *
 * This file implements admin usage agreement functionality for the So SSL plugin.
 * Only blocks access to So SSL plugin pages until agreement is accepted.
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
	die;
}

class So_SSL_Admin_Agreement {

	/**
	 * Initialize the admin agreement module
	 */
	public static function init() {
		// Only proceed if admin agreement is enabled
		if (!get_option('so_ssl_enable_admin_agreement', 1)) {
			return;
		}

		// Register admin settings
		add_action('admin_init', array(__CLASS__, 'register_settings'));

		// Add the agreement page - IMPORTANT: using 'admin_menu' priority 5 to ensure it's registered early
		add_action('admin_menu', array(__CLASS__, 'add_agreement_menu'), 5);

		// Check if admin has acknowledged the agreement - lower priority to run after menu is registered
		add_action('admin_init', array(__CLASS__, 'check_admin_agreement'), 20);

		// Add AJAX handler for saving agreement
		add_action('wp_ajax_so_ssl_save_admin_agreement', array(__CLASS__, 'ajax_save_admin_agreement'));

		// Add notice on plugins page to inform admins about the emergency override
		add_action('admin_notices', array(__CLASS__, 'maybe_show_emergency_notice'));
	}

	/**
	 * Show emergency notice on plugins page
	 */
	public static function maybe_show_emergency_notice() {
		$screen = get_current_screen();
		if ($screen && ($screen->id === 'plugins' || strpos($screen->id, 'so-ssl') !== false)) {
			echo '<div class="notice notice-info is-dismissible">';
			echo '<p><strong>So SSL Tip:</strong> If you ever get locked out of the plugin due to the admin agreement, you can use this URL to disable it: <code>' . admin_url('index.php?disable_so_ssl_agreement=1') . '</code></p>';
			echo '</div>';
		}
	}

	/**
	 * Check if admin has acknowledged the agreement
	 * Only blocks access to So SSL plugin pages
	 */
	public static function check_admin_agreement() {
		// Skip for AJAX, Cron, CLI, or admin-ajax.php requests
		if (wp_doing_ajax() || wp_doing_cron() || (defined('WP_CLI') && WP_CLI) ||
		    (isset($_SERVER['SCRIPT_FILENAME']) && strpos(sanitize_text_field(wp_unslash($_SERVER['SCRIPT_FILENAME'])), 'admin-ajax.php') !== false)) {
			return;
		}

		// Only check for admin users
		if (!current_user_can('manage_options')) {
			return;
		}

		// Check if we're on a So SSL plugin page
		$is_plugin_page = false;

		// Check query parameters for the plugin pages
		if (isset($_GET['page'])) {
			$page = sanitize_text_field($_GET['page']);

			// List of So SSL plugin pages to protect
			$so_ssl_pages = array(
				'so-ssl',                 // Main plugin page
				'so-ssl-sessions',        // User sessions page
				'class-so-ssl-login-limit', // Login limit page
				'so-ssl-login-limit'      // Another login limit page name possibility
			);

			// Check if current page is a So SSL page
			if (in_array($page, $so_ssl_pages)) {
				$is_plugin_page = true;
			}
		}

		// If not on a plugin page, no need to check for agreement
		if (!$is_plugin_page) {
			return;
		}

		// Exception for the agreement page itself
		if (isset($_GET['page']) && $_GET['page'] === 'so-ssl-agreement') {
			return;
		}

		// Get current user
		$current_user = wp_get_current_user();
		$user_id = $current_user->ID;

		// Check if original admin (user ID 1) is exempt
		$exempt_original_admin = get_option('so_ssl_admin_agreement_exempt_original_admin', true);
		if ($exempt_original_admin && $user_id === 1) {
			return;
		}

		// Check user role requirements
		$required_roles = get_option('so_ssl_admin_agreement_required_roles', array('administrator'));
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

		// Check if admin has already agreed
		$agreement = get_user_meta($user_id, 'so_ssl_admin_agreement_accepted', true);
		$expiry_days = intval(get_option('so_ssl_admin_agreement_expiry_days', 365));

		// Check if agreement has expired or doesn't exist
		if (empty($agreement) || (time() - intval($agreement)) > ($expiry_days * DAY_IN_SECONDS)) {
			// Set filter to indicate admin agreement is being shown
			add_filter('so_ssl_showing_admin_agreement', '__return_true');

			// Display admin notice instead of redirecting
			add_action('admin_notices', array(__CLASS__, 'display_agreement_notice'));

			// Use JavaScript to control access to plugin content
			add_action('admin_footer', array(__CLASS__, 'add_agreement_overlay_script'));
		}
	}

	/**
	 * Display admin notice about agreement requirement
	 */
	public static function display_agreement_notice() {
		$agreement_url = admin_url('admin.php?page=so-ssl-agreement');

		// Custom CSS to style the notice
		$custom_css = "
        .so-ssl-agreement-notice {
            background-color: #f0f6fc;
            border-left: 4px solid #2271b1;
            padding: 20px;
            margin: 20px 0;
            box-shadow: 0 1px 4px rgba(0,0,0,0.07);
            position: relative;
            border-radius: 0 3px 3px 0;
        }
        .so-ssl-agreement-notice h3 {
            margin-top: 0;
            color: #2271b1;
            font-size: 16px;
            font-weight: 600;
            display: flex;
            align-items: center;
            line-height: 1.4;
        }
        .so-ssl-agreement-notice h3 .dashicons {
            margin-right: 8px;
            color: #2271b1;
            font-size: 20px;
            width: 20px;
            height: 20px;
        }
        .so-ssl-agreement-notice p {
            margin: 0.5em 0;
            color: #1d2327;
            font-size: 14px;
        }
        .so-ssl-agreement-actions {
            margin-top: 15px;
        }
        .so-ssl-agreement-notice .button-primary {
            background: #2271b1;
            border-color: #2271b1;
            color: #fff;
            text-decoration: none;
            padding: 6px 15px;
            transition: all 0.2s ease;
        }
        .so-ssl-agreement-notice .button-primary:hover {
            background: #135e96;
            border-color: #135e96;
        }
        .so-ssl-agreement-notice .button-secondary {
            background: #f6f7f7;
            border: 1px solid #c3c4c7;
            color: #50575e;
            text-decoration: none;
            padding: 6px 12px;
            margin-left: 10px;
            transition: all 0.2s ease;
        }
        .so-ssl-agreement-notice .button-secondary:hover {
            background: #f0f0f1;
            border-color: #8c8f94;
            color: #1d2327;
        }
    ";

		// Output the CSS
		echo '<style>' . $custom_css . '</style>';

		// Output the enhanced notice
		echo '<div class="so-ssl-agreement-notice">';
		echo '<h3><span class="dashicons dashicons-shield"></span>' . esc_html__('So SSL Agreement Required', 'so-ssl') . '</h3>';
		echo '<p>' . esc_html__('An administrator agreement is required before using So SSL plugin features. Please review and accept the agreement to continue using the plugin.', 'so-ssl') . '</p>';
		echo '<div class="so-ssl-agreement-actions">';
		echo '<a href="' . esc_url($agreement_url) . '" class="button button-primary">' . esc_html__('View & Accept Agreement', 'so-ssl') . '</a>';
		echo '<a href="' . esc_url(admin_url('options-general.php')) . '" class="button-secondary">' . esc_html__('Back to Settings', 'so-ssl') . '</a>';
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Add JavaScript to overlay plugin content until agreement is accepted
	 */
	public static function add_agreement_overlay_script() {
		?>
        <style>
            #so-ssl-agreement-overlay {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(255,255,255,0.95);
                z-index: 999999;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            #so-ssl-agreement-content {
                background: white;
                padding: 30px;
                max-width: 600px;
                text-align: center;
                box-shadow: 0 0 20px rgba(0,0,0,0.2);
                border-radius: 5px;
            }

            #so-ssl-agreement-content h2 {
                color: #2271b1;
                margin-bottom: 20px;
            }

            #so-ssl-agreement-content .button-primary {
                margin: 0 5px;
            }
        </style>

        <script>
            jQuery(document).ready(function($) {
                // Create overlay elements
                var $overlay = $('<div id="so-ssl-agreement-overlay"></div>');
                var $content = $('<div id="so-ssl-agreement-content"></div>');

                $content.html(
                    '<h2><?php echo esc_js(__('Administrator Agreement Required', 'so-ssl')); ?></h2>' +
                    '<p><?php echo esc_js(__('You must accept the So SSL administrator agreement before accessing plugin features.', 'so-ssl')); ?></p>' +
                    '<p>' +
                    '<a href="<?php echo esc_url(admin_url('admin.php?page=so-ssl-agreement')); ?>" class="button button-primary"><?php echo esc_js(__('View & Accept Agreement', 'so-ssl')); ?></a> ' +
                    '<a href="<?php echo esc_url(admin_url('options-general.php')); ?>" class="button"><?php echo esc_js(__('Back to Settings', 'so-ssl')); ?></a>' +
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
	public static function add_agreement_menu() {
		// Add a direct admin page (not under options-general.php)
		// This ensures it's available even if there are permission issues
		add_menu_page(
			__('Admin Agreement', 'so-ssl'),
			__('Admin Agreement', 'so-ssl'),
			'read', // Allow any logged-in user to access the agreement page
			'so-ssl-agreement',
			array(__CLASS__, 'display_agreement_page'),
			'dashicons-shield',
			999
		);

		// Hide this from the menu - it's only for direct access
		remove_menu_page('so-ssl-agreement');
	}

	/**
	 * Register settings for admin agreement
	 */
	public static function register_settings() {
		// Admin Agreement settings
		register_setting(
			'so_ssl_options',
			'so_ssl_enable_admin_agreement',
			array(
				'type' => 'boolean',
				'sanitize_callback' => 'intval',
				'default' => 1,
			)
		);

		register_setting(
			'so_ssl_options',
			'so_ssl_admin_agreement_title',
			array(
				'type' => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default' => 'Administrator Agreement Required',
			)
		);

		register_setting(
			'so_ssl_options',
			'so_ssl_admin_agreement_text',
			array(
				'type' => 'string',
				'sanitize_callback' => 'wp_kses_post',
				'default' => 'By using this plugin, you agree to adhere to security best practices and ensure all data collected will be handled in accordance with applicable privacy laws. You acknowledge that this plugin makes changes to your website\'s security configuration that you are responsible for monitoring and maintaining.',
			)
		);

		register_setting(
			'so_ssl_options',
			'so_ssl_admin_agreement_checkbox_text',
			array(
				'type' => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default' => 'I understand and agree to these terms',
			)
		);

		register_setting(
			'so_ssl_options',
			'so_ssl_admin_agreement_expiry_days',
			array(
				'type' => 'integer',
				'sanitize_callback' => 'intval',
				'default' => 365,
			)
		);

		// Add new settings for role selection and original admin exemption
		register_setting(
			'so_ssl_options',
			'so_ssl_admin_agreement_required_roles',
			array(
				'type' => 'array',
				'sanitize_callback' => function($input) {
					if (!is_array($input)) {
						return array('administrator');
					}
					return array_map('sanitize_text_field', $input);
				},
				'default' => array('administrator'),
			)
		);

		register_setting(
			'so_ssl_options',
			'so_ssl_admin_agreement_exempt_original_admin',
			array(
				'type' => 'boolean',
				'sanitize_callback' => 'intval',
				'default' => true,
			)
		);

		// Admin Agreement Settings Section
		add_settings_section(
			'so_ssl_admin_agreement_section',
			__('Administrator Agreement Settings', 'so-ssl'),
			array(__CLASS__, 'admin_agreement_section_callback'),
			'so-ssl-admin-agreement'
		);

		add_settings_field(
			'so_ssl_enable_admin_agreement',
			__('Enable Admin Agreement', 'so-ssl'),
			array(__CLASS__, 'enable_admin_agreement_callback'),
			'so-ssl-admin-agreement',
			'so_ssl_admin_agreement_section'
		);

		add_settings_field(
			'so_ssl_admin_agreement_title',
			__('Agreement Page Title', 'so-ssl'),
			array(__CLASS__, 'admin_agreement_title_callback'),
			'so-ssl-admin-agreement',
			'so_ssl_admin_agreement_section'
		);

		add_settings_field(
			'so_ssl_admin_agreement_text',
			__('Agreement Text', 'so-ssl'),
			array(__CLASS__, 'admin_agreement_text_callback'),
			'so-ssl-admin-agreement',
			'so_ssl_admin_agreement_section'
		);

		add_settings_field(
			'so_ssl_admin_agreement_checkbox_text',
			__('Agreement Checkbox Text', 'so-ssl'),
			array(__CLASS__, 'admin_agreement_checkbox_text_callback'),
			'so-ssl-admin-agreement',
			'so_ssl_admin_agreement_section'
		);

		add_settings_field(
			'so_ssl_admin_agreement_expiry_days',
			__('Agreement Expiry (Days)', 'so-ssl'),
			array(__CLASS__, 'admin_agreement_expiry_days_callback'),
			'so-ssl-admin-agreement',
			'so_ssl_admin_agreement_section'
		);

		// Add new field for role selection
		add_settings_field(
			'so_ssl_admin_agreement_required_roles',
			__('Required for User Roles', 'so-ssl'),
			array(__CLASS__, 'admin_agreement_required_roles_callback'),
			'so-ssl-admin-agreement',
			'so_ssl_admin_agreement_section'
		);
	}

	/**
	 * Admin agreement section callback
	 */
	public static function admin_agreement_section_callback() {
		echo '<p>' . esc_html__('Configure the agreement that administrators must accept before using the plugin.', 'so-ssl') . '</p>';
	}

	/**
	 * Enable admin agreement field callback
	 */
	public static function enable_admin_agreement_callback() {
		$enable_admin_agreement = get_option('so_ssl_enable_admin_agreement', 1);

		echo '<label for="so_ssl_enable_admin_agreement">';
		echo '<input type="checkbox" id="so_ssl_enable_admin_agreement" name="so_ssl_enable_admin_agreement" value="1" ' . checked(1, $enable_admin_agreement, false) . '/>';
		echo esc_html__('Require administrators to accept an agreement before using the plugin', 'so-ssl');
		echo '</label>';
		echo '<p class="description">' . esc_html__('When enabled, administrators must accept the agreement before accessing plugin settings.', 'so-ssl') . '</p>';
	}

	/**
	 * Admin agreement title field callback
	 */
	public static function admin_agreement_title_callback() {
		$page_title = get_option('so_ssl_admin_agreement_title', 'Administrator Agreement Required');

		echo '<input type="text" id="so_ssl_admin_agreement_title" name="so_ssl_admin_agreement_title" value="' . esc_attr($page_title) . '" class="regular-text" />';
		echo '<p class="description">' . esc_html__('Title of the administrator agreement page.', 'so-ssl') . '</p>';
	}

	/**
	 * Admin agreement text field callback
	 */
	public static function admin_agreement_text_callback() {
		$agreement_text = get_option('so_ssl_admin_agreement_text', 'By using this plugin, you agree to adhere to security best practices and ensure all data collected will be handled in accordance with applicable privacy laws. You acknowledge that this plugin makes changes to your website\'s security configuration that you are responsible for monitoring and maintaining.');

		$editor_id = 'so_ssl_admin_agreement_text_editor';
		$editor_settings = array(
			'textarea_name' => 'so_ssl_admin_agreement_text',
			'textarea_rows' => 10,
			'media_buttons' => true,
			'tinymce'       => true,
			'quicktags'     => true,
		);

		wp_editor($agreement_text, $editor_id, $editor_settings);

		echo '<p class="description">' . esc_html__('Text of the agreement that administrators must accept. HTML is allowed.', 'so-ssl') . '</p>';
	}

	/**
	 * Admin agreement checkbox text field callback
	 */
	public static function admin_agreement_checkbox_text_callback() {
		$checkbox_text = get_option('so_ssl_admin_agreement_checkbox_text', 'I understand and agree to these terms');

		echo '<input type="text" id="so_ssl_admin_agreement_checkbox_text" name="so_ssl_admin_agreement_checkbox_text" value="' . esc_attr($checkbox_text) . '" class="regular-text" />';
		echo '<p class="description">' . esc_html__('Text for the agreement checkbox.', 'so-ssl') . '</p>';
	}

	/**
	 * Admin agreement expiry days field callback
	 */
	public static function admin_agreement_expiry_days_callback() {
		$expiry_days = get_option('so_ssl_admin_agreement_expiry_days', 365);

		echo '<input type="number" id="so_ssl_admin_agreement_expiry_days" name="so_ssl_admin_agreement_expiry_days" value="' . esc_attr($expiry_days) . '" min="1" max="3650" />';
		echo '<p class="description">' . esc_html__('Number of days before administrators need to re-accept the agreement. Default is 365 days (yearly).', 'so-ssl') . '</p>';
	}

	/**
	 * Admin agreement required roles field callback
	 */
	public static function admin_agreement_required_roles_callback() {
		$required_roles = get_option('so_ssl_admin_agreement_required_roles', array('administrator'));
		$roles = wp_roles()->get_names();

		echo '<select multiple id="so_ssl_admin_agreement_required_roles" name="so_ssl_admin_agreement_required_roles[]" class="regular-text" style="height: 120px;">';
		foreach ($roles as $role_value => $role_name) {
			echo '<option value="' . esc_attr($role_value) . '" ' . selected(in_array($role_value, $required_roles), true, false) . '>' . esc_html($role_name) . '</option>';
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__('Select which user roles will be required to acknowledge the administrator agreement. Hold Ctrl/Cmd to select multiple roles.', 'so-ssl') . '</p>';

		// Add option to exempt original admin (user ID 1)
		$exempt_original_admin = get_option('so_ssl_admin_agreement_exempt_original_admin', false);
		echo '<div style="margin-top: 10px;">';
		echo '<label for="so_ssl_admin_agreement_exempt_original_admin">';
		echo '<input type="checkbox" id="so_ssl_admin_agreement_exempt_original_admin" name="so_ssl_admin_agreement_exempt_original_admin" value="1" ' . checked(1, $exempt_original_admin, false) . '/>';
		echo esc_html__('Always exempt original admin (user ID 1)', 'so-ssl');
		echo '</label>';
		echo '<p class="description">' . esc_html__('When checked, the original admin user (ID 1) will never be required to acknowledge the admin agreement.', 'so-ssl') . '</p>';
		echo '</div>';
	}

	/**
	 * Display admin agreement page
	 */
	public static function display_agreement_page() {
		// Check user capabilities - any logged in user can view
		if (!is_user_logged_in()) {
			wp_die(__('You must be logged in to view this page.', 'so-ssl'));
			return;
		}

		$page_title = get_option('so_ssl_admin_agreement_title', 'Administrator Agreement Required');
		$agreement_text = get_option('so_ssl_admin_agreement_text', '');
		$checkbox_text = get_option('so_ssl_admin_agreement_checkbox_text', '');

		// Get the referring plugin page (for return after acceptance)
		$referer = isset($_SERVER['HTTP_REFERER']) ? wp_unslash($_SERVER['HTTP_REFERER']) : '';
		$redirect_url = !empty($referer) && strpos($referer, 'page=so-ssl') !== false ?
			$referer : admin_url('options-general.php?page=so-ssl');

		// Add CSS for the agreement page - using the plugin's color palette
		?>
        <style>
            .so-ssl-agreement-wrap {
                max-width: 800px;
                margin: 40px auto;
            }
            .so-ssl-agreement-container {
                background: #fff;
                border: 1px solid #c3c4c7;
                border-radius: 4px;
                box-shadow: 0 1px 4px rgba(0, 0, 0, 0.07);
                padding: 25px;
                margin-top: 20px;
            }
            .so-ssl-agreement-header {
                border-bottom: 1px solid #c3c4c7;
                margin-bottom: 20px;
                padding-bottom: 15px;
            }
            .so-ssl-agreement-header h1 {
                color: #2271b1;
                font-size: 24px;
                font-weight: 600;
                margin: 0;
                padding: 0;
                display: flex;
                align-items: center;
            }
            .so-ssl-agreement-content {
                background: #f0f6fc;
                border-left: 4px solid #2271b1;
                padding: 20px;
                margin-bottom: 25px;
                color: #1d2327;
                line-height: 1.5;
            }
            .so-ssl-agreement-form {
                background: #f8f9fa;
                border: 1px solid #dcdcde;
                border-radius: 4px;
                padding: 20px;
                margin-bottom: 20px;
            }
            .so-ssl-agreement-checkbox {
                margin-bottom: 20px;
            }
            .so-ssl-agreement-checkbox input[type="checkbox"] {
                margin-right: 8px;
            }
            .so-ssl-agreement-checkbox label {
                font-weight: 500;
                color: #1d2327;
            }
            .so-ssl-agreement-actions button {
                background: #2271b1;
                border-color: #2271b1;
                color: #fff;
                padding: 8px 15px;
                height: auto;
                transition: all 0.2s ease;
            }
            .so-ssl-agreement-actions button:hover {
                background: #135e96;
            }
            .so-ssl-agreement-actions button:disabled {
                background: #c3c4c7 !important;
                border-color: #c3c4c7 !important;
                color: #50575e !important;
                cursor: not-allowed;
            }
            .so-ssl-agreement-options {
                margin-top: 25px;
                padding-top: 15px;
                border-top: 1px solid #dcdcde;
            }
            .so-ssl-agreement-message {
                padding: 10px 15px;
                margin-top: 15px;
                border-radius: 4px;
            }
            .so-ssl-agreement-message.success {
                background-color: #f0f8ee;
                border-left: 4px solid #46b450;
                color: #1d2327;
            }
            .so-ssl-agreement-message.error {
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

        <div class="wrap so-ssl-agreement-wrap">
            <div class="so-ssl-agreement-container">
                <div class="so-ssl-agreement-header">
                    <h1>
                        <span class="dashicons dashicons-shield so-ssl-security-icon"></span>
						<?php echo esc_html($page_title); ?>
                    </h1>
                </div>

                <div class="so-ssl-agreement-content">
					<?php echo wp_kses_post($agreement_text); ?>
                </div>

                <div class="so-ssl-agreement-form">
                    <form id="so-ssl-admin-agreement-form">
						<?php wp_nonce_field('so_ssl_admin_agreement', 'so_ssl_admin_agreement_nonce'); ?>
                        <input type="hidden" id="so_ssl_redirect_url" value="<?php echo esc_url($redirect_url); ?>">

                        <div class="so-ssl-agreement-checkbox">
                            <label>
                                <input type="checkbox" id="so_ssl_admin_agreement_accept" name="so_ssl_admin_agreement_accept" value="1" required>
								<?php echo esc_html($checkbox_text); ?>
                            </label>
                        </div>

                        <div class="so-ssl-agreement-actions">
                            <button type="submit" id="so_ssl_agreement_submit" class="button button-primary" disabled>
								<?php esc_html_e('Accept and Continue', 'so-ssl'); ?>
                            </button>

                            <a href="<?php echo esc_url(admin_url('options-general.php')); ?>" class="so-ssl-alternate-button">
								<?php esc_html_e('Disagree and Go Back', 'so-ssl'); ?>
                            </a>
                        </div>

                        <div id="so-ssl-agreement-message" class="so-ssl-agreement-message" style="display: none;"></div>
                    </form>
                </div>

                <div class="so-ssl-agreement-options">
                    <p>
						<?php esc_html_e('Need to disable this agreement feature?', 'so-ssl'); ?>
                    </p>
                    <a href="<?php echo esc_url(admin_url('index.php?disable_so_ssl_agreement=1')); ?>" class="so-ssl-emergency-button">
                        <span class="dashicons dashicons-dismiss" style="margin-right: 4px; margin-top: 3px;"></span>
						<?php esc_html_e('Disable Admin Agreement', 'so-ssl'); ?>
                    </a>
                    <a href="<?php echo esc_url(admin_url('options-general.php')); ?>" class="so-ssl-emergency-button">
                        <span class="dashicons dashicons-undo" style="margin-right: 4px; margin-top: 3px;"></span>
						<?php esc_html_e('Return to Settings', 'so-ssl'); ?>
                    </a>
                </div>
            </div>
        </div>

        <script>
            jQuery(document).ready(function($) {
                // Enable/disable submit button based on checkbox
                $('#so_ssl_admin_agreement_accept').on('change', function() {
                    $('#so_ssl_agreement_submit').prop('disabled', !$(this).is(':checked'));
                });

                // Handle form submission
                $('#so-ssl-admin-agreement-form').on('submit', function(e) {
                    e.preventDefault();

                    // Show loading state
                    $('#so_ssl_agreement_submit').prop('disabled', true).text('<?php esc_html_e('Processing...', 'so-ssl'); ?>');

                    // Send AJAX request
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'so_ssl_save_admin_agreement',
                            nonce: $('#so_ssl_admin_agreement_nonce').val(),
                            accept: $('#so_ssl_admin_agreement_accept').is(':checked') ? 1 : 0
                        },
                        success: function(response) {
                            if (response.success) {
                                $('#so-ssl-agreement-message')
                                    .removeClass('error')
                                    .addClass('success')
                                    .html('<p>' + response.data.message + '</p>')
                                    .show();

                                // Redirect to the original plugin page after 1 second
                                setTimeout(function() {
                                    window.location.href = $('#so_ssl_redirect_url').val();
                                }, 1000);
                            } else {
                                $('#so-ssl-agreement-message')
                                    .removeClass('success')
                                    .addClass('error')
                                    .html('<p>' + response.data.message + '</p>')
                                    .show();

                                // Reset button
                                $('#so_ssl_agreement_submit').prop('disabled', false).text('<?php esc_html_e('Accept and Continue', 'so-ssl'); ?>');
                            }
                        },
                        error: function() {
                            $('#so-ssl-agreement-message')
                                .removeClass('success')
                                .addClass('error')
                                .html('<p><?php esc_html_e('An error occurred. Please try again.', 'so-ssl'); ?></p>')
                                .show();

                            // Reset button
                            $('#so_ssl_agreement_submit').prop('disabled', false).text('<?php esc_html_e('Accept and Continue', 'so-ssl'); ?>');
                        }
                    });
                });
            });
        </script>
		<?php
	}

	/**
	 * AJAX handler for saving admin agreement
	 */
	public static function ajax_save_admin_agreement() {
		// Verify nonce
		if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'so_ssl_admin_agreement')) {
			wp_send_json_error(array('message' => __('Security verification failed.', 'so-ssl')));
		}

		// Verify user capabilities
		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'so-ssl')));
		}

		// Get acceptance value
		$accept = isset($_POST['accept']) ? absint($_POST['accept']) : 0;

		if ($accept !== 1) {
			wp_send_json_error(array('message' => __('You must accept the agreement to continue.', 'so-ssl')));
		}

		// Save acceptance to user meta
		$user_id = get_current_user_id();
		update_user_meta($user_id, 'so_ssl_admin_agreement_accepted', time());

		wp_send_json_success(array('message' => __('Agreement accepted. Redirecting...', 'so-ssl')));
	}
}

// Initialize the class
So_SSL_Admin_Agreement::init();