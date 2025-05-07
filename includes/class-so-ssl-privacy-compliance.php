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

		// Check if user has acknowledged the privacy notice
		add_action('init', array(__CLASS__, 'check_privacy_acknowledgment'));

		// Add acknowledgment page
		add_action('wp_login', array(__CLASS__, 'flag_user_for_privacy_check'), 10, 2);

		// Register admin settings
		add_action('admin_init', array(__CLASS__, 'register_settings'));

		// Add settings change detection to trigger rewrite flush
		add_action('update_option_so_ssl_enable_privacy_compliance', array(__CLASS__, 'maybe_flush_rules'), 10, 2);
		add_action('update_option_so_ssl_privacy_page_slug', array(__CLASS__, 'maybe_flush_rules'), 10, 2);

		// Add flush button to the admin interface
		add_action('so_ssl_privacy_compliance_section_after', array(__CLASS__, 'add_flush_rules_button'));
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
	 * Check if user has acknowledged the privacy notice
	 */
	public static function check_privacy_acknowledgment() {
		// Skip for AJAX, Cron, CLI, or admin-ajax.php requests
		if (wp_doing_ajax() || wp_doing_cron() || (defined('WP_CLI') && WP_CLI) ||
		    (isset($_SERVER['SCRIPT_FILENAME']) && strpos($_SERVER['SCRIPT_FILENAME'], 'admin-ajax.php') !== false)) {
			return;
		}

		// Only check for logged-in users
		if (!is_user_logged_in()) {
			return;
		}

		// Get current user
		$current_user = wp_get_current_user();

		// Check if exempt by role
		$required_roles = get_option('so_ssl_privacy_required_roles', array('subscriber', 'contributor', 'author', 'editor'));
		$exempt_admins = get_option('so_ssl_privacy_exempt_admins', true);

		$user_requires_check = false;

		// If administrator exemption is enabled and user is admin, skip check
		if ($exempt_admins && current_user_can('manage_options')) {
			return;
		}

		// Check if user has any of the required roles
		foreach ($current_user->roles as $role) {
			if (in_array($role, $required_roles)) {
				$user_requires_check = true;
				break;
			}
		}

		// If user doesn't need to check, exit early
		if (!$user_requires_check) {
			return;
		}

		// Check if privacy notice is needed based on cookie
		if (isset($_COOKIE['so_ssl_privacy_needed']) && $_COOKIE['so_ssl_privacy_needed'] === '1') {
			// Check if we're already on the privacy page
			$privacy_slug = sanitize_title(get_option('so_ssl_privacy_page_slug', 'privacy-acknowledgment'));

			// Don't redirect if we're already on the privacy page
			$current_url = sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI']));
			if (strpos($current_url, $privacy_slug) !== false) {
				return;
			}

			// Don't redirect for specified allowed paths (add logout URL to avoid lockout)
			$allowed_paths = array(
				'wp-login.php?action=logout',
				'wp-login.php?loggedout=true',
				admin_url('admin-ajax.php'),
				'/wp-json/'
			);

			foreach ($allowed_paths as $path) {
				if (strpos($current_url, $path) !== false) {
					return;
				}
			}

			// Redirect to privacy acknowledgment page
			$privacy_url = home_url($privacy_slug);
			wp_redirect($privacy_url);
			exit;
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

		echo '<input type="text" id="so_ssl_privacy_page_slug" name="so_ssl_privacy_page_slug" value="' . esc_attr($page_slug) . '" class="regular-text" />';
		echo '<p class="description">' . esc_html__('URL slug for the privacy acknowledgment page.', 'so-ssl') . '</p>';
		echo '<p class="description"><strong>' . esc_html__('Note:', 'so-ssl') . '</strong> ' . esc_html__('Changing this will require flushing rewrite rules.', 'so-ssl') . '</p>';
	}

	/**
	 * Privacy notice text field callback
	 */
	public static function privacy_notice_text_callback() {
		$notice_text = get_option('so_ssl_privacy_notice_text', 'This site tracks certain information for security purposes including IP addresses, login attempts, and session data. By using this site, you acknowledge and consent to this data collection in accordance with our Privacy Policy and applicable data protection laws including GDPR and US privacy regulations.');

		echo '<textarea id="so_ssl_privacy_notice_text" name="so_ssl_privacy_notice_text" rows="5" class="large-text">' . esc_textarea($notice_text) . '</textarea>';
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
			$selected = in_array($role_value, $required_roles) ? 'selected="selected"' : '';
			echo '<option value="' . esc_attr($role_value) . '" ' . $selected . '>' . esc_html($role_name) . '</option>';
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
	 * Mark rewrite rules for flushing when settings change
	 */
	public static function maybe_flush_rules($old_value, $new_value) {
		if ($old_value !== $new_value) {
			update_option('so_ssl_flush_rewrite_rules', true);
		}
	}

	/**
	 * Register the privacy acknowledgment template
	 */
	public static function register_privacy_template() {
		// Register query var
		add_filter('query_vars', array(__CLASS__, 'add_query_vars'));

		// Add rewrite rules
		add_action('init', array(__CLASS__, 'add_rewrite_rules'), 10);

		// Handle template loading
		add_action('template_redirect', array(__CLASS__, 'load_privacy_template'));
	}

	/**
	 * Add custom query vars
	 *
	 * @param array $vars The array of available query variables
	 * @return array Modified array of query variables
	 */
	public static function add_query_vars($vars) {
		$vars[] = 'so_ssl_privacy';
		return $vars;
	}

	/**
	 * Add rewrite rules for the privacy page
	 */
	public static function add_rewrite_rules() {
		$slug = sanitize_title(get_option('so_ssl_privacy_page_slug', 'privacy-acknowledgment'));

		// Make sure the rule is correct and uses a proper regex
		add_rewrite_rule(
			'^' . $slug . '/?$',
			'index.php?so_ssl_privacy=1',
			'top'
		);

		// Check if we need to flush rewrite rules
		if (get_option('so_ssl_flush_rewrite_rules', false)) {
			// This is resource-intensive, so we only do it when needed
			flush_rewrite_rules();
			update_option('so_ssl_flush_rewrite_rules', false);
		}
	}

	/**
	 * Load the privacy template
	 */
	public static function load_privacy_template() {
		global $wp_query;

		if (isset($wp_query->query_vars['so_ssl_privacy']) && $wp_query->query_vars['so_ssl_privacy'] == 1) {
			// Process form submission
			if (isset($_POST['so_ssl_privacy_submit']) && isset($_POST['so_ssl_privacy_nonce'])) {
				if (wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['so_ssl_privacy_nonce'])), 'so_ssl_privacy_acknowledgment')) {
					if (isset($_POST['so_ssl_privacy_accept']) && $_POST['so_ssl_privacy_accept'] == '1') {
						// User has acknowledged
						$user_id = get_current_user_id();
						update_user_meta($user_id, 'so_ssl_privacy_acknowledged', time());

						// Clear the privacy needed cookie
						setcookie('so_ssl_privacy_needed', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);

						// Redirect to previous page or dashboard
						$redirect = isset($_COOKIE['so_ssl_privacy_redirect']) ? $_COOKIE['so_ssl_privacy_redirect'] : admin_url();
						wp_redirect($redirect);
						exit;
					}
				}
			}

			// Set redirect cookie if referrer is available
			if (isset($_SERVER['HTTP_REFERER'])) {
				$referer = wp_sanitize_redirect($_SERVER['HTTP_REFERER']);
				// Only set if it's on the same domain
				$site_url = site_url();
				if (strpos($referer, $site_url) === 0) {
					setcookie('so_ssl_privacy_redirect', $referer, 0, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
				}
			}

			// Load the template
			self::display_privacy_page();
			exit;
		}
	}

	/**
	 * Display the privacy acknowledgment page
	 */
	public static function display_privacy_page() {
		$page_title = get_option('so_ssl_privacy_page_title', 'Privacy Acknowledgment Required');
		$notice_text = get_option('so_ssl_privacy_notice_text', '');
		$checkbox_text = get_option('so_ssl_privacy_checkbox_text', '');

		// Start output buffering to capture all output
		ob_start();
		?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php echo esc_html($page_title); ?> - <?php bloginfo('name'); ?></title>
			<?php wp_head(); ?>
            <style>
                .so-ssl-privacy-page {
                    max-width: 800px;
                    margin: 50px auto;
                    padding: 20px;
                    background: #fff;
                    border-radius: 5px;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                }
                .so-ssl-privacy-title {
                    color: #2271b1;
                    border-bottom: 1px solid #dcdcde;
                    padding-bottom: 10px;
                    margin-bottom: 20px;
                }
                .so-ssl-privacy-content {
                    margin-bottom: 30px;
                    line-height: 1.5;
                }
                .so-ssl-privacy-form {
                    padding: 20px;
                    background: #f0f6fc;
                    border-left: 4px solid #2271b1;
                    margin-bottom: 20px;
                }
                .so-ssl-privacy-checkbox {
                    margin: 15px 0;
                }
                .so-ssl-privacy-submit {
                    background: #2271b1;
                    border-color: #2271b1;
                    color: #fff;
                    padding: 10px 15px;
                    border: none;
                    border-radius: 3px;
                    cursor: pointer;
                }
                .so-ssl-privacy-submit:hover {
                    background: #135e96;
                }
                .so-ssl-privacy-submit:disabled {
                    background: #c3c4c7;
                    cursor: not-allowed;
                }
                .so-ssl-privacy-error {
                    color: #d63638;
                    margin-top: 10px;
                }
            </style>
        </head>
        <body <?php body_class(); ?>>
        <div class="so-ssl-privacy-page">
            <h1 class="so-ssl-privacy-title"><?php echo esc_html($page_title); ?></h1>

            <div class="so-ssl-privacy-content">
				<?php echo wp_kses_post($notice_text, array('so-ssl')); ?>
            </div>

            <form class="so-ssl-privacy-form" method="post">
				<?php wp_nonce_field('so_ssl_privacy_acknowledgment', 'so_ssl_privacy_nonce'); ?>

                <div class="so-ssl-privacy-checkbox">
                    <label>
                        <input type="checkbox" name="so_ssl_privacy_accept" value="1" required>
						<?php echo esc_html($checkbox_text); ?>
                    </label>
                </div>

                <div class="so-ssl-privacy-actions">
                    <button type="submit" name="so_ssl_privacy_submit" class="so-ssl-privacy-submit">
						<?php esc_html_e('Continue', 'so-ssl'); ?>
                    </button>
                </div>

				<?php if (isset($_POST['so_ssl_privacy_submit']) && (!isset($_POST['so_ssl_privacy_accept']) || $_POST['so_ssl_privacy_accept'] != '1')): ?>
                    <div class="so-ssl-privacy-error">
						<?php esc_html_e('You must acknowledge the privacy notice to continue.', 'so-ssl'); ?>
                    </div>
				<?php endif; ?>
            </form>

            <div class="so-ssl-privacy-links">
                <a href="<?php echo esc_url(wp_logout_url()); ?>">
					<?php esc_html_e('Logout', 'so-ssl'); ?>
                </a>
            </div>
        </div>

        <script>
            // Simple script to enable/disable submit button based on checkbox
            document.addEventListener('DOMContentLoaded', function() {
                var checkbox = document.querySelector('input[name="so_ssl_privacy_accept"]');
                var submitBtn = document.querySelector('.so-ssl-privacy-submit');

                if (checkbox && submitBtn) {
                    submitBtn.disabled = !checkbox.checked;

                    checkbox.addEventListener('change', function() {
                        submitBtn.disabled = !this.checked;
                    });
                }
            });
        </script>

		<?php wp_footer(); ?>
        </body>
        </html>
		<?php
		// Output the buffered content
		echo ob_get_clean();
	}

	/**
	 * Add a link to view the actual page
	 */
	public static function add_view_actual_page_link() {
		// Get the privacy page slug
		$privacy_slug = sanitize_title(get_option('so_ssl_privacy_page_slug', 'privacy-acknowledgment'));

		// Create the URL
		$privacy_url = home_url($privacy_slug);

		// Output the link
		echo '<p class="so-ssl-view-page">';
		echo esc_html__('To view the actual privacy acknowledgment page, visit:', 'so-ssl') . ' ';
		echo '<a href="' . esc_url($privacy_url) . '" target="_blank">' . esc_html($privacy_url) . '</a>';
		echo '</p>';
	}

	/**
	 * Add JavaScript to update the preview in real-time
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
