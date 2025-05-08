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

		// Check if admin has acknowledged the agreement - must run at 9 to catch plugin pages
		add_action('admin_init', array(__CLASS__, 'check_admin_agreement'), 9);

		// Register admin settings
		add_action('admin_init', array(__CLASS__, 'register_settings'));

		// Add AJAX handler for saving agreement
		add_action('wp_ajax_so_ssl_save_admin_agreement', array(__CLASS__, 'ajax_save_admin_agreement'));
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

		// Check if admin has already agreed
		$user_id = get_current_user_id();
		$agreement = get_user_meta($user_id, 'so_ssl_admin_agreement_accepted', true);
		$expiry_days = intval(get_option('so_ssl_admin_agreement_expiry_days', 365));

		// Check if agreement has expired or doesn't exist
		if (empty($agreement) || (time() - intval($agreement)) > ($expiry_days * DAY_IN_SECONDS)) {
			// Redirect to agreement page
			wp_redirect(admin_url('options-general.php?page=so-ssl-agreement'));
			exit;
		}
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

		// Add admin menu item
		add_action('admin_menu', array(__CLASS__, 'add_agreement_menu'), 999);
	}

	/**
	 * Add agreement menu
	 */
	public static function add_agreement_menu() {
		// Add a menu item that won't show in the menu but is accessible via URL
		add_submenu_page(
			null, // Don't show in menu
			__('Admin Agreement', 'so-ssl'),
			__('Admin Agreement', 'so-ssl'),
			'manage_options',
			'so-ssl-agreement',
			array(__CLASS__, 'display_agreement_page')
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
	 * Display admin agreement page
	 */
	public static function display_agreement_page() {
		// Check user capabilities
		if (!current_user_can('manage_options')) {
			return;
		}

		$page_title = get_option('so_ssl_admin_agreement_title', 'Administrator Agreement Required');
		$agreement_text = get_option('so_ssl_admin_agreement_text', '');
		$checkbox_text = get_option('so_ssl_admin_agreement_checkbox_text', '');

		// Get the referring plugin page (for return after acceptance)
		$referer = isset($_SERVER['HTTP_REFERER']) ? wp_unslash($_SERVER['HTTP_REFERER']) : '';
		$redirect_url = !empty($referer) && strpos($referer, 'page=so-ssl') !== false ?
			$referer : admin_url('options-general.php?page=so-ssl');

		?>
        <div class="wrap">
            <h1><?php echo esc_html($page_title); ?></h1>

            <div id="so-ssl-agreement-container" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04); margin: 20px 0;">
                <div class="so-ssl-agreement-content">
					<?php echo wp_kses_post($agreement_text); ?>
                </div>

                <div class="so-ssl-agreement-form" style="margin-top: 20px; padding: 15px; background: #f8f9fa;">
                    <form id="so-ssl-admin-agreement-form">
						<?php wp_nonce_field('so_ssl_admin_agreement', 'so_ssl_admin_agreement_nonce'); ?>
                        <input type="hidden" id="so_ssl_redirect_url" value="<?php echo esc_url($redirect_url); ?>">

                        <div class="so-ssl-agreement-checkbox" style="margin-bottom: 15px;">
                            <label>
                                <input type="checkbox" id="so_ssl_admin_agreement_accept" name="so_ssl_admin_agreement_accept" value="1" required>
								<?php echo esc_html($checkbox_text); ?>
                            </label>
                        </div>

                        <div class="so-ssl-agreement-actions">
                            <button type="submit" id="so_ssl_agreement_submit" class="button button-primary" disabled>
								<?php esc_html_e('Accept and Continue', 'so-ssl'); ?>
                            </button>
                        </div>

                        <div id="so-ssl-agreement-message" style="margin-top: 10px; display: none;"></div>
                    </form>
                </div>

                <div class="so-ssl-agreement-options" style="margin-top: 20px; border-top: 1px solid #ddd; padding-top: 15px;">
                    <p>
						<?php esc_html_e('If you don\'t want to agree at this time:', 'so-ssl'); ?>
                        <a href="<?php echo esc_url(admin_url('options-general.php?page=so-ssl')); ?>" class="button">
							<?php esc_html_e('Return to Plugin Settings', 'so-ssl'); ?>
                        </a>
                        <a href="<?php echo esc_url(admin_url()); ?>" class="button">
							<?php esc_html_e('Return to Dashboard', 'so-ssl'); ?>
                        </a>
                    </p>
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
                                    .addClass('notice notice-success')
                                    .html('<p>' + response.data.message + '</p>')
                                    .show();

                                // Redirect to the original plugin page after 1 second
                                setTimeout(function() {
                                    window.location.href = $('#so_ssl_redirect_url').val();
                                }, 1000);
                            } else {
                                $('#so-ssl-agreement-message')
                                    .addClass('notice notice-error')
                                    .html('<p>' + response.data.message + '</p>')
                                    .show();

                                // Reset button
                                $('#so_ssl_agreement_submit').prop('disabled', false).text('<?php esc_html_e('Accept and Continue', 'so-ssl'); ?>');
                            }
                        },
                        error: function() {
                            $('#so-ssl-agreement-message')
                                .addClass('notice notice-error')
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
