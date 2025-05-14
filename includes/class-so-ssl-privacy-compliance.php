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

		// Handle template loading and form processing - must be early
		add_action('template_redirect', array(__CLASS__, 'handle_privacy_page'), 5);
		add_action('admin_init', array(__CLASS__, 'handle_privacy_page'), 5);

		// Check if user has acknowledged the privacy notice - for front-end
		add_action('template_redirect', array(__CLASS__, 'check_privacy_acknowledgment'), 20);

		// Check if user has acknowledged the privacy notice - for admin
		add_action('admin_init', array(__CLASS__, 'check_privacy_acknowledgment_admin'), 20);

		// Add acknowledgment page
		add_action('wp_login', array(__CLASS__, 'flag_user_for_privacy_check'), 10, 2);

		// Register admin settings
		add_action('admin_init', array(__CLASS__, 'register_settings'));

		// Add hook for admin scripts (for TinyMCE editor)
		add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_admin_scripts'));

		// Fix logout links
		add_filter('logout_url', array(__CLASS__, 'fix_logout_url'), 10, 2);

		// Add modal to pages when needed
		add_action('wp_footer', array(__CLASS__, 'maybe_add_privacy_modal'));
		add_action('admin_footer', array(__CLASS__, 'maybe_add_privacy_modal'));
	}

	/**
	 * Handle privacy page display and form processing
	 */
	public static function handle_privacy_page() {
		$page_slug = get_option('so_ssl_privacy_page_slug', 'privacy-acknowledgment');

		// Check if we're on the privacy page
		if (!isset($_GET[$page_slug]) || $_GET[$page_slug] != '1') {
			return;
		}

		// Process form submission if present
		if (isset($_POST['so_ssl_privacy_submit']) && isset($_POST['so_ssl_privacy_nonce'])) {
			if (wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['so_ssl_privacy_nonce'])), 'so_ssl_privacy_acknowledgment')) {
				if (isset($_POST['so_ssl_privacy_accept']) && $_POST['so_ssl_privacy_accept'] == '1') {
					// User has acknowledged
					$user_id = get_current_user_id();

					if (!$user_id) {
						wp_die('Error: Unable to identify the current user.');
					}

					// Save acknowledgment with current timestamp
					update_user_meta($user_id, 'so_ssl_privacy_acknowledged', time());

					// Force clear any cached data
					clean_user_cache($user_id);
					wp_cache_delete($user_id, 'user_meta');

					// Determine redirect URL
					$redirect = '';

					// Try to get the redirect URL from cookie
					if (isset($_COOKIE['so_ssl_privacy_redirect'])) {
						$cookie_redirect = sanitize_url(wp_unslash($_COOKIE['so_ssl_privacy_redirect']));
						// Only use cookie redirect if it's not the privacy page
						if ($cookie_redirect && strpos($cookie_redirect, $page_slug.'=1') === false) {
							$redirect = $cookie_redirect;
						}
					}

					// If no valid redirect from cookie, use admin URL
					if (empty($redirect)) {
						$redirect = admin_url();
					}

					// Clear cookies before redirect
					if (isset($_COOKIE['so_ssl_privacy_needed'])) {
						setcookie('so_ssl_privacy_needed', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
					}
					if (isset($_COOKIE['so_ssl_privacy_redirect'])) {
						setcookie('so_ssl_privacy_redirect', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
					}

					// Do the redirect
					wp_safe_redirect($redirect);
					exit;
				}
			}
		}

		// Instead of displaying a full page, we'll set a flag to show the modal
		add_filter('so_ssl_show_privacy_modal', '__return_true');
	}

	/**
	 * Check privacy acknowledgment for admin pages
	 */
	public static function check_privacy_acknowledgment_admin() {
		$page_slug = get_option('so_ssl_privacy_page_slug', 'privacy-acknowledgment');

		// Skip if this is a logout request
		if (isset($_GET['action']) && $_GET['action'] === 'logout') {
			return;
		}

		// Skip for AJAX, Cron, CLI requests
		if (wp_doing_ajax() || wp_doing_cron() || (defined('WP_CLI') && WP_CLI)) {
			return;
		}

		// Only check for logged-in users
		if (!is_user_logged_in()) {
			return;
		}

		// Don't check if we're already on the privacy page
		if (isset($_GET[$page_slug]) && $_GET[$page_slug] == '1') {
			return;
		}

		// Skip if we're on the privacy acknowledgment page URL
		$request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
		if (strpos($request_uri, $page_slug.'=1') !== false) {
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

		// Force fresh check of user meta
		clean_user_cache($user_id);
		wp_cache_delete($user_id, 'user_meta');

		// Check acknowledgment status - use get_user_meta with single=true
		$acknowledgment = get_user_meta($user_id, 'so_ssl_privacy_acknowledged', true);
		$expiry_days = intval(get_option('so_ssl_privacy_expiry_days', 30));

		// Check if acknowledgment has expired or doesn't exist
		if (empty($acknowledgment) ||
		    (intval($acknowledgment) === 0) ||
		    (time() - intval($acknowledgment)) > ($expiry_days * DAY_IN_SECONDS)) {

			// Set a flag to show the modal
			add_filter('so_ssl_show_privacy_modal', '__return_true');
		}
	}

	/**
	 * Check if user has acknowledged the privacy notice - for front-end
	 */
	public static function check_privacy_acknowledgment() {
		$page_slug = get_option('so_ssl_privacy_page_slug', 'privacy-acknowledgment');

		// Skip if this is a logout request
		if (isset($_GET['action']) && $_GET['action'] === 'logout') {
			return;
		}

		// Skip for AJAX, Cron, CLI requests
		if (wp_doing_ajax() || wp_doing_cron() || (defined('WP_CLI') && WP_CLI)) {
			return;
		}

		// Only check for logged-in users
		if (!is_user_logged_in()) {
			return;
		}

		// Don't check if we're already on the privacy page
		if (isset($_GET[$page_slug]) && $_GET[$page_slug] == '1') {
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

		// Force fresh check of user meta
		clean_user_cache($user_id);
		wp_cache_delete($user_id, 'user_meta');

		// Check acknowledgment status - use get_user_meta with single=true
		$acknowledgment = get_user_meta($user_id, 'so_ssl_privacy_acknowledged', true);
		$expiry_days = intval(get_option('so_ssl_privacy_expiry_days', 30));

		// Check if acknowledgment has expired or doesn't exist
		if (empty($acknowledgment) ||
		    (intval($acknowledgment) === 0) ||
		    (time() - intval($acknowledgment)) > ($expiry_days * DAY_IN_SECONDS)) {

			// Set a flag to show the modal
			add_filter('so_ssl_show_privacy_modal', '__return_true');
		}
	}

	/**
	 * Add privacy modal to pages when needed
	 */
	public static function maybe_add_privacy_modal() {
		if (!apply_filters('so_ssl_show_privacy_modal', false)) {
			return;
		}

		// Ensure user is logged in
		if (!is_user_logged_in()) {
			return;
		}

		$page_title = get_option('so_ssl_privacy_page_title', 'Privacy Acknowledgment Required');
		$notice_text = get_option('so_ssl_privacy_notice_text', '');
		$checkbox_text = get_option('so_ssl_privacy_checkbox_text', '');

		// Show error message if form was submitted without checkbox
		$show_error = false;
		if (isset($_POST['so_ssl_privacy_submit']) &&
		    (!isset($_POST['so_ssl_privacy_accept']) || $_POST['so_ssl_privacy_accept'] != '1')) {
			$show_error = true;
		}

		?>
        <style>
            .so-ssl-privacy-modal-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.7);
                z-index: 999999;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .so-ssl-privacy-modal {
                width: 60%;
                max-width: 800px;
                background: #fff;
                border-radius: 8px;
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
                max-height: 90vh;
                overflow-y: auto;
                position: relative;
            }

            .so-ssl-privacy-header {
                background: #fff;
                border-bottom: 1px solid #dcdcde;
                padding: 20px 25px;
                border-radius: 8px 8px 0 0;
                position: sticky;
                top: 0;
                z-index: 10;
            }

            .so-ssl-privacy-title {
                color: #2271b1;
                font-size: 24px;
                font-weight: 600;
                margin: 0;
                padding: 0;
                display: flex;
                align-items: center;
            }

            .so-ssl-privacy-title .dashicons {
                margin-right: 10px;
                color: #2271b1;
            }

            .so-ssl-privacy-content {
                padding: 25px;
                margin-bottom: 0;
                line-height: 1.6;
                color: #1d2327;
            }

            .so-ssl-privacy-form {
                padding: 20px 25px;
                background: #f8f9fa;
                border-top: 1px solid #dcdcde;
                border-radius: 0 0 8px 8px;
            }

            .so-ssl-privacy-notice {
                background: #f0f6fc;
                border-left: 4px solid #2271b1;
                padding: 15px 20px;
                margin-bottom: 25px;
                border-radius: 0 4px 4px 0;
            }

            .so-ssl-privacy-checkbox {
                margin: 15px 0 20px;
            }

            .so-ssl-privacy-checkbox input[type="checkbox"] {
                margin-right: 8px;
            }

            .so-ssl-privacy-checkbox label {
                font-weight: 500;
                color: #1d2327;
            }

            .so-ssl-privacy-actions {
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            .so-ssl-privacy-submit {
                background: #2271b1;
                border: 1px solid #2271b1;
                color: #fff;
                padding: 8px 15px;
                border-radius: 3px;
                cursor: pointer;
                font-size: 14px;
                font-weight: 500;
                transition: all 0.2s ease;
            }

            .so-ssl-privacy-submit:hover {
                background: #135e96;
                border-color: #135e96;
            }

            .so-ssl-privacy-submit:disabled {
                background: #c3c4c7 !important;
                border-color: #c3c4c7 !important;
                color: #50575e !important;
                cursor: not-allowed;
            }

            .so-ssl-privacy-error {
                color: #d63638;
                background: #fcf0f1;
                border-left: 4px solid #d63638;
                padding: 10px 15px;
                margin-top: 15px;
                border-radius: 0 4px 4px 0;
            }

            .so-ssl-privacy-logout {
                color: #646970;
                text-decoration: none;
                font-size: 14px;
            }

            .so-ssl-privacy-logout:hover {
                color: #d63638;
            }

            @media (max-width: 768px) {
                .so-ssl-privacy-modal {
                    width: 90%;
                    margin: 20px;
                }
            }
        </style>

        <div class="so-ssl-privacy-modal-overlay">
            <div class="so-ssl-privacy-modal">
                <div class="so-ssl-privacy-header">
                    <h1 class="so-ssl-privacy-title">
                        <span class="dashicons dashicons-shield"></span>
						<?php echo esc_html($page_title); ?>
                    </h1>
                </div>

                <div class="so-ssl-privacy-content">
                    <div class="so-ssl-privacy-notice">
						<?php echo wp_kses_post($notice_text); ?>
                    </div>

                    <div class="so-ssl-privacy-form">
                        <form method="post" action="">
							<?php wp_nonce_field('so_ssl_privacy_acknowledgment', 'so_ssl_privacy_nonce'); ?>

                            <div class="so-ssl-privacy-checkbox">
                                <label>
                                    <input type="checkbox" id="privacy_accept" name="so_ssl_privacy_accept" value="1">
									<?php echo esc_html($checkbox_text); ?>
                                </label>
                            </div>

                            <div class="so-ssl-privacy-actions">
                                <button type="submit" name="so_ssl_privacy_submit" value="1" class="so-ssl-privacy-submit" id="privacy-submit-btn">
									<?php esc_html_e('Continue', 'so-ssl'); ?>
                                </button>

                                <a href="<?php echo esc_url(wp_logout_url(home_url())); ?>" class="so-ssl-privacy-logout">
									<?php esc_html_e('Disagree and logout', 'so-ssl'); ?>
                                </a>
                            </div>
                        </form>

						<?php if ($show_error): ?>
                            <div class="so-ssl-privacy-error">
								<?php esc_html_e('You must acknowledge the privacy notice to continue.', 'so-ssl'); ?>
                            </div>
						<?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var checkbox = document.getElementById('privacy_accept');
                var submitBtn = document.getElementById('privacy-submit-btn');

                if (checkbox && submitBtn) {
                    function updateButton() {
                        submitBtn.disabled = !checkbox.checked;
                    }

                    updateButton();
                    checkbox.addEventListener('change', updateButton);
                }

                // Prevent closing the modal by clicking outside
                var overlay = document.querySelector('.so-ssl-privacy-modal-overlay');
                overlay.addEventListener('click', function(e) {
                    if (e.target === overlay) {
                        e.preventDefault();
                        e.stopPropagation();
                    }
                });
            });
        </script>
		<?php
	}

	/**
	 * Display privacy page (fallback for compatibility)
	 */
	public static function display_privacy_page() {
		// Ensure user is logged in
		if (!is_user_logged_in()) {
			auth_redirect();
		}

		// Set the filter to show the modal
		add_filter('so_ssl_show_privacy_modal', '__return_true');

		// Display the modal
		self::maybe_add_privacy_modal();
		exit;
	}

	public static function enqueue_admin_scripts($hook) {
		// Only load on our plugin's settings page
		if (strpos($hook, 'so-ssl') !== false || $hook === 'settings_page_so-ssl') {
			wp_enqueue_editor();
			wp_enqueue_media();
		}
	}

	/**
	 * Flag user for privacy check after login
	 *
	 * @param string $user_login The username
	 * @param WP_User $user The user object
	 */
	public static function flag_user_for_privacy_check($user_login, $user) {
		// Check if user requires acknowledgment based on role
		$required_roles = get_option('so_ssl_privacy_required_roles', array('subscriber', 'contributor', 'author', 'editor'));
		$exempt_admins = get_option('so_ssl_privacy_exempt_admins', true);

		$user_requires_check = false;

		// If administrator exemption is enabled and user is admin, skip check
		if ($exempt_admins && user_can($user->ID, 'manage_options')) {
			return;
		}

		// Check if user has any of the required roles
		foreach ($user->roles as $role) {
			if (in_array($role, $required_roles)) {
				$user_requires_check = true;
				break;
			}
		}

		// If user doesn't need to check, exit early
		if (!$user_requires_check) {
			return;
		}

		// Check acknowledgment status
		$acknowledgment = get_user_meta($user->ID, 'so_ssl_privacy_acknowledged', true);
		$expiry_days = intval(get_option('so_ssl_privacy_expiry_days', 30));

		// Check if acknowledgment has expired or doesn't exist
		if (empty($acknowledgment) ||
		    (time() - intval($acknowledgment)) > ($expiry_days * DAY_IN_SECONDS)) {
			// Set session cookie to indicate privacy notice needed
			setcookie('so_ssl_privacy_needed', '1', 0, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
		}
	}

	/**
	 * Fix logout URL to prevent privacy compliance interference
	 *
	 * @param string $logout_url The logout URL
	 * @param string $redirect The redirect URL after logout
	 * @return string The fixed logout URL
	 */
	public static function fix_logout_url($logout_url, $redirect) {
		// Add a parameter to identify this as a logout action
		$logout_url = add_query_arg('bypass_privacy_check', '1', $logout_url);
		return $logout_url;
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

		// Call the action for adding the flush button
		do_action('so_ssl_privacy_compliance_section_after');
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
	 * Privacy troubleshooting callback
	 */
	public static function privacy_troubleshoot_callback() {
		// Call the function directly that outputs the properly escaped HTML
		self::output_flush_rules_button();
	}

	/**
	 * Output the troubleshooting section with flush rewrite rules button
	 * This outputs directly rather than returning a string
	 */
	public static function output_flush_rules_button() {
		// Only show to admins
		if (!current_user_can('manage_options')) {
			return;
		}

		// Process the flush if requested
		if (isset($_POST['so_ssl_flush_rules_nonce']) &&
		    wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['so_ssl_flush_rules_nonce'])), 'so_ssl_flush_rules')) {
			flush_rewrite_rules();
			echo '<div class="notice notice-success"><p>' . esc_html__('Rewrite rules have been flushed successfully.', 'so-ssl') . '</p></div>';
		}

		// Display the button
		?>
        <div class="so-ssl-admin-section" style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-left: 4px solid #72aee6;">
            <h3><?php esc_html_e('Troubleshooting', 'so-ssl'); ?></h3>
            <p><?php esc_html_e('If the privacy page is returning a 404 error, try flushing the rewrite rules:', 'so-ssl'); ?></p>
            <form method="post">
				<?php wp_nonce_field('so_ssl_flush_rules', 'so_ssl_flush_rules_nonce'); ?>
                <input type="submit" class="button button-secondary" value="<?php esc_attr_e('Flush Rewrite Rules', 'so-ssl'); ?>">
            </form>
        </div>
		<?php
	}

	/**
	 * Show troubleshooting section with flush rewrite rules button
	 */
	public static function add_flush_rules_button() {
		// Only show to admins
		if (!current_user_can('manage_options')) {
			return;
		}

		// Process the flush if requested
		if (isset($_POST['so_ssl_flush_rules_nonce']) &&
		    wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['so_ssl_flush_rules_nonce'])), 'so_ssl_flush_rules')) {
			flush_rewrite_rules();
			echo '<div class="notice notice-success"><p>' . esc_html__('Rewrite rules have been flushed successfully.', 'so-ssl') . '</p></div>';
		}

		// Display the button
		?>
        <div class="so-ssl-admin-section" style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-left: 4px solid #72aee6;">
            <h3><?php esc_html_e('Troubleshooting', 'so-ssl'); ?></h3>
            <p><?php esc_html_e('If the privacy page is returning a 404 error, try flushing the rewrite rules:', 'so-ssl'); ?></p>
            <form method="post">
				<?php wp_nonce_field('so_ssl_flush_rules', 'so_ssl_flush_rules_nonce'); ?>
                <input type="submit" class="button button-secondary" value="<?php esc_attr_e('Flush Rewrite Rules', 'so-ssl'); ?>">
            </form>
        </div>
		<?php
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
}

// Initialize the class
So_SSL_Privacy_Compliance::init();
So_SSL_Privacy_Compliance::register_privacy_template();

// Add the JavaScript to admin footer
add_action('admin_footer', array('So_SSL_Privacy_Compliance', 'add_admin_footer_js'));