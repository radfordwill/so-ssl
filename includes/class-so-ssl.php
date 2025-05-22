<?php
  /**
   * The core plugin class.
   *
   * @since      1.1.0
   * @package    So_SSL
   */

// If this file is called directly, abort.
if (!defined('WPINC')) {
	die;
}

class So_SSL {

    /**
     * Plugin version.
     *
     * @since    1.1.0
     * @access   private
     * @var      string    $version    The current version of the plugin.
     */
    private $version;

    /**
     * Plugin path.
     *
     * @since    1.0.2
     * @access   private
     * @var      string    $plugin_path    The path to the plugin directory.
     */
    private $plugin_path;

    /**
     * Plugin URL.
     *
     * @since    1.0.2
     * @access   private
     * @var      string    $plugin_url    The URL to the plugin directory.
     */
    private $plugin_url;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.2
     */
    public function __construct() {
        $this->version = SO_SSL_VERSION;
        $this->plugin_path = SO_SSL_PATH;
        $this->plugin_url = SO_SSL_URL;

	    // Ensure plugin path and URL are never null
	    if (empty($this->plugin_path)) {
		    $this->plugin_path = dirname(__FILE__) . '/';
	    }
	    if (empty($this->plugin_url)) {
		    $this->plugin_url = plugins_url('/', __FILE__);
	    }

        $this->load_dependencies();
    }

    /**
     * Load required dependencies.
     *
     * @since    1.0.2
     * @access   private
     */
    private function load_dependencies() {
        // Load Two-Factor Authentication
        $this->load_two_factor_authentication();
    }

    /**
     * Load Two-Factor Authentication functionality
     *
     * @since 1.2.0
     */
	public function load_two_factor_authentication() {

        if ( ! class_exists( 'TOTP' ) ) {
		// Always load TOTP implementation (needed for settings UI)
		require_once SO_SSL_PATH . 'includes/class-so-ssl-totp.php';
        }

		// Only load the actual 2FA functionality if it's enabled
		if (get_option('so_ssl_enable_2fa', 0)) {
			// Load session handler first
			require_once SO_SSL_PATH . 'includes/class-so-ssl-session-handler.php';

			// Load 2FA functionality
			require_once SO_SSL_PATH . 'includes/class-so-ssl-two-factor.php';
		}
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
	}

	/**
	 * Privacy notice text field callback
	 *
	 * @since    1.4.4
	 * @access   private
	 *
	 */
	public static function privacy_notice_text_callback() {
		// Get saved content
		$notice_text = get_option('so_ssl_privacy_notice_text', 'This site tracks certain information for security purposes including IP addresses, login attempts, and session data. By using this site, you acknowledge and consent to this data collection in accordance with our Privacy Policy and applicable data protection laws including GDPR and US privacy regulations.');

		// Force load WordPress editor scripts directly
		if (function_exists('wp_enqueue_editor')) {
			wp_enqueue_editor();
			wp_enqueue_media();
		}

		// Basic editor settings - simplified for troubleshooting
		$editor_settings = array(
			'textarea_name' => 'so_ssl_privacy_notice_text', // Field name
			'textarea_rows' => 10,
			'teeny'         => true, // Use minimal editor toolbar
			'wpautop'       => true, // Add paragraphs automatically
		);

		// Add a comment that will show in HTML source to confirm this function is called
		echo "<!-- TinyMCE editor should appear below this line -->";

		// Output the editor
		wp_editor(
			wp_kses_post($notice_text),  // Sanitize content
			'so_ssl_privacy_notice_text', // Editor ID - must match field name for simplicity
			$editor_settings
		);

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
     * Run the loader to execute all hooks.
     *
     * @since    1.0.2
     */
    public function run() {
        // Register all hooks
        $this->define_admin_hooks();
        $this->define_public_hooks();
    }

    /**
     * Register all of the hooks related to the admin area functionality.
     *
     * @since    1.0.2
     * @access   private
     */
    private function define_admin_hooks() {
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));

        // Add settings
        add_action('admin_init', array($this, 'register_settings'));

        // Admin footer JS
        add_action('admin_footer', array($this, 'admin_footer_js'));

        // Add initialization of 2FA directories
        add_action('admin_init', array($this, 'initialize_2fa_directories'));

        // Enqueue admin styles and scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_styles'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }

   /**
 * Register all of the hooks related to the public-facing functionality.
 *
 * @since    1.0.2
 * @access   private
 */
private function define_public_hooks() {
    // Check SSL and redirect if needed
    add_action('template_redirect', array($this, 'check_ssl'));

    // Add security headers
    add_action('send_headers', array($this, 'add_hsts_header'));
    add_action('send_headers', array($this, 'add_xframe_header'));
    add_action('send_headers', array($this, 'add_csp_frame_ancestors_header'));
    add_action('send_headers', array($this, 'add_referrer_policy_header'));
    add_action('send_headers', array($this, 'add_content_security_policy_header'));
    add_action('send_headers', array($this, 'add_permissions_policy_header'));
    add_action('send_headers', array($this, 'add_cross_origin_policy_headers'));

    // Enforce strong passwords if enabled
if (get_option('so_ssl_disable_weak_passwords', 0)) {
    // Disable application passwords
    add_filter('wp_is_application_passwords_available', '__return_false');

    // Add script to every page where passwords might be set
    add_action('login_enqueue_scripts', array($this, 'disable_weak_password_js'));
    add_action('admin_enqueue_scripts', array($this, 'disable_weak_password_js'));
    add_action('resetpass_form', array($this, 'disable_weak_password_js'));
    add_action('register_form', array($this, 'disable_weak_password_js'));

    // Force high priority to override WP defaults
    add_action('admin_footer', array($this, 'disable_weak_password_js'), 99);
    add_action('login_footer', array($this, 'disable_weak_password_js'), 99);

    // Add password validation hooks
    // Update the registration hook to use the filter instead of action
    add_filter('registration_errors', array($this, 'enforce_strong_password'), 10, 3);

    // Make sure validate_password_reset still uses action
    add_action('validate_password_reset', array($this, 'validate_password_reset'), 10, 2);

    // Profile update can stay as action
    add_action('user_profile_update_errors', array($this, 'enforce_strong_password'), 10, 3);
}
}

    /**
     * Initialize directories needed for 2FA functionality
     *
     * @since 1.2.0
     */
    public function initialize_2fa_directories() {
        // Create required directories if they don't exist
        $directories = array(
            SO_SSL_PATH . 'includes',
            SO_SSL_PATH . 'assets',
            SO_SSL_PATH . 'assets/css',
            SO_SSL_PATH . 'assets/js'
        );

        foreach ($directories as $directory) {
            if (!file_exists($directory)) {
                wp_mkdir_p($directory);
            }
        }
    }

    /**
     * Add admin menu for plugin settings.
     *
     * @since    1.0.2
     */
	public function add_admin_menu() {
		$page_title = __('So SSL Settings', 'so-ssl');
		$menu_title = __('So SSL', 'so-ssl');

		// Ensure titles are never null
		if (empty($page_title)) {
			$page_title = 'So SSL Settings';
		}
		if (empty($menu_title)) {
			$menu_title = 'So SSL';
		}

		add_options_page(
			'So SSL Settings',  // Use string literal as fallback
			'So SSL',          // Use string literal as fallback
			'manage_options',
			'so-ssl',
			array($this, 'display_options_page')
		);
	}

    /**
     * Enqueue admin styles
     */
    public function enqueue_admin_styles($hook) {
        // Only load on plugin pages
        if (strpos($hook, 'so-ssl') !== false || $hook === 'settings_page_so-ssl') {
            wp_enqueue_style('so-ssl-admin', SO_SSL_URL . 'assets/css/so-ssl-admin.css', array(), SO_SSL_VERSION);
            wp_enqueue_style('dashicons');
	        wp_enqueue_style('so-ssl-admin', SO_SSL_URL . 'assets/js/lib/qrcodejs/1.0.0/qrcode.min.js', array(), '1.0.0');
	        //wordpress\wp-content\plugins\so-ssl\assets\js\lib\qrcodejs\1.0.0\qrcode.min.js"
        }
    }

	/**
	 * Enqueue admin scripts
	 */
	public function enqueue_admin_scripts($hook) {
		// Only load on plugin pages
		if (strpos($hook, 'so-ssl') !== false || $hook === 'settings_page_so-ssl') {
			wp_enqueue_script('so-ssl-admin', SO_SSL_URL . 'assets/js/so-ssl-admin.js', array('jquery'), SO_SSL_VERSION, true);

			// Add translation strings and other data for JavaScript
			wp_localize_script('so-ssl-admin', 'soSslAdmin', array(
				'ajaxUrl' => admin_url('admin-ajax.php'),
				'nonce' => wp_create_nonce('so_ssl_admin_nonce')
				// Removed the warning message, but it's not harmful to leave it either
			));
		}

		// Load on any admin page that might include our settings
		wp_enqueue_editor();
		wp_enqueue_media();

		// Also load TinyMCE-specific CSS
		wp_enqueue_style('editor-buttons');
	}

    /**
     * Plugin settings page content.
     *
     * @since    1.0.2
     */
    public function display_options_page() {
        ?>
        <div class="wrap so-ssl-wrap">
            <div class="so-ssl-header">
                <h1>
                  <svg viewBox="0 0 500 300" xmlns="http://www.w3.org/2000/svg">
                    <!-- Background Shield -->
                    <path d="M250 30 L380 80 L380 170 C380 240 320 270 250 290 C180 270 120 240 120 170 L120 80 Z" fill="#f1f5f9" stroke="#334155" stroke-width="8"/>

                    <!-- So Text -->
                    <text x="250" y="100" font-family="Arial, sans-serif" font-size="40" font-weight="bold" text-anchor="middle" fill="#0ea5e9">So</text>

                    <!-- SSL Text -->
                    <text x="250" y="160" font-family="Arial, sans-serif" font-size="80" font-weight="bold" text-anchor="middle" fill="#334155">SSL</text>

                    <!-- 3D Lock - positioned higher on the shield -->
                    <g transform="translate(335, 250) rotate(-25) scale(1.5)">
                      <!-- Lock Body Shadow (for 3D effect) -->
                      <rect x="-22" y="0" width="44" height="32" rx="5" ry="5" fill="#0c4a6e" opacity="0.5"/>

                      <!-- Lock Body -->
                      <rect x="-20" y="-2" width="40" height="30" rx="5" ry="5" fill="#0ea5e9"/>

                      <!-- Lock Body Highlight (for 3D effect) -->
                      <rect x="-18" y="-2" width="36" height="5" rx="2" ry="2" fill="#7dd3fc" opacity="0.5"/>

                      <!-- Lock Shackle Behind Shield (for 3D effect) -->
                      <path d="M-10 -2 L-10 -14 C-10 -23 10 -23 10 -14 L10 -2" fill="none" stroke="#64748b" stroke-width="6" stroke-linecap="round"/>

                      <!-- Lock Shackle Front (for 3D effect) -->
                      <path d="M-10 -2 L-10 -15 C-10 -25 10 -25 10 -15 L10 -2" fill="none" stroke="#334155" stroke-width="6" stroke-linecap="round"/>

                      <!-- Shackle Highlights (for 3D effect) -->
                      <path d="M-10 -15 C-10 -24 10 -24 10 -15" fill="none" stroke="#f1f5f9" stroke-width="1.5" stroke-linecap="round" opacity="0.7"/>

                      <!-- Keyhole Outer -->
                      <circle cx="0" cy="13" r="6" fill="#0c4a6e"/>

                      <!-- Keyhole Inner -->
                      <circle cx="0" cy="13" r="5" fill="#64748b"/>
                      <rect x="-1.5" y="13" width="3" height="7" fill="#64748b"/>

                      <!-- Keyhole Highlight (for 3D effect) -->
                      <path d="M-3 10 A5 5 0 0 1 3 10" stroke="#f1f5f9" stroke-width="1" fill="none" opacity="0.7"/>
                    </g>

                    <!-- Security Dots -->
                    <circle cx="145" cy="210" r="8" fill="#0ea5e9" opacity="0.7"/>
                    <circle cx="355" cy="210" r="8" fill="#0ea5e9" opacity="0.7"/>
                    <circle cx="170" cy="250" r="6" fill="#0ea5e9" opacity="0.5"/>
                    <circle cx="330" cy="250" r="6" fill="#0ea5e9" opacity="0.5"/>
                  </svg>
                    <?php echo esc_html(get_admin_page_title()); ?>
                    <span class="so-ssl-version">v<?php echo esc_html(SO_SSL_VERSION); ?></span>
                </h1>
            </div>

            <!-- Security Status Dashboard -->
            <div class="so-ssl-security-status">
                <div class="so-ssl-security-score">
                    <h2><?php esc_html_e('Security Status', 'so-ssl'); ?></h2>
                    <div class="so-ssl-score-circle">
                        <div class="so-ssl-score-fill" style="height: 0%;"></div>
                        <div class="so-ssl-score-text">0%</div>
                    </div>
                    <p><?php esc_html_e('Implementing more security features will improve your score', 'so-ssl'); ?></p>
                </div>

                <div class="so-ssl-security-items">
                    <?php
                    // Check SSL status
                    $ssl_status = is_ssl();
                    $ssl_class = $ssl_status ? 'so-ssl-security-good' : 'so-ssl-security-bad';
                    $ssl_icon = $ssl_status ? 'yes' : 'no';
                    $ssl_text = $ssl_status ? __('SSL is active', 'so-ssl') : __('SSL is not active', 'so-ssl');
                    ?>

                    <div class="so-ssl-security-item <?php echo esc_attr($ssl_class); ?>">
                        <span class="dashicons dashicons-<?php echo esc_attr($ssl_icon); ?>"></span>
                        <span><?php echo esc_html($ssl_text); ?></span>
                    </div>

                    <?php
                    // Check HSTS status
                    $hsts_enabled = get_option('so_ssl_enable_hsts', 0);
                    $hsts_class = $hsts_enabled ? 'so-ssl-security-good' : 'so-ssl-security-warning';
                    $hsts_icon = $hsts_enabled ? 'yes' : 'warning';
                    $hsts_text = $hsts_enabled ? __('HSTS is enabled', 'so-ssl') : __('HSTS is not enabled', 'so-ssl');
                    ?>

                    <div class="so-ssl-security-item <?php echo esc_attr($hsts_class); ?>">
                        <span class="dashicons dashicons-<?php echo esc_attr($hsts_icon); ?>"></span>
                        <span><?php echo esc_html($hsts_text); ?></span>
                    </div>

                    <?php
                    // Check 2FA status
                    $twofa_enabled = get_option('so_ssl_enable_2fa', 0);
                    $twofa_class = $twofa_enabled ? 'so-ssl-security-good' : 'so-ssl-security-warning';
                    $twofa_icon = $twofa_enabled ? 'yes' : 'warning';
                    $twofa_text = $twofa_enabled ? __('Two-Factor Authentication is enabled', 'so-ssl') : __('Two-Factor Authentication is not enabled', 'so-ssl');
                    ?>

                    <div class="so-ssl-security-item <?php echo esc_attr($twofa_class); ?>">
                        <span class="dashicons dashicons-<?php echo esc_attr($twofa_icon); ?>"></span>
                        <span><?php echo esc_html($twofa_text); ?></span>
                    </div>

                    <?php
                    // Check Strong Passwords status
                    $strong_pwd = get_option('so_ssl_disable_weak_passwords', 0);
                    $strong_pwd_class = $strong_pwd ? 'so-ssl-security-good' : 'so-ssl-security-warning';
                    $strong_pwd_icon = $strong_pwd ? 'yes' : 'warning';
                    $strong_pwd_text = $strong_pwd ? __('Strong Passwords are enforced', 'so-ssl') : __('Strong Passwords are not enforced', 'so-ssl');
                    ?>

                    <div class="so-ssl-security-item <?php echo esc_attr($strong_pwd_class); ?>">
                        <span class="dashicons dashicons-<?php echo esc_attr($strong_pwd_icon); ?>"></span>
                        <span><?php echo esc_html($strong_pwd_text); ?></span>
                    </div>
                </div>
            </div>

            <div class="nav-tab-wrapper">
                <a href="#ssl-settings" class="nav-tab nav-tab-active"><?php esc_html_e('SSL Settings', 'so-ssl'); ?></a>
                <a href="#content-security" class="nav-tab"><?php esc_html_e('Content Security', 'so-ssl'); ?></a>
                <a href="#browser-features" class="nav-tab"><?php esc_html_e('Browser Features', 'so-ssl'); ?></a>
                <a href="#cross-origin" class="nav-tab"><?php esc_html_e('Cross-Origin', 'so-ssl'); ?></a>
                <a href="#two-factor" class="nav-tab"><?php esc_html_e('Two-Factor Auth', 'so-ssl'); ?></a>
                <a href="#login-protection" class="nav-tab"><?php esc_html_e('Login Protection', 'so-ssl'); ?></a>
                <a href="#user-sessions" class="nav-tab"><?php esc_html_e('User Sessions', 'so-ssl'); ?></a>
                <a href="#login-limit" class="nav-tab"><?php esc_html_e('Login Limiting', 'so-ssl'); ?></a>
                <a href="#privacy-compliance" class="nav-tab"><?php esc_html_e('Privacy Compliance', 'so-ssl'); ?></a>
                <a href="#admin-agreement" class="nav-tab"><?php esc_html_e('Admin Agreement', 'so-ssl'); ?></a>
            </div>

            <form action="options.php" method="post">
                <?php settings_fields('so_ssl_options'); ?>

                <!-- SSL Settings Tab -->
                <div id="ssl-settings" class="settings-tab">
                    <h2><?php esc_html_e('SSL & Basic Security Settings', 'so-ssl'); ?></h2>
                    <?php
                    // Display current SSL status
                    if (is_ssl()) {
                        echo '<div class="so-ssl-notice so-ssl-notice-success"><p>' . esc_html__('Your site is currently using SSL/HTTPS.', 'so-ssl') . '</p></div>';
                    } else {
                        echo '<div class="so-ssl-notice so-ssl-notice-warning"><p>' . esc_html__('Your site is not using SSL/HTTPS. Enabling force SSL without having a valid SSL certificate may make your site inaccessible.', 'so-ssl') . '</p></div>';
                    }

                    do_settings_sections('so-ssl-ssl');
                    ?>
                </div>

                <!-- Content Security Tab -->
                <div id="content-security" class="settings-tab">
                    <h2><?php esc_html_e('Content Security Policies', 'so-ssl'); ?></h2>
                    <?php
                    do_settings_sections('so-ssl-csp');
                    do_settings_sections('so-ssl-referrer');
                    ?>
                </div>

                <!-- Browser Features Tab -->
                <div id="browser-features" class="settings-tab">
                    <h2><?php esc_html_e('Browser Feature Controls', 'so-ssl'); ?></h2>
                    <?php
                    do_settings_sections('so-ssl-permissions');
                    ?>
                </div>

                <!-- Privacy Compliance Tab -->
                <div id="privacy-compliance" class="settings-tab">
                    <h2><?php esc_html_e('Privacy Compliance Settings', 'so-ssl'); ?></h2>
		            <?php
		            do_settings_sections('so-ssl-privacy');
		            ?>
                </div>

                <!-- Cross-Origin Tab -->
                <div id="cross-origin" class="settings-tab">
                    <h2><?php esc_html_e('Cross-Origin Security Controls', 'so-ssl'); ?></h2>
                    <?php
                    do_settings_sections('so-ssl-cross-origin');
                    do_settings_sections('so-ssl-xframe');
                    do_settings_sections('so-ssl-csp-frame');
                    ?>
                </div>

                <!-- Two-Factor Authentication Tab -->
                <div id="two-factor" class="settings-tab">
                    <h2><?php esc_html_e('Two-Factor Authentication', 'so-ssl'); ?></h2>

                    <!-- Administrator Setup Guide -->
                    <div class="so-ssl-admin-guide" style="background: #f0f6fc; border-left: 4px solid #2271b1; padding: 20px; margin-bottom: 20px; border-radius: 0 4px 4px 0;">
                        <h3 style="margin-top: 0; color: #2271b1;">
                            <span class="dashicons dashicons-admin-users" style="margin-right: 5px;"></span>
				            <?php esc_html_e('Administrator Setup Guide', 'so-ssl'); ?>
                        </h3>

                        <div style="margin-bottom: 20px;">
                            <h4><?php esc_html_e('Step 1: Choose Authentication Method', 'so-ssl'); ?></h4>
                            <ul style="list-style-type: disc; margin-left: 20px;">
                                <li><strong><?php esc_html_e('Email Verification:', 'so-ssl'); ?></strong> <?php esc_html_e('Easier to set up, sends codes via email. Good for most users.', 'so-ssl'); ?></li>
                                <li><strong><?php esc_html_e('Authenticator App:', 'so-ssl'); ?></strong> <?php esc_html_e('More secure, requires mobile app. Best for administrators and high-privilege users.', 'so-ssl'); ?></li>
                            </ul>
                        </div>

                        <div style="margin-bottom: 20px;">
                            <h4><?php esc_html_e('Step 2: Select User Roles', 'so-ssl'); ?></h4>
                            <p><?php esc_html_e('Choose which user roles will be required to use 2FA. We recommend:', 'so-ssl'); ?></p>
                            <ul style="list-style-type: disc; margin-left: 20px;">
                                <li><?php esc_html_e('Administrators - Always enable (highest privileges)', 'so-ssl'); ?></li>
                                <li><?php esc_html_e('Editors - Recommended (can publish content)', 'so-ssl'); ?></li>
                                <li><?php esc_html_e('Authors - Optional (can write posts)', 'so-ssl'); ?></li>
                                <li><?php esc_html_e('Subscribers - Usually not needed', 'so-ssl'); ?></li>
                            </ul>
                        </div>

                        <div style="margin-bottom: 20px;">
                            <h4><?php esc_html_e('Step 3: User Setup Process', 'so-ssl'); ?></h4>
                            <p><?php esc_html_e('After enabling 2FA, users must:', 'so-ssl'); ?></p>
                            <ol style="margin-left: 20px;">
                                <li><?php esc_html_e('Go to their WordPress profile page', 'so-ssl'); ?></li>
                                <li><?php esc_html_e('Enable 2FA in the "Two-Factor Authentication" section', 'so-ssl'); ?></li>
                                <li><?php esc_html_e('Complete the setup process for their chosen method', 'so-ssl'); ?></li>
                                <li><?php esc_html_e('Generate and save backup codes', 'so-ssl'); ?></li>
                            </ol>
                        </div>

                        <div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 4px;">
                            <h4 style="margin-top: 0; color: #856404;">
                                <span class="dashicons dashicons-warning" style="color: #856404;"></span>
					            <?php esc_html_e('Important Considerations', 'so-ssl'); ?>
                            </h4>
                            <ul style="list-style-type: disc; margin-left: 20px;">
                                <li><?php esc_html_e('Test 2FA with a test account before enabling for all users', 'so-ssl'); ?></li>
                                <li><?php esc_html_e('Ensure users have valid email addresses for email verification', 'so-ssl'); ?></li>
                                <li><?php esc_html_e('Educate users about backup codes and keeping them safe', 'so-ssl'); ?></li>
                                <li><?php esc_html_e('Have a recovery plan if users lose access to their 2FA method', 'so-ssl'); ?></li>
                                <li><?php esc_html_e('Consider creating documentation for your users', 'so-ssl'); ?></li>
                            </ul>
                        </div>
                    </div>

		            <?php
		            do_settings_sections('so-ssl-2fa');
		            ?>

		            <?php if (get_option('so_ssl_enable_2fa', 0)): ?>
                        <div class="so-ssl-notice" style="background: #d4edda; border-left: 4px solid #28a745; padding: 15px; margin-top: 20px;">
                            <h4 style="margin-top: 0; color: #155724;"><?php esc_html_e('✓ Two-Factor Authentication is Enabled', 'so-ssl'); ?></h4>
                            <p><strong><?php esc_html_e('Next Steps:', 'so-ssl'); ?></strong></p>
                            <ol>
                                <li><?php esc_html_e('Verify the authentication method is set correctly above', 'so-ssl'); ?></li>
                                <li><?php esc_html_e('Check that the correct user roles are selected', 'so-ssl'); ?></li>
                                <li><?php esc_html_e('Inform users to set up 2FA in their profile settings', 'so-ssl'); ?></li>
                                <li><?php esc_html_e('Test the 2FA login process with a test account', 'so-ssl'); ?></li>
                                <li><?php esc_html_e('Monitor for any user issues or support requests', 'so-ssl'); ?></li>
                            </ol>

                            <div style="margin-top: 15px; padding: 10px; background: #f8f9fa; border-radius: 4px;">
                                <p style="margin: 0;">
                                    <strong><?php esc_html_e('Quick Links:', 'so-ssl'); ?></strong>
                                    <a href="<?php echo esc_url(admin_url('profile.php#so_ssl_2fa_enabled')); ?>" style="margin: 0 10px;"><?php esc_html_e('Your 2FA Settings', 'so-ssl'); ?></a>
                                    <a href="<?php echo esc_url(admin_url('users.php')); ?>" style="margin: 0 10px;"><?php esc_html_e('User Management', 'so-ssl'); ?></a>
                                </p>
                            </div>
                        </div>
		            <?php else: ?>
                        <div class="so-ssl-notice" style="background: #f8d7da; border-left: 4px solid #dc3545; padding: 15px; margin-top: 20px;">
                            <h4 style="margin-top: 0; color: #721c24;"><?php esc_html_e('Two-Factor Authentication is Disabled', 'so-ssl'); ?></h4>
                            <p><?php esc_html_e('Enable 2FA above to add an extra layer of security to your WordPress login process.', 'so-ssl'); ?></p>
                        </div>
		            <?php endif; ?>
                </div>

                <!-- Login Protection Tab -->
                <div id="login-protection" class="settings-tab">
                    <h2><?php esc_html_e('Login Protection Settings', 'so-ssl'); ?></h2>
                    <?php
                    do_settings_sections('so-ssl-login-protection');
                    ?>

                    <?php if (get_option('so_ssl_disable_weak_passwords', 0)): ?>
                    <div class="so-ssl-notice so-ssl-notice-success">
                        <p><?php esc_html_e('Strong password enforcement is active. Users will be required to create strong passwords when registering or changing their passwords.', 'so-ssl'); ?></p>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- User Sessions Tab -->
                <div id="user-sessions" class="settings-tab">
                    <h2><?php esc_html_e('User Sessions', 'so-ssl'); ?></h2>
                    <?php
                    do_settings_sections('so-ssl-user-sessions');
                    ?>

                    <?php if (get_option('so_ssl_enable_user_sessions', 0)): ?>
                    <div class="so-ssl-notice">
                        <p>
                            <?php
                            printf(
                                /* translators: %s: URL to User Sessions page */
                                esc_html__('Configure detailed settings and view active sessions on the %s page.', 'so-ssl'),
                                '<a href="' . esc_url(admin_url('options-general.php?page=so-ssl-sessions')) . '">' . esc_html__('User Sessions', 'so-ssl') . '</a>'
                            );
                            ?>
                        </p>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Login Limiting Tab -->
                <div id="login-limit" class="settings-tab">
                    <h2><?php esc_html_e('Login Attempts', 'so-ssl'); ?></h2>
                    <?php
                    do_settings_sections('so-ssl-login-limit-tab');
                    ?>

                    <?php if (get_option('so_ssl_enable_login_limit', 0)): ?>
                    <div class="so-ssl-admin-tips">
                            <?php
                            printf(
                            /* translators: %s: URL to Login Security page */
                            esc_html__('For detailed settings and statistics, visit the %s page.', 'so-ssl'),
                                '<b><a href="' . esc_url(admin_url('options-general.php?page=class-so-ssl-login-limit')) . '">' . esc_html__('Login Security', 'so-ssl') . '</a></b>');?>
                    </div>
                        <?php
                            if (get_option('so_ssl_disable_weak_passwords', 0)): ?>
                            <div class="so-ssl-notice so-ssl-notice-success">
                                <p><?php esc_html_e('Login Attempt Limiting is active. This includes login attempt limiting lockouts and black listing to protect your site from brute force attacks.', 'so-ssl'); ?></p>
                            </div>
                            <?php endif;
                            endif; ?>
                </div>
                <!-- Admin Agreement Tab -->
                <div id="admin-agreement" class="settings-tab">
                    <h2><?php esc_html_e('Administrator Agreement Settings', 'so-ssl'); ?></h2>
		            <?php
		            do_settings_sections('so-ssl-admin-agreement');
		            ?>
                </div>

                <!-- Add hidden input for active tab -->
                <input type="hidden" name="so_ssl_active_tab" id="active_tab" value="<?php echo esc_attr(get_option('so_ssl_active_tab', 'ssl-settings')); ?>">
                <?php
                submit_button(); ?>
            </form>

            <!-- Feature Overview -->
            <div class="so-ssl-section-title"><?php esc_html_e('Feature Overview', 'so-ssl'); ?></div>

            <div class="so-ssl-features">
                <div class="so-ssl-feature-card">
                    <h3>
                        <span class="dashicons dashicons-lock"></span>
                        <?php esc_html_e('SSL Enforcement', 'so-ssl'); ?>
                    </h3>
                    <p><?php esc_html_e('Automatically redirect all traffic to HTTPS to ensure secure connections.', 'so-ssl'); ?></p>
                </div>

                <div class="so-ssl-feature-card">
                    <h3>
                        <span class="dashicons dashicons-shield"></span>
                        <?php esc_html_e('Security Headers', 'so-ssl'); ?>
                    </h3>
                    <p><?php esc_html_e('Implement advanced security headers like HSTS, CSP, and X-Frame-Options.', 'so-ssl'); ?></p>
                </div>

                <div class="so-ssl-feature-card">
                    <h3>
                        <span class="dashicons dashicons-smartphone"></span>
                        <?php esc_html_e('Two-Factor Authentication', 'so-ssl'); ?>
                    </h3>
                    <p><?php esc_html_e('Add an extra layer of security with email or authenticator app verification.', 'so-ssl'); ?></p>
                </div>

                <div class="so-ssl-feature-card">
                    <h3>
                        <span class="dashicons dashicons-privacy"></span>
                        <?php esc_html_e('Strong Passwords', 'so-ssl'); ?>
                    </h3>
                    <p><?php esc_html_e('Enforce secure password policies for all users on your WordPress site.', 'so-ssl'); ?></p>
                </div>

                <div class="so-ssl-feature-card">
                    <h3>
                        <span class="dashicons dashicons-groups"></span>
                        <?php esc_html_e('Session Management', 'so-ssl'); ?>
                    </h3>
                    <p><?php esc_html_e('View and control all active user sessions across multiple devices.', 'so-ssl'); ?></p>
                </div>

                <div class="so-ssl-feature-card">
                    <h3>
                        <span class="dashicons dashicons-shield-alt"></span>
                        <?php esc_html_e('Login Protection', 'so-ssl'); ?>
                    </h3>
                    <p><?php esc_html_e('Limit login attempts and protect against brute force attacks.', 'so-ssl'); ?></p>
                </div>
                <div class="so-ssl-feature-card">
                    <h3>
                        <span class="dashicons dashicons-privacy"></span>
			            <?php esc_html_e('Privacy Compliance', 'so-ssl'); ?>
                    </h3>
                    <p><?php esc_html_e('Implement GDPR and US privacy law compliance with customizable privacy acknowledgment for users.', 'so-ssl'); ?></p>
                </div>

                <div class="so-ssl-feature-card">
                    <h3>
                        <span class="dashicons dashicons-privacy"></span>
			            <?php esc_html_e('Privacy Compliance', 'so-ssl'); ?>
                    </h3>
                    <p><?php esc_html_e('Implement GDPR and US privacy law compliance with customizable privacy acknowledgment for users.', 'so-ssl'); ?></p>
                </div>

                <div class="so-ssl-feature-card">
                    <h3>
                        <span class="dashicons dashicons-admin-network"></span>
			            <?php esc_html_e('Admin Agreement', 'so-ssl'); ?>
                    </h3>
                    <p><?php esc_html_e('Require administrators to accept terms before accessing plugin features with emergency override to prevent accidental lockouts.', 'so-ssl'); ?></p>
                </div>

            </div>
            <div class="so-ssl-features">
    <!-- First Accordion -->
    <div class="so-ssl-feature-card so-ssl-accordion">
        <div class="so-ssl-accordion-header">
            <div class="so-ssl-section-title"><?php esc_html_e('Privacy Compliance', 'so-ssl'); ?></div>
            <span class="so-ssl-accordion-icon dashicons dashicons-arrow-down-alt2"></span>
        </div>

        <div class="so-ssl-accordion-content">
            <div class="so-ssl-compliance-highlights">
                <div class="so-ssl-compliance-detail">
                    <h4><span class="dashicons dashicons-welcome-view-site"></span> <?php esc_html_e('Customizable Privacy Page', 'so-ssl'); ?></h4>
                    <p><?php esc_html_e('Create a custom privacy acknowledgment page that users must accept before accessing your site.', 'so-ssl'); ?></p>
                </div>
                <div class="so-ssl-compliance-detail">
                    <h4><span class="dashicons dashicons-groups"></span> <?php esc_html_e('Role-Based Configuration', 'so-ssl'); ?></h4>
                    <p><?php esc_html_e('Choose which user roles require privacy acknowledgment, with optional exemption for administrators.', 'so-ssl'); ?></p>
                </div>
                <div class="so-ssl-compliance-detail">
                    <h4><span class="dashicons dashicons-calendar-alt"></span> <?php esc_html_e('Expiration Controls', 'so-ssl'); ?></h4>
                    <p><?php esc_html_e('Set acknowledgment expiry periods to ensure users regularly review updated privacy information.', 'so-ssl'); ?></p>
                </div>
                <a href="<?php echo esc_url(admin_url('options-general.php?page=so-ssl#privacy-compliance')); ?>" class="button button-primary" target="_blank">
                    <?php esc_html_e('Configure Privacy Compliance', 'so-ssl'); ?>
                </a>
            </div>
        </div>
    </div>

    <!-- Second Accordion with Dummy Content -->
    <div class="so-ssl-feature-card so-ssl-accordion">
        <div class="so-ssl-accordion-header">
            <div class="so-ssl-section-title"><?php esc_html_e('SSL Security', 'so-ssl'); ?></div>
            <span class="so-ssl-accordion-icon dashicons dashicons-arrow-down-alt2"></span>
        </div>

        <div class="so-ssl-accordion-content">
            <div class="so-ssl-compliance-highlights">
                <div class="so-ssl-compliance-detail">
                    <h4><span class="dashicons dashicons-shield"></span> <?php esc_html_e('Automatic Certificate Management', 'so-ssl'); ?></h4>
                    <p><?php esc_html_e('Seamlessly install and renew SSL certificates with automated tools and verification.', 'so-ssl'); ?></p>
                </div>
                <div class="so-ssl-compliance-detail">
                    <h4><span class="dashicons dashicons-dashboard"></span> <?php esc_html_e('Security Monitoring', 'so-ssl'); ?></h4>
                    <p><?php esc_html_e('Real-time monitoring of SSL certificate status with automated alerts for expiration.', 'so-ssl'); ?></p>
                </div>
                <div class="so-ssl-compliance-detail">
                    <h4><span class="dashicons dashicons-admin-site"></span> <?php esc_html_e('Mixed Content Detection', 'so-ssl'); ?></h4>
                    <p><?php esc_html_e('Automatically identify and fix mixed content warnings that affect your site security.', 'so-ssl'); ?></p>
                </div>
                <a href="<?php echo esc_url(admin_url('options-general.php?page=so-ssl#ssl-security')); ?>" class="button button-primary" target="_blank">
                    <?php esc_html_e('Manage SSL Settings', 'so-ssl'); ?>
                </a>
            </div>
        </div>
    </div>

    <!-- Third Accordion with Dummy Content -->
    <div class="so-ssl-feature-card so-ssl-accordion">
        <div class="so-ssl-accordion-header">
            <div class="so-ssl-section-title"><?php esc_html_e('Admin Agreement', 'so-ssl'); ?></div>
            <span class="so-ssl-accordion-icon dashicons dashicons-arrow-down-alt2"></span>
        </div>

        <div class="so-ssl-accordion-content">
            <div class="so-ssl-compliance-highlights">
                <div class="so-ssl-compliance-detail">
                    <h4><span class="dashicons dashicons-admin-users"></span> <?php esc_html_e('Administrator Terms', 'so-ssl'); ?></h4>
                    <p><?php esc_html_e('Require administrators to accept terms before accessing plugin features with customizable text.', 'so-ssl'); ?></p>
                </div>
                <div class="so-ssl-compliance-detail">
                    <h4><span class="dashicons dashicons-unlock"></span> <?php esc_html_e('Role Exemptions', 'so-ssl'); ?></h4>
                    <p><?php esc_html_e('Implement role-based requirements with specific exemption options for certain administrators.', 'so-ssl'); ?></p>
                </div>
                <div class="so-ssl-compliance-detail">
                    <h4><span class="dashicons dashicons-backup"></span> <?php esc_html_e('Emergency Override', 'so-ssl'); ?></h4>
                    <p><?php esc_html_e('Emergency override options to prevent accidental lockouts with periodic re-acknowledgment.', 'so-ssl'); ?></p>
                </div>
                <a href="<?php echo esc_url(admin_url('options-general.php?page=so-ssl#admin-agreement')); ?>" class="button button-primary" target="_blank">
                    <?php esc_html_e('Configure Admin Agreement', 'so-ssl'); ?>
                </a>
            </div>
        </div>
    </div>
</div>

<style>

/* Grid layout for features */
.so-ssl-features {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 25px;
    margin: 30px 0;
}

/* Feature card styling (which contains the accordion) */
.so-ssl-feature-card {
    border: 1px solid #ddd;
    border-radius: 8px;
    overflow: hidden;
    background-color: #fff;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    transition: transform 0.2s, box-shadow 0.2s;
}

.so-ssl-feature-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

/* Accordion styling */
.so-ssl-accordion-header {
    padding: 15px;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid transparent;
    background-color: #f8f9fa;
    transition: background-color 0.2s;
}

.so-ssl-accordion-header:hover {
    background-color: #f1f3f5;
}

.so-ssl-accordion-header.active {
    border-bottom: 1px solid #ddd;
    background-color: #e9ecef;
}

.so-ssl-section-title {
    font-size: 16px;
    font-weight: 600;
    color: #23282d;
}

.so-ssl-accordion-icon {
    color: #0073aa;
    transition: transform 0.2s;
}

.so-ssl-accordion-header.active .so-ssl-accordion-icon {
    transform: rotate(180deg);
}

.so-ssl-accordion-content {
    padding: 0;
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.3s ease-out, padding 0.3s;
}

.so-ssl-accordion-content.active {
    padding: 15px;
    max-height: 1000px; /* Arbitrary large value */
}

/* Compliance highlights styling */
.so-ssl-compliance-highlights {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.so-ssl-compliance-detail {
    background-color: #f9f9f9;
    padding: 12px;
    border-radius: 4px;
    border-left: 3px solid #0073aa;
}

.so-ssl-compliance-detail h4 {
    display: flex;
    align-items: center;
    margin: 0 0 8px 0;
    font-size: 14px;
    color: #23282d;
}

.so-ssl-compliance-detail h4 .dashicons {
    margin-right: 10px;
    color: #0073aa;
}

.so-ssl-compliance-detail p {
    margin: 0 0 0 28px;
    color: #555;
    font-size: 13px;
    line-height: 1.5;
}

.so-ssl-accordion-content .button {
    display: block;
    margin-top: 15px;
    width: 100%;
    text-align: center;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .so-ssl-features {
        grid-template-columns: 1fr;
    }
}

</style>

        <!-- Inline script to ensure tabs work correctly -->
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Get active tab - check URL hash first, then saved value, then default
                let activeTab = '<?php echo esc_js(get_option('so_ssl_active_tab', 'ssl-settings')); ?>';
                const urlHash = window.location.hash.substring(1);

                // Give precedence to URL hash if it exists and corresponds to a valid tab
                if (urlHash && $('#' + urlHash).length) {
                    activeTab = urlHash;
                }

                console.log('Initial activeTab:', activeTab); // Debugging

                // Initially hide all tabs
                $('.settings-tab').hide();
                $('.nav-tab').removeClass('nav-tab-active');

                // Activate the appropriate tab
                if ($('#' + activeTab).length) {
                    $('#' + activeTab).show();
                    $('.nav-tab[href="#' + activeTab + '"]').addClass('nav-tab-active');
                    $('#active_tab').val(activeTab);

                    // Also update localStorage for redundancy
                    localStorage.setItem('so_ssl_active_tab', activeTab);
                } else {
                    // Fallback to first tab if the saved tab doesn't exist
                    $('#ssl-settings').show();
                    $('.nav-tab[href="#ssl-settings"]').addClass('nav-tab-active');
                    $('#active_tab').val('ssl-settings');
                    localStorage.setItem('so_ssl_active_tab', 'ssl-settings');
                    activeTab = 'ssl-settings';
                }

                // Handle tab navigation
                $('.nav-tab').on('click', function(e) {
                    e.preventDefault();

                    // Get target tab
                    const tabId = $(this).attr('href').substring(1);
                    console.log('Clicked tab:', tabId); // Debugging

                    // If already on this tab, do nothing
                    if ($(this).hasClass('nav-tab-active')) {
                        return;
                    }

                    // Check for unsaved changes
                    if (typeof formModified !== 'undefined' && formModified) {
                        if (!confirm('Your changes are not saved. Do you want to continue?')) {
                            return false;
                        }
                    }

                    // Update tabs
                    $('.nav-tab').removeClass('nav-tab-active');
                    $('.settings-tab').hide();

                    $(this).addClass('nav-tab-active');
                    $('#' + tabId).show();

                    // Update hidden input value - CRITICAL for form submission
                    $('#active_tab').val(tabId);

                    // Update localStorage for redundancy
                    localStorage.setItem('so_ssl_active_tab', tabId);

                    // Update URL hash
                    if (history.pushState) {
                        history.pushState(null, null, '#' + tabId);
                    } else {
                        window.location.hash = tabId;
                    }
                });

                // Save active tab on form submission
                $('form').on('submit', function(e) {
                    const currentTab = $('#active_tab').val();
                    console.log('Submitting form with active tab:', currentTab); // Debugging

                    // Make sure the hidden input is properly named for WP options
                    if ($('#active_tab').attr('name') !== 'so_ssl_active_tab') {
                        // Update the name to match the registered option
                        $('#active_tab').attr('name', 'so_ssl_active_tab');
                    }

                    // For redundancy, also add another hidden field
                    if (!$(this).find('input[name="so_ssl_active_tab"]').length) {
                        $(this).append('<input type="hidden" name="so_ssl_active_tab" value="' + currentTab + '">');
                    }
                });

        jQuery(document).ready(function($) {
    $('.so-ssl-accordion-header').on('click', function() {
        // Toggle the active class on the header
        $(this).toggleClass('active');

        // Toggle the content panel
        var content = $(this).next('.so-ssl-accordion-content');
        content.toggleClass('active');

        // Update the icon
        if ($(this).hasClass('active')) {
            $(this).find('.so-ssl-accordion-icon').removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-up-alt2');
        } else {
            $(this).find('.so-ssl-accordion-icon').removeClass('dashicons-arrow-up-alt2').addClass('dashicons-arrow-down-alt2');
        }
    });
});
            });
        </script>
        <?php
    }

    /**
     * Register all settings.
     *
     * @since    1.0.2
     */
    public function register_settings() {
        // SSL Settings
        $this->register_ssl_settings();

        // HSTS Settings
        $this->register_hsts_settings();

        // X-Frame-Options Settings
        $this->register_xframe_settings();

        // CSP Frame-Ancestors Settings
        $this->register_csp_settings();

        // Two-Factor Authentication Settings
        $this->register_two_factor_settings();

        // Referrer Policy Settings
        $this->register_referrer_policy_settings();

        // Content Security Policy Settings
        $this->register_content_security_policy_settings();

        // Permissions Policy Settings
        $this->register_permissions_policy_settings();

        // Cross-Origin Policy Settings
        $this->register_cross_origin_policy_settings();

        // Login Protection Settings
        $this->register_login_protection_settings();

	    // Privacy compliance settings
	    $this->register_privacy_compliance_settings();

	    // Admin Agrrement settings
	    $this->register_admin_agreement_settings();


	    // Register a setting for active tab
	    register_setting(
		    'so_ssl_options',
		    'so_ssl_active_tab',
		    array(
			    'type' => 'string',
			    'sanitize_callback' => 'sanitize_text_field',
			    'default' => 'ssl-settings',
		    )
	    );

        // User Sessions Management Settings
    register_setting(
        'so_ssl_options',
        'so_ssl_enable_user_sessions',
        array(
            'type' => 'boolean',
            'sanitize_callback' => 'intval',
            'default' => 0,
        )
    );

    // Login Limiting Settings
    register_setting(
        'so_ssl_options',
        'so_ssl_enable_login_limit',
        array(
            'type' => 'boolean',
            'sanitize_callback' => 'intval',
            'default' => 0,
        )
    );

    // Register User Sessions Management tab
    add_settings_section(
        'so_ssl_user_sessions_section',
        __('User Sessions Management', 'so-ssl'),
        array($this, 'user_sessions_section_callback'),
        'so-ssl-user-sessions'
    );

    add_settings_field(
        'so_ssl_enable_user_sessions',
        __('Enable User Sessions Management', 'so-ssl'),
        array($this, 'enable_user_sessions_callback'),
        'so-ssl-user-sessions',
        'so_ssl_user_sessions_section'
    );

    // Register Login Limiting tab
    add_settings_section(
        'so_ssl_login_limit_section_tab',
        __('Login Attempt Limiting', 'so-ssl'),
        array($this, 'login_limit_section_callback'),
        'so-ssl-login-limit-tab'
    );

    add_settings_field(
        'so_ssl_enable_login_limit',
        __('Enable Login Limiting', 'so-ssl'),
        array($this, 'enable_login_limit_callback'),
        'so-ssl-login-limit-tab',
        'so_ssl_login_limit_section_tab'
    );
    }

    /**
     * Register SSL settings.
     *
     * @since    1.0.2
     * @access   private
     */
    private function register_ssl_settings() {
        // Force SSL setting
        register_setting(
            'so_ssl_options',
            'so_ssl_force_ssl',
            array(
                'type' => 'boolean',
                'sanitize_callback' => 'intval',
                'default' => 0,
            )
        );

        // SSL Settings Section
        add_settings_section(
            'so_ssl_section',
            __('SSL Settings', 'so-ssl'),
            array($this, 'ssl_section_callback'),
            'so-ssl-ssl'
        );

        add_settings_field(
            'so_ssl_force_ssl',
            __('Force SSL', 'so-ssl'),
            array($this, 'force_ssl_callback'),
            'so-ssl-ssl',
            'so_ssl_section'
        );
    }

    /**
     * Register HSTS settings.
     *
     * @since    1.0.2
     * @access   private
     */
    private function register_hsts_settings() {
        // HSTS settings
        register_setting(
            'so_ssl_options',
            'so_ssl_enable_hsts',
            array(
                'type' => 'boolean',
                'sanitize_callback' => 'intval',
                'default' => 0,
            )
        );

        register_setting(
            'so_ssl_options',
            'so_ssl_hsts_max_age',
            array(
                'type' => 'integer',
                'sanitize_callback' => 'intval',
                'default' => 31536000,
            )
        );

        register_setting(
            'so_ssl_options',
            'so_ssl_hsts_subdomains',
            array(
                'type' => 'boolean',
                'sanitize_callback' => 'intval',
                'default' => 0,
            )
          );

        register_setting(
            'so_ssl_options',
            'so_ssl_hsts_preload',
            array(
                'type' => 'boolean',
                'sanitize_callback' => 'intval',
                'default' => 0,
            )
        );

        // HSTS Settings Section
        add_settings_section(
            'so_ssl_hsts_section',
            __('HTTP Strict Transport Security (HSTS)', 'so-ssl'),
            array($this, 'hsts_section_callback'),
            'so-ssl-ssl'
        );

        add_settings_field(
            'so_ssl_enable_hsts',
            __('Enable HSTS', 'so-ssl'),
            array($this, 'enable_hsts_callback'),
            'so-ssl-ssl',
            'so_ssl_hsts_section'
        );

        add_settings_field(
            'so_ssl_hsts_max_age',
            __('Max Age', 'so-ssl'),
            array($this, 'hsts_max_age_callback'),
            'so-ssl-ssl',
            'so_ssl_hsts_section'
        );

        add_settings_field(
            'so_ssl_hsts_subdomains',
            __('Include Subdomains', 'so-ssl'),
            array($this, 'hsts_subdomains_callback'),
            'so-ssl-ssl',
            'so_ssl_hsts_section'
        );

        add_settings_field(
            'so_ssl_hsts_preload',
            __('Preload', 'so-ssl'),
            array($this, 'hsts_preload_callback'),
            'so-ssl-ssl',
            'so_ssl_hsts_section'
        );
    }

    /**
     * Register X-Frame-Options settings.
     *
     * @since    1.0.2
     * @access   private
     */
    private function register_xframe_settings() {
        // X-Frame-Options settings
        register_setting(
            'so_ssl_options',
            'so_ssl_enable_xframe',
            array(
                'type' => 'boolean',
                'sanitize_callback' => 'intval',
                'default' => 1,
            )
        );

        register_setting(
            'so_ssl_options',
            'so_ssl_xframe_option',
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => 'sameorigin',
            )
        );

        register_setting(
            'so_ssl_options',
            'so_ssl_xframe_allow_from',
            array(
                'type' => 'string',
                'sanitize_callback' => 'esc_url_raw',
                'default' => '',
            )
        );

        // X-Frame-Options Settings Section
        add_settings_section(
            'so_ssl_xframe_section',
            __('X-Frame-Options (Iframe Protection)', 'so-ssl'),
            array($this, 'xframe_section_callback'),
            'so-ssl-xframe'
        );

        add_settings_field(
            'so_ssl_enable_xframe',
            __('Enable X-Frame-Options', 'so-ssl'),
            array($this, 'enable_xframe_callback'),
            'so-ssl-xframe',
            'so_ssl_xframe_section'
        );

        add_settings_field(
            'so_ssl_xframe_option',
            __('X-Frame-Options Value', 'so-ssl'),
            array($this, 'xframe_option_callback'),
            'so-ssl-xframe',
            'so_ssl_xframe_section'
        );

        add_settings_field(
            'so_ssl_xframe_allow_from',
            __('Allow From Domain', 'so-ssl'),
            array($this, 'xframe_allow_from_callback'),
            'so-ssl-xframe',
            'so_ssl_xframe_section',
            array('class' => 'so_ssl_allow_from_field')
        );
    }

    /**
     * Register CSP Frame-Ancestors settings.
     *
     * @since    1.0.2
     * @access   private
     */
    private function register_csp_settings() {
        // CSP Frame-Ancestors settings
        register_setting(
            'so_ssl_options',
            'so_ssl_enable_csp_frame_ancestors',
            array(
                'type' => 'boolean',
                'sanitize_callback' => 'intval',
                'default' => 0,
            )
        );

        register_setting(
            'so_ssl_options',
            'so_ssl_csp_frame_ancestors_option',
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => 'none',
            )
        );

        register_setting(
            'so_ssl_options',
            'so_ssl_csp_include_self',
            array(
                'type' => 'boolean',
                'sanitize_callback' => 'intval',
                'default' => 0,
            )
        );

        register_setting(
            'so_ssl_options',
            'so_ssl_csp_frame_ancestors_domains',
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_textarea_field',
                'default' => '',
            )
        );

        // CSP Frame-Ancestors Settings Section
        add_settings_section(
            'so_ssl_csp_section',
            __('Content Security Policy (CSP): Frame-Ancestors', 'so-ssl'),
            array($this, 'csp_section_callback'),
            'so-ssl-csp-frame'
        );

        add_settings_field(
            'so_ssl_enable_csp_frame_ancestors',
            __('Enable CSP Frame-Ancestors', 'so-ssl'),
            array($this, 'enable_csp_frame_ancestors_callback'),
            'so-ssl-csp-frame',
            'so_ssl_csp_section'
        );

        add_settings_field(
            'so_ssl_csp_frame_ancestors_option',
            __('Frame-Ancestors Value', 'so-ssl'),
            array($this, 'csp_frame_ancestors_option_callback'),
            'so-ssl-csp-frame',
            'so_ssl_csp_section'
        );

        add_settings_field(
            'so_ssl_csp_include_self',
            __('Include Self', 'so-ssl'),
            array($this, 'csp_include_self_callback'),
            'so-ssl-csp-frame',
            'so_ssl_csp_section',
            array('class' => 'so_ssl_csp_custom_field')
        );

        add_settings_field(
            'so_ssl_csp_frame_ancestors_domains',
            __('Allowed Domains', 'so-ssl'),
            array($this, 'csp_frame_ancestors_domains_callback'),
            'so-ssl-csp-frame',
            'so_ssl_csp_section',
            array('class' => 'so_ssl_csp_custom_field')
        );
    }

    /**
     * Register Two-Factor Authentication settings.
     *
     * @since    1.2.0
     * @access   private
     */
    private function register_two_factor_settings() {
        // Two-Factor Authentication settings
        register_setting(
            'so_ssl_options',
            'so_ssl_enable_2fa',
            array(
                'type' => 'boolean',
                'sanitize_callback' => 'intval',
                'default' => 0,
            )
        );

        register_setting(
            'so_ssl_options',
            'so_ssl_2fa_user_roles',
            array(
                'type' => 'array',
                'sanitize_callback' => function($input) {
                    if (!is_array($input)) {
                        return array();
                    }
                    return array_map('sanitize_text_field', $input);
                },
                'default' => array('administrator'),
            )
        );

        register_setting(
            'so_ssl_options',
            'so_ssl_2fa_method',
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => 'email',
            )
        );

        // Two-Factor Authentication Settings Section
        add_settings_section(
            'so_ssl_2fa_section',
            __('Two-Factor Authentication Settings', 'so-ssl'),
            array($this, 'two_factor_section_callback'),
            'so-ssl-2fa'
        );

        add_settings_field(
            'so_ssl_enable_2fa',
            __('Enable Two-Factor Authentication', 'so-ssl'),
            array($this, 'enable_two_factor_callback'),
            'so-ssl-2fa',
            'so_ssl_2fa_section'
        );

        add_settings_field(
            'so_ssl_2fa_user_roles',
            __('User Roles', 'so-ssl'),
            array($this, 'two_factor_user_roles_callback'),
            'so-ssl-2fa',
            'so_ssl_2fa_section'
        );

        add_settings_field(
            'so_ssl_2fa_method',
            __('Authentication Method', 'so-ssl'),
            array($this, 'two_factor_method_callback'),
            'so-ssl-2fa',
            'so_ssl_2fa_section'
        );
    }

/**
 * Register Referrer Policy settings.
 *
 * @since    1.0.2
 * @access   private
 */
private function register_referrer_policy_settings() {
    // Referrer Policy settings
    register_setting(
        'so_ssl_options',
        'so_ssl_enable_referrer_policy',
        array(
            'type' => 'boolean',
            'sanitize_callback' => 'intval',
            'default' => 0,
        )
    );

    register_setting(
        'so_ssl_options',
        'so_ssl_referrer_policy_option',
        array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'strict-origin-when-cross-origin',
        )
    );

    // Referrer Policy Settings Section
    add_settings_section(
        'so_ssl_referrer_policy_section',
        __('Referrer Policy', 'so-ssl'),
        array($this, 'referrer_policy_section_callback'),
        'so-ssl-referrer'
    );

    add_settings_field(
        'so_ssl_enable_referrer_policy',
        __('Enable Referrer Policy', 'so-ssl'),
        array($this, 'enable_referrer_policy_callback'),
        'so-ssl-referrer',
        'so_ssl_referrer_policy_section'
    );

    add_settings_field(
        'so_ssl_referrer_policy_option',
        __('Referrer Policy Value', 'so-ssl'),
        array($this, 'referrer_policy_option_callback'),
        'so-ssl-referrer',
        'so_ssl_referrer_policy_section'
    );
}

/**
     * Register Content Security Policy settings.
     *
     * @since    1.0.2
     * @access   private
     */
    private function register_content_security_policy_settings() {
        // Content Security Policy settings
        register_setting(
            'so_ssl_options',
            'so_ssl_enable_csp',
            array(
                'type' => 'boolean',
                'sanitize_callback' => 'intval',
                'default' => 0,
            )
        );

        register_setting(
            'so_ssl_options',
            'so_ssl_csp_mode',
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => 'report-only',
            )
        );

        // Register CSP directives
        $csp_directives = array(
            'default-src' => "'self'",
            'script-src' => "'self'",
            'style-src' => "'self'",
            'img-src' => "'self'",
            'connect-src' => "'self'",
            'font-src' => "'self'",
            'object-src' => "'none'",
            'media-src' => "'self'",
            'frame-src' => "'self'",
            'base-uri' => "'self'",
            'form-action' => "'self'",
            'upgrade-insecure-requests' => ""
        );

        foreach ($csp_directives as $directive => $default_value) {
            // Register the main directive value
            register_setting(
                'so_ssl_options',
                'so_ssl_csp_' . str_replace('-', '_', $directive),
                array(
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_textarea_field',
                    'default' => $default_value,
                )
            );

            // Register the option type (dropdown or custom)
            if ($directive !== 'upgrade-insecure-requests') {
                register_setting(
                    'so_ssl_options',
                    'so_ssl_csp_' . str_replace('-', '_', $directive) . '_type',
                    array(
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                        'default' => 'predefined',
                    )
                );
            }
        }

        // Content Security Policy Settings Section
        add_settings_section(
            'so_ssl_csp_full_section',
            __('Content Security Policy (CSP)', 'so-ssl'),
            array($this, 'csp_full_section_callback'),
            'so-ssl-csp'
        );

        add_settings_field(
            'so_ssl_enable_csp',
            __('Enable Content Security Policy', 'so-ssl'),
            array($this, 'enable_csp_callback'),
            'so-ssl-csp',
            'so_ssl_csp_full_section'
        );

        add_settings_field(
            'so_ssl_csp_mode',
            __('CSP Mode', 'so-ssl'),
            array($this, 'csp_mode_callback'),
            'so-ssl-csp',
            'so_ssl_csp_full_section'
        );

        // Add fields for each CSP directive
        foreach ($csp_directives as $directive => $default_value) {
            $field_id = 'so_ssl_csp_' . str_replace('-', '_', $directive);

            add_settings_field(
                $field_id,
                $directive,
                array($this, 'csp_directive_callback'),
                'so-ssl-csp',
                'so_ssl_csp_full_section',
                array(
                    'label_for' => $field_id,
                    'directive' => $directive,
                    'default_value' => $default_value
                )
            );
        }
    }

                /**
                   * Register Permissions Policy settings.
                   *
                   * @since    1.0.2
                   * @access   private
                   */
                  private function register_permissions_policy_settings() {
                      // Permissions Policy settings
                      register_setting(
                          'so_ssl_options',
                          'so_ssl_enable_permissions_policy',
                          array(
                              'type' => 'boolean',
                              'sanitize_callback' => 'intval',
                              'default' => 0,
                          )
                      );

                      // Define available permissions
                      $permissions = array(
                          'accelerometer' => array('default' => 'self', 'description' => __('Controls access to accelerometer sensors', 'so-ssl')),
                          'ambient-light-sensor' => array('default' => 'self', 'description' => __('Controls access to ambient light sensors', 'so-ssl')),
                          'autoplay' => array('default' => 'self', 'description' => __('Controls the ability to autoplay media', 'so-ssl')),
                          'battery' => array('default' => 'self', 'description' => __('Controls access to battery information', 'so-ssl')),
                          'camera' => array('default' => 'self', 'description' => __('Controls access to video cameras', 'so-ssl')),
                          'display-capture' => array('default' => 'self', 'description' => __('Controls the ability to capture screen content', 'so-ssl')),
                          'document-domain' => array('default' => 'self', 'description' => __('Controls the ability to set document.domain', 'so-ssl')),
                          'encrypted-media' => array('default' => 'self', 'description' => __('Controls access to EME API', 'so-ssl')),
                          'execution-while-not-rendered' => array('default' => 'self', 'description' => __('Controls execution when not rendered', 'so-ssl')),
                          'execution-while-out-of-viewport' => array('default' => 'self', 'description' => __('Controls execution when outside viewport', 'so-ssl')),
                          'fullscreen' => array('default' => 'self', 'description' => __('Controls the ability to use fullscreen mode', 'so-ssl')),
                          'geolocation' => array('default' => 'self', 'description' => __('Controls access to the geolocation API', 'so-ssl')),
                          'gyroscope' => array('default' => 'self', 'description' => __('Controls access to gyroscope sensors', 'so-ssl')),
                          'microphone' => array('default' => 'self', 'description' => __('Controls access to audio capture devices', 'so-ssl')),
                          'midi' => array('default' => 'self', 'description' => __('Controls access to MIDI devices', 'so-ssl')),
                          'navigation-override' => array('default' => 'self', 'description' => __('Controls the ability to override navigation', 'so-ssl')),
                          'payment' => array('default' => 'self', 'description' => __('Controls access to the Payment Request API', 'so-ssl')),
                          'picture-in-picture' => array('default' => '*', 'description' => __('Controls the ability to use Picture-in-Picture', 'so-ssl')),
                          'publickey-credentials-get' => array('default' => 'self', 'description' => __('Controls access to WebAuthn API', 'so-ssl')),
                          'screen-wake-lock' => array('default' => 'self', 'description' => __('Controls access to Wake Lock API', 'so-ssl')),
                          'sync-xhr' => array('default' => 'self', 'description' => __('Controls the ability to use synchronous XHR', 'so-ssl')),
                          'usb' => array('default' => 'self', 'description' => __('Controls access to USB devices', 'so-ssl')),
                          'web-share' => array('default' => 'self', 'description' => __('Controls access to the Web Share API', 'so-ssl')),
                          'xr-spatial-tracking' => array('default' => 'self', 'description' => __('Controls access to WebXR features', 'so-ssl'))
                      );

                      // Register setting for each permission
                      foreach ($permissions as $permission => $info) {
                          $option_name = 'so_ssl_permissions_policy_' . str_replace('-', '_', $permission);

                          // Register the value setting
                          register_setting(
                              'so_ssl_options',
                              $option_name,
                              array(
                                  'type' => 'string',
                                  'sanitize_callback' => 'sanitize_text_field',
                                  'default' => $info['default'],
                              )
                          );

                          // Register the type setting (dropdown or custom)
                          register_setting(
                              'so_ssl_options',
                              $option_name . '_type',
                              array(
                                  'type' => 'string',
                                  'sanitize_callback' => 'sanitize_text_field',
                                  'default' => 'predefined',
                              )
                          );
                      }

                      // Permissions Policy Settings Section
                      add_settings_section(
                          'so_ssl_permissions_policy_section',
                          __('Permissions Policy', 'so-ssl'),
                          array($this, 'permissions_policy_section_callback'),
                          'so-ssl-permissions'
                      );

                      add_settings_field(
                          'so_ssl_enable_permissions_policy',
                          __('Enable Permissions Policy', 'so-ssl'),
                          array($this, 'enable_permissions_policy_callback'),
                          'so-ssl-permissions',
                          'so_ssl_permissions_policy_section'
                      );

                      // Add settings fields for each permission
                      foreach ($permissions as $permission => $info) {
                          $option_name = 'so_ssl_permissions_policy_' . str_replace('-', '_', $permission);

                          add_settings_field(
                              $option_name,
                              $permission,
                              array($this, 'permissions_policy_option_callback'),
                              'so-ssl-permissions',
                              'so_ssl_permissions_policy_section',
                              array(
                                  'label_for' => $option_name,
                                  'permission' => $permission,
                                  'description' => $info['description'],
                                  'default' => $info['default']
                              )
                          );
                      }
                  }

    /**
     * Register Cross-Origin Policy settings.
     *
     * @since    1.0.2
     * @access   private
     */
    private function register_cross_origin_policy_settings() {
        // Cross-Origin Policy settings
        $cross_origin_headers = array(
            'cross-origin-embedder-policy' => array(
                'default' => 'require-corp',
                'options' => array(
                    'require-corp' => __('require-corp - Embedded resources must opt in', 'so-ssl'),
                    'unsafe-none' => __('unsafe-none - Allows loading any resource (default browser behavior)', 'so-ssl')
                ),
                'description' => __('Controls whether resources can be embedded cross-origin', 'so-ssl')
            ),
            'cross-origin-opener-policy' => array(
                'default' => 'same-origin',
                'options' => array(
                    'unsafe-none' => __('unsafe-none - Allows sharing browsing context (default browser behavior)', 'so-ssl'),
                    'same-origin' => __('same-origin - Isolates browsing context to same origin', 'so-ssl'),
                    'same-origin-allow-popups' => __('same-origin-allow-popups - Isolates browsing context but allows popups', 'so-ssl')
                ),
                'description' => __('Controls how windows/tabs opened from your site can interact with it', 'so-ssl')
            ),
            'cross-origin-resource-policy' => array(
                'default' => 'same-origin',
                'options' => array(
                    'same-site' => __('same-site - Resources can only be loaded on the same site', 'so-ssl'),
                    'same-origin' => __('same-origin - Resources can only be loaded from the same origin', 'so-ssl'),
                    'cross-origin' => __('cross-origin - Resources can be loaded from any origin (less secure)', 'so-ssl')
                ),
                'description' => __('Controls how resources can be loaded cross-origin', 'so-ssl')
            )
        );

        foreach ($cross_origin_headers as $header => $info) {
            $option_name_enabled = 'so_ssl_enable_' . str_replace('-', '_', $header);
            $option_name_value = 'so_ssl_' . str_replace('-', '_', $header) . '_value';

            // Register enable setting
            register_setting(
                'so_ssl_options',
                $option_name_enabled,
                array(
                    'type' => 'boolean',
                    'sanitize_callback' => 'intval',
                    'default' => 0,
                )
            );

            // Register value setting
            register_setting(
                'so_ssl_options',
                $option_name_value,
                array(
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'default' => $info['default'],
                )
            );
        }

        // Cross-Origin Policy Settings Section
        add_settings_section(
            'so_ssl_cross_origin_policy_section',
            __('Cross-Origin Policies', 'so-ssl'),
            array($this, 'cross_origin_policy_section_callback'),
            'so-ssl-cross-origin'
        );

        // Add settings fields for each cross-origin header
        foreach ($cross_origin_headers as $header => $info) {
            $option_name_enabled = 'so_ssl_enable_' . str_replace('-', '_', $header);
            $option_name_value = 'so_ssl_' . str_replace('-', '_', $header) . '_value';

            add_settings_field(
                $option_name_enabled,
                $header,
                array($this, 'cross_origin_policy_callback'),
                'so-ssl-cross-origin',
                'so_ssl_cross_origin_policy_section',
                array(
                    'label_for' => $option_name_enabled,
                    'value_id' => $option_name_value,
                    'header' => $header,
                    'options' => $info['options'],
                    'description' => $info['description']
                )
            );
        }
    }

    /**
     * Register Login Protection settings.
     *
     * @since    1.3.0
     * @access   private
     */
    private function register_login_protection_settings() {
        // Password security setting
        register_setting(
            'so_ssl_options',
            'so_ssl_disable_weak_passwords',
            array(
                'type' => 'boolean',
                'sanitize_callback' => 'intval',
                'default' => 0,
            )
        );

        // Password Security Settings Section
        add_settings_section(
            'so_ssl_password_security_section',
            __('Password Security', 'so-ssl'),
            array($this, 'password_security_section_callback'),
            'so-ssl-login-protection'
        );

        add_settings_field(
            'so_ssl_disable_weak_passwords',
            __('Enforce Strong Passwords', 'so-ssl'),
            array($this, 'disable_weak_passwords_callback'),
            'so-ssl-login-protection',
            'so_ssl_password_security_section'
        );
    }

	/**
	 * Register Privacy Compliance Settings.
	 *
	 * @since    1.4.4
	 * @access   private
	 */
	private function register_privacy_compliance_settings() {
		// Create the settings section
		add_settings_section(
			'so_ssl_privacy_compliance_section',
			__('Privacy Compliance', 'so-ssl'),
			array($this, 'privacy_compliance_section_callback'),
			'so-ssl-privacy'
		);

		// Register all privacy settings
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

		// Add settings fields
		add_settings_field(
			'so_ssl_enable_privacy_compliance',
			__('Enable Privacy Compliance', 'so-ssl'),
			array($this, 'enable_privacy_compliance_callback'),
			'so-ssl-privacy',
			'so_ssl_privacy_compliance_section'
		);

		add_settings_field(
			'so_ssl_privacy_page_title',
			__('Privacy Page Title', 'so-ssl'),
			array($this, 'privacy_page_title_callback'),
			'so-ssl-privacy',
			'so_ssl_privacy_compliance_section'
		);

		add_settings_field(
			'so_ssl_privacy_page_slug',
			__('Privacy Page Slug', 'so-ssl'),
			array($this, 'privacy_page_slug_callback'),
			'so-ssl-privacy',
			'so_ssl_privacy_compliance_section'
		);

		add_settings_field(
			'so_ssl_privacy_notice_text',
			__('Privacy Notice Text', 'so-ssl'),
			array($this, 'privacy_notice_text_callback'),
			'so-ssl-privacy',
			'so_ssl_privacy_compliance_section'
		);

		add_settings_field(
			'so_ssl_privacy_checkbox_text',
			__('Acknowledgment Checkbox Text', 'so-ssl'),
			array($this, 'privacy_checkbox_text_callback'),
			'so-ssl-privacy',
			'so_ssl_privacy_compliance_section'
		);

		add_settings_field(
			'so_ssl_privacy_expiry_days',
			__('Acknowledgment Expiry (Days)', 'so-ssl'),
			array($this, 'privacy_expiry_days_callback'),
			'so-ssl-privacy',
			'so_ssl_privacy_compliance_section'
		);

		add_settings_field(
			'so_ssl_privacy_required_roles',
			__('Required for User Roles', 'so-ssl'),
			array($this, 'privacy_required_roles_callback'),
			'so-ssl-privacy',
			'so_ssl_privacy_compliance_section'
		);
	}

	/**
	 * Register settings for admin agreement
	 */
	private function register_admin_agreement_settings() {
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
        __('Administrator Agreement Form', 'so-ssl'),
        array($this, 'admin_agreement_section_callback'),
        'so-ssl-admin-agreement'
    );

    add_settings_field(
        'so_ssl_enable_admin_agreement',
        __('Enable Admin Agreement', 'so-ssl'),
        array($this, 'enable_admin_agreement_callback'),
        'so-ssl-admin-agreement',
        'so_ssl_admin_agreement_section'
    );

    add_settings_field(
        'so_ssl_admin_agreement_title',
        __('Agreement Page Title', 'so-ssl'),
        array($this, 'admin_agreement_title_callback'),
        'so-ssl-admin-agreement',
        'so_ssl_admin_agreement_section'
    );

    add_settings_field(
        'so_ssl_admin_agreement_text',
        __('Agreement Text', 'so-ssl'),
        array($this, 'admin_agreement_text_callback'),
        'so-ssl-admin-agreement',
        'so_ssl_admin_agreement_section'
    );

    add_settings_field(
        'so_ssl_admin_agreement_checkbox_text',
        __('Agreement Checkbox Text', 'so-ssl'),
        array($this, 'admin_agreement_checkbox_text_callback'),
        'so-ssl-admin-agreement',
        'so_ssl_admin_agreement_section'
    );

    add_settings_field(
        'so_ssl_admin_agreement_expiry_days',
        __('Agreement Expiry (Days)', 'so-ssl'),
        array($this, 'admin_agreement_expiry_days_callback'),
        'so-ssl-admin-agreement',
        'so_ssl_admin_agreement_section'
    );

    add_settings_field(
        'so_ssl_admin_agreement_required_roles',
        __('Required for User Roles', 'so-ssl'),
        array('So_SSL_Admin_Agreement', 'admin_agreement_required_roles_callback'),
        'so-ssl-admin-agreement',
        'so_ssl_admin_agreement_section'
    );
}

    /**
     * SSL section description.
     *
     * @since    1.0.2
     */
    public function ssl_section_callback() {
        echo '<p>' . esc_html__('Configure SSL settings for your website.', 'so-ssl') . '</p>';

        // Display current SSL status
        if (is_ssl()) {
            echo '<div class="notice notice-success inline"><p>' . esc_html__('Your site is currently using SSL/HTTPS.', 'so-ssl') . '</p></div>';
        } else {
            echo '<div class="notice notice-warning inline"><p>' . esc_html__('Your site is not using SSL/HTTPS. Enabling force SSL without having a valid SSL certificate may make your site inaccessible.', 'so-ssl') . '</p></div>';
        }
    }

	/**
	 * Two-Factor Authentication section description.
	 *
	 * @since    1.2.0
	 */
	public function two_factor_section_callback() {
		echo '<p>' . esc_html__('Configure Two-Factor Authentication (2FA) settings for your WordPress users.', 'so-ssl') . '</p>';
		echo '<p>' . esc_html__('Two-Factor Authentication adds an extra layer of security by requiring a second verification method in addition to the password.', 'so-ssl') . '</p>';

		// Quick setup checklist
		echo '<div style="background: #f8f9fa; padding: 15px; border-radius: 4px; margin-top: 15px;">';
		echo '<h4 style="margin-top: 0;">' . esc_html__('Quick Setup Checklist:', 'so-ssl') . '</h4>';
		echo '<ol style="margin-bottom: 0;">';
		echo '<li>' . esc_html__('Enable 2FA below', 'so-ssl') . '</li>';
		echo '<li>' . esc_html__('Choose authentication method (email or app)', 'so-ssl') . '</li>';
		echo '<li>' . esc_html__('Select which user roles require 2FA', 'so-ssl') . '</li>';
		echo '<li>' . esc_html__('Save settings', 'so-ssl') . '</li>';
		echo '<li>' . esc_html__('Set up 2FA in your own profile first', 'so-ssl') . '</li>';
		echo '<li>' . esc_html__('Test the login process', 'so-ssl') . '</li>';
		echo '<li>' . esc_html__('Communicate with users about the new requirement', 'so-ssl') . '</li>';
		echo '</ol>';
		echo '</div>';
	}
/**
 * Password Security section description.
 *
 * @since    1.3.0
 */
public function password_security_section_callback() {
    echo '<p>' . esc_html__('Configure password security settings to enforce stronger passwords and improve login security.', 'so-ssl') . '</p>';
}

/**
 * Enable Two-Factor Authentication field callback.
 *
 * @since    1.2.0
 */
public function enable_two_factor_callback() {
    $enable_2fa = get_option('so_ssl_enable_2fa', 0);

    echo '<label for="so_ssl_enable_2fa">';
    echo '<input type="checkbox" id="so_ssl_enable_2fa" name="so_ssl_enable_2fa" value="1" ' . checked(1, $enable_2fa, false) . '/>';
    echo esc_html__('Enable Two-Factor Authentication for users', 'so-ssl');
    echo '</label>';
    echo '<p class="description">' . esc_html__('Adds an additional security layer to the WordPress login process.', 'so-ssl') . '</p>';
}

/**
 * Disable Weak Passwords field callback.
 *
 * @since    1.3.0
 */
public function disable_weak_passwords_callback() {
    $disable_weak_passwords = get_option('so_ssl_disable_weak_passwords', 0);

    echo '<label for="so_ssl_disable_weak_passwords">';
    echo '<input type="checkbox" id="so_ssl_disable_weak_passwords" name="so_ssl_disable_weak_passwords" value="1" ' . checked(1, $disable_weak_passwords, false) . '/>';
    echo esc_html__('Enforce strong passwords for all users', 'so-ssl');
    echo '</label>';
    echo '<p class="description">' . esc_html__('Only allow strong passwords that meet ALL requirements: 8+ characters, uppercase letters, lowercase letters, numbers, and special characters. Weak and medium strength passwords will be rejected.', 'so-ssl') . '</p>';
    echo '<div style="background: #f0f6fc; border-left: 4px solid #2271b1; padding: 10px; margin-top: 10px; border-radius: 0 4px 4px 0;">';
    echo '<strong>' . esc_html__('Password Requirements:', 'so-ssl') . '</strong><br/>';
    echo '• ' . esc_html__('Minimum 8 characters long', 'so-ssl') . '<br/>';
    echo '• ' . esc_html__('Include uppercase letters (A-Z)', 'so-ssl') . '<br/>';
    echo '• ' . esc_html__('Include lowercase letters (a-z)', 'so-ssl') . '<br/>';
    echo '• ' . esc_html__('Include numbers (0-9)', 'so-ssl') . '<br/>';
    echo '• ' . esc_html__('Include special characters (!@#$%^&*...)', 'so-ssl') . '<br/>';
    echo '• ' . esc_html__('Cannot contain username', 'so-ssl') . '<br/>';
    echo '• ' . esc_html__('Cannot use common weak patterns', 'so-ssl');
    echo '</div>';
}

	/**
	 * Two-Factor Authentication user roles field callback.
	 *
	 * @since    1.2.0
	 */
	public function two_factor_user_roles_callback() {
		$selected_roles = get_option('so_ssl_2fa_user_roles', array('administrator'));

		if (!is_array($selected_roles)) {
			$selected_roles = array('administrator');
		}

		$roles = wp_roles()->get_names();

		echo '<select multiple id="so_ssl_2fa_user_roles" name="so_ssl_2fa_user_roles[]" class="regular-text" style="height: 120px;">';
		foreach ($roles as $role_value => $role_name) {
			$selected = in_array($role_value, $selected_roles) ? 'selected="selected"' : '';
			echo '<option value="' . esc_attr($role_value) . '" ' . esc_html( $selected ) . '>' . esc_html($role_name) . '</option>';
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__('Select which user roles will be required to use Two-Factor Authentication. Hold Ctrl/Cmd to select multiple roles.', 'so-ssl') . '</p>';
	}

	/**
	 * Two-Factor Authentication method field callback.
	 *
	 * @since    1.2.0
	 */
	public function two_factor_method_callback() {
		$method = get_option('so_ssl_2fa_method', 'email');

		echo '<select id="so_ssl_2fa_method" name="so_ssl_2fa_method">';
		echo '<option value="email" ' . selected('email', $method, false) . '>' . esc_html__('Email - Send verification code via email', 'so-ssl') . '</option>';
		echo '<option value="authenticator" ' . selected('authenticator', $method, false) . '>' . esc_html__('Authenticator App - Use Google Authenticator or similar apps', 'so-ssl') . '</option>';
		echo '</select>';
		echo '<p class="description">' . esc_html__('Select the Two-Factor Authentication method to use.', 'so-ssl') . '</p>';

		// Add detailed method comparison
		echo '<div style="margin-top: 15px; display: flex; gap: 20px;">';

		// Email method info
		echo '<div style="flex: 1; background: #f0f6fc; padding: 15px; border-radius: 4px;">';
		echo '<h4 style="margin-top: 0; color: #2271b1;">' . esc_html__('Email Method', 'so-ssl') . '</h4>';
		echo '<ul style="margin: 0; padding-left: 20px;">';
		echo '<li>' . esc_html__('✓ Easy to set up', 'so-ssl') . '</li>';
		echo '<li>' . esc_html__('✓ No app required', 'so-ssl') . '</li>';
		echo '<li>' . esc_html__('✓ Works on any device', 'so-ssl') . '</li>';
		echo '<li>' . esc_html__('✗ Requires email access', 'so-ssl') . '</li>';
		echo '<li>' . esc_html__('✗ Less secure than app', 'so-ssl') . '</li>';
		echo '</ul>';
		echo '</div>';

		// Authenticator method info
		echo '<div style="flex: 1; background: #f0f6fc; padding: 15px; border-radius: 4px;">';
		echo '<h4 style="margin-top: 0; color: #2271b1;">' . esc_html__('Authenticator App', 'so-ssl') . '</h4>';
		echo '<ul style="margin: 0; padding-left: 20px;">';
		echo '<li>' . esc_html__('✓ More secure', 'so-ssl') . '</li>';
		echo '<li>' . esc_html__('✓ Works offline', 'so-ssl') . '</li>';
		echo '<li>' . esc_html__('✓ Instant codes', 'so-ssl') . '</li>';
		echo '<li>' . esc_html__('✗ Requires smartphone', 'so-ssl') . '</li>';
		echo '<li>' . esc_html__('✗ Initial setup needed', 'so-ssl') . '</li>';
		echo '</ul>';
		echo '</div>';

		echo '</div>';
	}

/**
 * HSTS section description.
 *
 * @since    1.0.2
 */
public function hsts_section_callback() {
    echo '<p>' . esc_html__('HTTP Strict Transport Security (HSTS) instructs browsers to only access your site over HTTPS, even if the user enters or clicks on a plain HTTP URL. This helps protect against SSL stripping attacks.', 'so-ssl') . '</p>';
    echo '<div class="notice notice-warning inline"><p><strong>'.esc_html__('Warning:', 'so-ssl') . '</strong>' . esc_html__(' Only enable HSTS if you are certain your site will always use HTTPS. Once a browser receives this header, it will not allow access to your site over HTTP until the max-age expires, even if you disable SSL later.', 'so-ssl') . '</p></div>';
}

/**
 * X-Frame-Options section description.
 *
 * @since    1.0.2
 */
public function xframe_section_callback() {
    echo '<p>' . esc_html__('X-Frame-Options header controls whether your site can be loaded in an iframe. This helps prevent clickjacking attacks where an attacker might embed your site in their own malicious site.', 'so-ssl') . '</p>';
}

/**
 * CSP section description.
 *
 * @since    1.0.2
 */
public function csp_section_callback() {
    echo '<p>' . esc_html__('Content Security Policy (CSP) with frame-ancestors directive is a modern replacement for X-Frame-Options. It provides more flexibility for controlling which domains can embed your site in an iframe.', 'so-ssl') . '</p>';
    echo '<p>' . esc_html__('Note: You can use both X-Frame-Options and CSP frame-ancestors for better browser compatibility.', 'so-ssl') . '</p>';
}

/**
 * Force SSL field callback.
 *
 * @since    1.0.2
 */
public function force_ssl_callback() {
    $force_ssl = get_option('so_ssl_force_ssl', 0);

    echo '<label for="so_ssl_force_ssl">';
    echo '<input type="checkbox" id="so_ssl_force_ssl" name="so_ssl_force_ssl" value="1" ' . checked(1, $force_ssl, false) . '/>';
    echo esc_html__('Force all traffic to use HTTPS/SSL', 'so-ssl');
    echo '</label>';
    echo '<p class="description">' . esc_html__('Warning: Only enable this if you have a valid SSL certificate installed.', 'so-ssl') . '</p>';
}

/**
 * Enable HSTS field callback.
 *
 * @since    1.0.2
 */
public function enable_hsts_callback() {
    $enable_hsts = get_option('so_ssl_enable_hsts', 0);

    echo '<label for="so_ssl_enable_hsts">';
    echo '<input type="checkbox" id="so_ssl_enable_hsts" name="so_ssl_enable_hsts" value="1" ' . checked(1, $enable_hsts, false) . '/>';
    echo esc_html__('Enable HTTP Strict Transport Security (HSTS)', 'so-ssl');
    echo '</label>';
    echo '<p class="description">' . esc_html__('Adds the Strict-Transport-Security header to tell browsers to always use HTTPS for this domain.', 'so-ssl') . '</p>';
}

/**
 * HSTS Max Age field callback.
 *
 * @since    1.0.2
 */
public function hsts_max_age_callback() {
    $max_age = get_option('so_ssl_hsts_max_age', 31536000);

    echo '<select id="so_ssl_hsts_max_age" name="so_ssl_hsts_max_age">';
    echo '<option value="86400" ' . selected(86400, $max_age, false) . '>' . esc_html__('1 Day (86400 seconds)', 'so-ssl') . '</option>';
    echo '<option value="604800" ' . selected(604800, $max_age, false) . '>' . esc_html__('1 Week (604800 seconds)', 'so-ssl') . '</option>';
    echo '<option value="2592000" ' . selected(2592000, $max_age, false) . '>' . esc_html__('1 Month (2592000 seconds)', 'so-ssl') . '</option>';
    echo '<option value="31536000" ' . selected(31536000, $max_age, false) . '>' . esc_html__('1 Year (31536000 seconds) - Recommended', 'so-ssl') . '</option>';
    echo '<option value="63072000" ' . selected(63072000, $max_age, false) . '>' . esc_html__('2 Years (63072000 seconds)', 'so-ssl') . '</option>';
    echo '</select>';
    echo '<p class="description">' . esc_html__('How long browsers should remember that this site is only to be accessed using HTTPS.', 'so-ssl') . '</p>';
}

/**
 * HSTS Include Subdomains field callback.
 *
 * @since    1.0.2
 */
public function hsts_subdomains_callback() {
    $include_subdomains = get_option('so_ssl_hsts_subdomains', 0);

    echo '<label for="so_ssl_hsts_subdomains">';
    echo '<input type="checkbox" id="so_ssl_hsts_subdomains" name="so_ssl_hsts_subdomains" value="1" ' . checked(1, $include_subdomains, false) . '/>';
    echo esc_html__('Apply HSTS to all subdomains', 'so-ssl');
    echo '</label>';
    echo '<p class="description">' . esc_html__('Warning: Only enable if ALL subdomains have SSL certificates!', 'so-ssl') . '</p>';
}

/**
 * HSTS Preload field callback.
 *
 * @since    1.0.2
 */
public function hsts_preload_callback() {
    $preload = get_option('so_ssl_hsts_preload', 0);

    echo '<label for="so_ssl_hsts_preload">';
    echo '<input type="checkbox" id="so_ssl_hsts_preload" name="so_ssl_hsts_preload" value="1" ' . checked(1, $preload, false) . '/>';
    echo esc_html__('Add preload flag', 'so-ssl');
    echo '</label>';
    echo '<p class="description">';
    printf(
        /* translators: %s: URL to HSTS Preload List website */
        esc_html__('This is necessary for submitting to the <a href="%s" target="_blank">HSTS Preload List</a>. Only enable if you intend to submit your site to this list.', 'so-ssl'),
        'https://hstspreload.org/'
    );
    echo '</p>';
}

/**
 * Enable X-Frame-Options field callback.
 *
 * @since    1.0.2
 */
public function enable_xframe_callback() {
    $enable_xframe = get_option('so_ssl_enable_xframe', 1);

    echo '<label for="so_ssl_enable_xframe">';
    echo '<input type="checkbox" id="so_ssl_enable_xframe" name="so_ssl_enable_xframe" value="1" ' . checked(1, $enable_xframe, false) . '/>';
    echo esc_html__('Enable X-Frame-Options header', 'so-ssl');
    echo '</label>';
    echo '<p class="description">' . esc_html__('Controls whether your site can be loaded in an iframe (recommended for security).', 'so-ssl') . '</p>';
}

/**
 * X-Frame-Options value field callback.
 *
 * @since    1.0.2
 */
public function xframe_option_callback() {
    $xframe_option = get_option('so_ssl_xframe_option', 'sameorigin');

    echo '<select id="so_ssl_xframe_option" name="so_ssl_xframe_option">';
    echo '<option value="deny" ' . selected('deny', $xframe_option, false) . '>' . esc_html__('DENY - Prevents any site from loading this site in an iframe', 'so-ssl') . '</option>';
    echo '<option value="sameorigin" ' . selected('sameorigin', $xframe_option, false) . '>' . esc_html__('SAMEORIGIN - Only allow same site to frame content (recommended)', 'so-ssl') . '</option>';
    echo '<option value="allowfrom" ' . selected('allowfrom', $xframe_option, false) . '>' . esc_html__('ALLOW-FROM - Allow a specific domain to frame content', 'so-ssl') . '</option>';
    echo '</select>';
    echo '<p class="description">' . esc_html__('Determines which sites (if any) can load your site in an iframe.', 'so-ssl') . '</p>';
}

/**
 * X-Frame-Options Allow-From domain field callback.
 *
 * @since    1.0.2
 */
public function xframe_allow_from_callback() {
    $allow_from = get_option('so_ssl_xframe_allow_from', '');

    echo '<input type="url" id="so_ssl_xframe_allow_from" name="so_ssl_xframe_allow_from" value="' . esc_attr($allow_from) . '" class="regular-text" placeholder="https://example.com" />';
    echo '<p class="description">' . esc_html__('Enter the full domain that is allowed to load your site in an iframe (only used with ALLOW-FROM option).', 'so-ssl') . '</p>';
}

/**
 * Enable CSP Frame-Ancestors field callback.
 *
 * @since    1.0.2
 */
public function enable_csp_frame_ancestors_callback() {
    $enable_csp = get_option('so_ssl_enable_csp_frame_ancestors', 0);

    echo '<label for="so_ssl_enable_csp_frame_ancestors">';
    echo '<input type="checkbox" id="so_ssl_enable_csp_frame_ancestors" name="so_ssl_enable_csp_frame_ancestors" value="1" ' . checked(1, $enable_csp, false) . '/>';
    echo esc_html__('Enable Content Security Policy: frame-ancestors directive', 'so-ssl');
    echo '</label>';
    echo '<p class="description">' . esc_html__('Adds the Content-Security-Policy header with frame-ancestors directive to control iframe embedding.', 'so-ssl') . '</p>';
}

    /**
     * CSP Frame-Ancestors value field callback.
     *
     * @since    1.0.2
     */
    public function csp_frame_ancestors_option_callback() {
        $csp_option = get_option('so_ssl_csp_frame_ancestors_option', 'none');

        echo '<select id="so_ssl_csp_frame_ancestors_option" name="so_ssl_csp_frame_ancestors_option">';
        echo '<option value="none" ' . selected('none', $csp_option, false) . '>' . esc_html__('\'none\' - No site can embed your content (most restrictive)', 'so-ssl') . '</option>';
        echo '<option value="self" ' . selected('self', $csp_option, false) . '>' . esc_html__('\'self\' - Only your own site can embed your content', 'so-ssl') . '</option>';
        echo '<option value="custom" ' . selected('custom', $csp_option, false) . '>' . esc_html__('Custom - Specify allowed domains', 'so-ssl') . '</option>';
        echo '</select>';
        echo '<p class="description">' . esc_html__('Determines which sites (if any) can embed your site in an iframe.', 'so-ssl') . '</p>';
    }

    /**
     * CSP Include Self field callback.
     *
     * @since    1.0.2
     */
    public function csp_include_self_callback() {
        $include_self = get_option('so_ssl_csp_include_self', 0);

        echo '<label for="so_ssl_csp_include_self">';
        echo '<input type="checkbox" id="so_ssl_csp_include_self" name="so_ssl_csp_include_self" value="1" ' . checked(1, $include_self, false) . '/>';
        echo esc_html__('Include \'self\' in allowed domains', 'so-ssl');
        echo '</label>';
        echo '<p class="description">' . esc_html__('Allow your own site to embed your content when using custom domains.', 'so-ssl') . '</p>';
    }

    /**
     * CSP Frame-Ancestors domains field callback.
     *
     * @since    1.0.2
     */
    public function csp_frame_ancestors_domains_callback() {
        $domains = get_option('so_ssl_csp_frame_ancestors_domains', '');

        echo '<textarea id="so_ssl_csp_frame_ancestors_domains" name="so_ssl_csp_frame_ancestors_domains" rows="5" class="large-text" placeholder="https://example.com">' . esc_textarea($domains) . '</textarea>';
        echo '<p class="description">' . esc_html__('Enter domains that are allowed to embed your site, one per line. Example: https://example.com', 'so-ssl') . '</p>';
        echo '<p class="description">' . esc_html__('You can also use wildcards like *.example.com to allow all subdomains.', 'so-ssl') . '</p>';
    }

	/**
	 * Privacy Compliance Section Callback.
	 *
	 * @since    1.4.4
	 */
	public function privacy_compliance_section_callback() {
		$page_slug = get_option('so_ssl_privacy_page_slug', 'privacy-acknowledgment');
        ?>

        <div class="so-ssl-admin-info-box">
            <h3><?php esc_html_e('How This Works', 'so-ssl'); ?></h3>
            <ul>
                <li><?php esc_html_e('When enabled, users will be redirected to a privacy acknowledgment page after login.', 'so-ssl'); ?></li>
                <li><?php esc_html_e('Users must check the acknowledgment box to access the site.', 'so-ssl'); ?></li>
                <li><?php esc_html_e('The acknowledgment is stored in user metadata with a timestamp.', 'so-ssl'); ?></li>
                <li><?php esc_html_e('You can set an expiry period after which users must re-acknowledge the notice.', 'so-ssl'); ?></li>
                <li><?php esc_html_e('Configure for GDPR and US privacy compliance settings to inform users about data collection and tracking.', 'so-ssl'); ?></li>
            </ul>
        </div>

        <div>
            <a href="<?php echo esc_url(admin_url('admin.php?page=so-ssl-privacy')); ?>" target="_blank" class="button button-primary" style="font-size: 14px; height: auto; padding: 8px 16px;">
				<?php esc_html_e('Open Privacy Page', 'so-ssl'); ?>
                <span class="dashicons dashicons-external" style="font-size: 16px; height: 16px; width: 16px; vertical-align: text-bottom;"></span>
            </a>
        </div>

        <style>
            .so-ssl-admin-description {
                font-size: 14px;
                margin-bottom: 20px;
            }

            .so-ssl-admin-info-box {
                background: #f8f9fa;
                border-left: 4px solid #72aee6;
                padding: 15px 20px;
                margin: 20px 0;
                border-radius: 0 4px 4px 0;
            }

            .so-ssl-admin-info-box h3 {
                margin-top: 0;
                color: #2271b1;
            }

            .so-ssl-admin-info-box ul {
                margin-left: 20px;
            }

            .so-ssl-admin-tips {
                background: #f0f6fc;
                border-left: 4px solid #2271b1;
                padding: 15px 20px;
                margin: 20px 0;
                border-radius: 0 4px 4px 0;
            }

            .so-ssl-admin-tips h3 {
                margin-top: 0;
                color: #2271b1;
            }

            .so-ssl-preview-container {
                border: 1px solid #dcdcde;
                border-radius: 4px;
                padding: 20px;
                margin: 15px 0;
                background: #fff;
                max-width: 650px;
            }

            .so-ssl-preview-header {
                border-bottom: 1px solid #dcdcde;
                padding-bottom: 10px;
                margin-bottom: 15px;
            }

            .so-ssl-preview-header h2 {
                margin: 0;
                color: #2271b1;
            }

            .so-ssl-preview-content {
                margin-bottom: 20px;
                padding: 10px;
                background: #f8f9fa;
                border-radius: 4px;
            }

            .so-ssl-preview-checkbox {
                margin: 15px 0;
            }

            .so-ssl-preview-button button {
                padding: 8px 15px;
                background: #2271b1;
                border: none;
                color: #fff;
                border-radius: 4px;
                opacity: 0.7;
            }

            /* Highlighting effect for the privacy page link */
            .so-ssl-privacy-page-link {
                position: relative;
                overflow: hidden;
            }

            .so-ssl-privacy-page-link:after {
                content: "";
                position: absolute;
                top: 0;
                left: -100%;
                width: 100%;
                height: 100%;
                background: linear-gradient(
                        90deg,
                        transparent 0%,
                        rgba(255, 255, 255, 0.2) 50%,
                        transparent 100%
                );
                animation: shine 3s infinite;
            }

            @keyframes shine {
                0% { left: -100%; }
                20% { left: 100%; }
                100% { left: 100%; }
            }
        </style>
        <style>
            .so-ssl-compliance-highlights {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
                gap: 20px;
                margin-bottom: 30px;
            }

            .so-ssl-compliance-detail {
                background: #fff;
                border: 1px solid #dcdcde;
                border-radius: 4px;
                padding: 15px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            }

            .so-ssl-compliance-detail h4 {
                display: flex;
                align-items: center;
                margin-top: 0;
                color: #2271b1;
            }

            .so-ssl-compliance-detail h4 .dashicons {
                margin-right: 8px;
                color: #2271b1;
            }

            .so-ssl-compliance-detail p {
                margin-bottom: 0;
            }

            .so-ssl-compliance-highlights .button {
                margin-top: 15px;
                grid-column: 1 / -1;
                justify-self: start;
            }
        </style>

        <?php
	}

    /**
     * Add JavaScript to show/hide fields based on selected options.
     *
     * @since    1.0.2
     */
    public function admin_footer_js() {
        ?>
        <script>
        jQuery(document).ready(function($) {
            // X-Frame-Options Allow-From toggle
            function toggleAllowFrom() {
                var selected = $('#so_ssl_xframe_option').val();
                if (selected === 'allowfrom') {
                    $('.so_ssl_allow_from_field').show();
                } else {
                    $('.so_ssl_allow_from_field').hide();
                }
            }

            // CSP Custom Fields toggle
            function toggleCSPCustomFields() {
                var selected = $('#so_ssl_csp_frame_ancestors_option').val();
                if (selected === 'custom') {
                    $('.so_ssl_csp_custom_field').show();
                } else {
                    $('.so_ssl_csp_custom_field').hide();
                }
            }

            // CSP upgrade-insecure-requests handling
            function setupCSPUpgradeRequests() {
                var $field = $('#so_ssl_csp_upgrade_insecure_requests');
                if ($field.is(':checkbox')) {
                    return; // Already set up correctly
                }

                // Get the current value
                var value = $field.val();

                // Replace the input with a checkbox
                var $parent = $field.parent();
                $field.remove();

                // Create new checkbox
                var checked = (value === '1') ? 'checked' : '';
                $parent.prepend('<input type="checkbox" id="so_ssl_csp_upgrade_insecure_requests" name="so_ssl_csp_upgrade_insecure_requests" value="1" ' + checked + '/>');
            }

            // Initialize all UI elements
            function initializeUI() {
                // Initial states
                toggleAllowFrom();
                toggleCSPCustomFields();
                setupCSPUpgradeRequests();

                // Field toggles for permissions policy
                $('.so_ssl_permissions_policy_field').each(function() {
                    var $parent = $(this).closest('tr');
                    if (!$('#so_ssl_enable_permissions_policy').is(':checked')) {
                        $parent.not(':first').hide();
                    }
                });

                // Field toggles for CSP
                $('.so_ssl_csp_directive_field').each(function() {
                    var $parent = $(this).closest('tr');
                    if (!$('#so_ssl_enable_csp').is(':checked')) {
                        $parent.not(':first-child').not(':nth-child(2)').hide();
                    }
                });
            }

            // On change handlers
            $('#so_ssl_xframe_option').on('change', function() {
                toggleAllowFrom();
            });

            $('#so_ssl_csp_frame_ancestors_option').on('change', function() {
                toggleCSPCustomFields();
            });

            // Show/hide permissions policy fields when toggle changes
            $('#so_ssl_enable_permissions_policy').on('change', function() {
                if ($(this).is(':checked')) {
                    $('.so_ssl_permissions_policy_field').closest('tr').show();
                } else {
                    $('.so_ssl_permissions_policy_field').closest('tr').not(':first').hide();
                }
            });

            // Show/hide CSP directive fields when toggle changes
            $('#so_ssl_enable_csp').on('change', function() {
                if ($(this).is(':checked')) {
                    $('.so_ssl_csp_directive_field').closest('tr').show();
                } else {
                    $('.so_ssl_csp_directive_field').closest('tr').not(':first-child').not(':nth-child(2)').hide();
                }
            });

            // Initialize UI
            initializeUI();
        });
        </script>
        <script>
    jQuery(document).ready(function($) {
        // Handle the toggle between predefined and custom CSP options
        $('.so-ssl-csp-type-selector').on('change', function() {
            var targetField = $(this).data('target');
            var selectedType = $(this).val();

            if (selectedType === 'predefined') {
                $('#' + targetField + '_predefined').show();
                $('#' + targetField + '_custom').hide();

                // Update textarea with dropdown value
                var dropdownValue = $('#' + targetField + '_dropdown').val();
                $('#' + targetField).val(dropdownValue);
            } else {
                $('#' + targetField + '_predefined').hide();
                $('#' + targetField + '_custom').show();
            }
        });

        // When dropdown value changes, update the hidden textarea
        $('.so-ssl-csp-dropdown').on('change', function() {
            var targetField = $(this).data('target');
            var selectedValue = $(this).val();
            $('#' + targetField).val(selectedValue);
        });
    });
    </script>
        <?php
    }

    /**
     * Check if SSL is available and activate it if needed.
     *
     * @since    1.0.2
     */
    public function check_ssl() {
        // Check if site has SSL capability
        $has_ssl = is_ssl();

        // Get current setting
        $force_ssl = get_option('so_ssl_force_ssl', 0);

        // If SSL is forced and we're not on HTTPS, redirect
        if ($force_ssl && !$has_ssl && !is_admin()) {
            // Get current URL with proper validation and sanitization
            $http_host = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : '';
            $request_uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';

            if (!empty($http_host) && !empty($request_uri)) {
                $current_url = 'http://' . $http_host . $request_uri;
                $ssl_url = str_replace('http://', 'https://', $current_url);

                // Redirect to HTTPS
                wp_redirect($ssl_url, 301);
                exit;
            }
        }

        return $has_ssl;
    }

    /**
     * Add HTTP Strict Transport Security (HSTS) header if enabled.
     *
     * @since    1.0.2
     */
    public function add_hsts_header() {
        // Only proceed if we're on HTTPS
        if (!is_ssl()) {
            return;
        }

        $enable_hsts = get_option('so_ssl_enable_hsts', 0);

        if ($enable_hsts) {
            $max_age = get_option('so_ssl_hsts_max_age', 31536000);
            $include_subdomains = get_option('so_ssl_hsts_subdomains', 0) ? '; includeSubDomains' : '';
            $preload = get_option('so_ssl_hsts_preload', 0) ? '; preload' : '';

            $hsts_header = "max-age=" . intval($max_age) . $include_subdomains . $preload;

            header("Strict-Transport-Security: " . $hsts_header);
        }
    }

    /**
     * Add X-Frame-Options header if enabled.
     *
     * @since    1.0.2
     */
    public function add_xframe_header() {
        $enable_xframe = get_option('so_ssl_enable_xframe', 1);

        if ($enable_xframe) {
            $xframe_option = get_option('so_ssl_xframe_option', 'sameorigin');

            // Set header based on selected option
            if ($xframe_option === 'deny') {
                header('X-Frame-Options: DENY');
            } elseif ($xframe_option === 'sameorigin') {
                header('X-Frame-Options: SAMEORIGIN');
            } elseif ($xframe_option === 'allowfrom' && !empty(get_option('so_ssl_xframe_allow_from', ''))) {
                $allowed_origin = get_option('so_ssl_xframe_allow_from', '');
                header('X-Frame-Options: ALLOW-FROM ' . esc_url($allowed_origin));
            }
        }
    }

    /**
     * Add Content Security Policy header with frame-ancestors directive if enabled.
     *
     * @since    1.0.2
     */
    public function add_csp_frame_ancestors_header() {
        $enable_csp_frame_ancestors = get_option('so_ssl_enable_csp_frame_ancestors', 0);

        if ($enable_csp_frame_ancestors) {
            $csp_option = get_option('so_ssl_csp_frame_ancestors_option', 'none');
            $csp_value = '';

            // Set CSP value based on selected option
            if ($csp_option === 'none') {
                $csp_value = "frame-ancestors 'none'";
            } elseif ($csp_option === 'self') {
                $csp_value = "frame-ancestors 'self'";
            } elseif ($csp_option === 'custom') {
                $allowed_domains = get_option('so_ssl_csp_frame_ancestors_domains', '');
                $domains = explode("\n", $allowed_domains);
                $valid_domains = array();

                // Add 'self' if selected
                if (get_option('so_ssl_csp_include_self', 0)) {
                    $valid_domains[] = "'self'";
                }

                // Process and validate each domain
                foreach ($domains as $domain) {
                    $domain = trim($domain);
                    if (!empty($domain)) {
                        // Add single quotes if it's a special keyword like 'none'
                        if (in_array($domain, array('none', 'self'))) {
                            $domain = "'$domain'";
                        }
                        $valid_domains[] = $domain;
                    }
                }

                if (!empty($valid_domains)) {
                    $csp_value = "frame-ancestors " . implode(' ', $valid_domains);
                } else {
                    $csp_value = "frame-ancestors 'none'";  // Default if no valid domains
                }
            }

            // Only add header if we have a value
            if (!empty($csp_value)) {
                header("Content-Security-Policy: $csp_value");
            }
        }
    }

/**
 * Referrer Policy section description.
 *
 * @since    1.0.2
 */
public function referrer_policy_section_callback() {
    echo '<p>' . esc_html__('The Referrer-Policy HTTP header controls how much referrer information should be included with requests.', 'so-ssl') . '</p>';
}

/**
 * Enable Referrer Policy field callback.
 *
 * @since    1.0.2
 */
public function enable_referrer_policy_callback() {
    $enable_referrer_policy = get_option('so_ssl_enable_referrer_policy', 0);

    echo '<label for="so_ssl_enable_referrer_policy">';
    echo '<input type="checkbox" id="so_ssl_enable_referrer_policy" name="so_ssl_enable_referrer_policy" value="1" ' . checked(1, $enable_referrer_policy, false) . '/>';
    echo esc_html__('Enable Referrer Policy header', 'so-ssl');
    echo '</label>';
    echo '<p class="description">' . esc_html__('Adds the Referrer-Policy header to control what information is sent in the Referer header.', 'so-ssl') . '</p>';
}

/**
 * Referrer Policy value field callback.
 *
 * @since    1.0.2
 */
public function referrer_policy_option_callback() {
    $referrer_policy = get_option('so_ssl_referrer_policy_option', 'strict-origin-when-cross-origin');

    echo '<select id="so_ssl_referrer_policy_option" name="so_ssl_referrer_policy_option">';
    echo '<option value="no-referrer" ' . selected('no-referrer', $referrer_policy, false) . '>' . esc_html__('no-referrer - No referrer information is sent', 'so-ssl') . '</option>';
    echo '<option value="no-referrer-when-downgrade" ' . selected('no-referrer-when-downgrade', $referrer_policy, false) . '>' . esc_html__('no-referrer-when-downgrade - No referrer when downgrading (e.g., HTTPS→HTTP)', 'so-ssl') . '</option>';
    echo '<option value="origin" ' . selected('origin', $referrer_policy, false) . '>' . esc_html__('origin - Only send the origin of the document', 'so-ssl') . '</option>';
    echo '<option value="origin-when-cross-origin" ' . selected('origin-when-cross-origin', $referrer_policy, false) . '>' . esc_html__('origin-when-cross-origin - Full path for same origin, only origin for cross-origin', 'so-ssl') . '</option>';
    echo '<option value="same-origin" ' . selected('same-origin', $referrer_policy, false) . '>' . esc_html__('same-origin - Send referrer only for same-origin requests', 'so-ssl') . '</option>';
    echo '<option value="strict-origin" ' . selected('strict-origin', $referrer_policy, false) . '>' . esc_html__('strict-origin - Only send origin when protocol security level stays the same', 'so-ssl') . '</option>';
    echo '<option value="strict-origin-when-cross-origin" ' . selected('strict-origin-when-cross-origin', $referrer_policy, false) . '>' . esc_html__('strict-origin-when-cross-origin - (Recommended) Send full referrer to same-origin, only send origin when protocol security level stays the same', 'so-ssl') . '</option>';
    echo '<option value="unsafe-url" ' . selected('unsafe-url', $referrer_policy, false) . '>' . esc_html__('unsafe-url - Always send full referrer information (least secure)', 'so-ssl') . '</option>';
    echo '</select>';
    echo '<p class="description">' . esc_html__('Determines how much information is included in the Referer header when making requests.', 'so-ssl') . '</p>';
}

/**
 * Add Referrer Policy header if enabled.
 *
 * @since    1.0.2
 */
public function add_referrer_policy_header() {
    $enable_referrer_policy = get_option('so_ssl_enable_referrer_policy', 0);

    if ($enable_referrer_policy) {
        $referrer_policy = get_option('so_ssl_referrer_policy_option', 'strict-origin-when-cross-origin');

        // Only add header if we have a valid policy
        if (!empty($referrer_policy)) {
            header("Referrer-Policy: " . sanitize_text_field($referrer_policy));
        }
    }
}

    /**
         * Content Security Policy section description.
         *
         * @since    1.0.2
         */
        public function csp_full_section_callback() {
            echo '<p>' . esc_html__('Content Security Policy (CSP) is an added layer of security that helps to detect and mitigate certain types of attacks, including Cross-Site Scripting (XSS) and data injection attacks.', 'so-ssl') . '</p>';
            echo '<p>' . esc_html__('It is recommended to first enable CSP in "Report-Only" mode to ensure it does not break your site functionality.', 'so-ssl') . '</p>';
            echo '<div class="notice notice-warning inline"><p>'.esc_html__('Warning:', 'so-ssl') . esc_html__(' Incorrect CSP settings can break functionality on your site. Make sure to test thoroughly.', 'so-ssl') . '</p></div>';
        }

        /**
         * Enable CSP field callback.
         *
         * @since    1.0.2
         */
        public function enable_csp_callback() {
            $enable_csp = get_option('so_ssl_enable_csp', 0);

            echo '<label for="so_ssl_enable_csp">';
            echo '<input type="checkbox" id="so_ssl_enable_csp" name="so_ssl_enable_csp" value="1" ' . checked(1, $enable_csp, false) . '/>';
            echo esc_html__('Enable Content Security Policy header', 'so-ssl');
            echo '</label>';
            echo '<p class="description">' . esc_html__('Adds the Content-Security-Policy header to restrict what resources can be loaded.', 'so-ssl') . '</p>';
        }

        /**
         * CSP Mode field callback.
         *
         * @since    1.0.2
         */
        public function csp_mode_callback() {
            $csp_mode = get_option('so_ssl_csp_mode', 'report-only');

            ?>
            <select id="so_ssl_csp_mode" name="so_ssl_csp_mode">
                <option value="report-only" <?php selected('report-only', $csp_mode); ?>>
                    <?php
                    /* translators: CSP mode option that only reports violations without enforcing */
                    esc_html_e('Report-Only - Just report violations, do not enforce (recommended for testing)', 'so-ssl');
                    ?>
                </option>
                <option value="enforce" <?php selected('enforce', $csp_mode); ?>>
                    <?php
                    /* translators: CSP mode option that enforces the policy */
                    esc_html_e('Enforce - Enforce the policy (only use after testing)', 'so-ssl');
                    ?>
                </option>
            </select>
            <p class="description">
                <?php
                /* translators: Description of what the CSP mode setting does */
                esc_html_e('Determines whether the browser should enforce the policy or just report violations.', 'so-ssl');
                ?>
            </p>
            <?php
        }

        /**
         * CSP Directive field callback.
         *
         * @param    array    $args    The arguments passed to the callback.
         *
         *@since    1.0.2
         */
        public function csp_directive_callback($args) {
            $directive = $args['directive'];
            $field_id = 'so_ssl_csp_' . str_replace('-', '_', $directive);
            $value = get_option($field_id, $args['default_value']);

            if ($directive === 'upgrade-insecure-requests') {
                echo '<label for="' . esc_attr($field_id) . '">';
                echo '<input type="checkbox" id="' . esc_attr($field_id) . '" name="' . esc_attr($field_id) . '" value="1" ' . checked('1', $value, false) . '/>';
                echo esc_html__('Enable upgrade-insecure-requests directive', 'so-ssl');
                echo '</label>';
                echo '<p class="description">' . esc_html__('Instructs browsers to upgrade HTTP requests to HTTPS before fetching.', 'so-ssl') . '</p>';
            } else {
                // Get the selection type (predefined or custom)
                $type_field_id = $field_id . '_type';
                $selection_type = get_option($type_field_id, 'predefined');

                // Create predefined options based on directive type
                $options = $this->get_csp_predefined_options($directive);

                // Selection type radio buttons
                echo '<div style="margin-bottom: 10px;">';
                echo '<label style="margin-right: 15px;"><input type="radio" name="' . esc_attr($type_field_id) . '" value="predefined" ' . checked('predefined', $selection_type, false) . ' class="so-ssl-csp-type-selector" data-target="' . esc_attr($field_id) . '"/> ' . esc_html__('Predefined', 'so-ssl') . '</label>';
                echo '<label><input type="radio" name="' . esc_attr($type_field_id) . '" value="custom" ' . checked('custom', $selection_type, false) . ' class="so-ssl-csp-type-selector" data-target="' . esc_attr($field_id) . '"/> ' . esc_html__('Custom', 'so-ssl') . '</label>';
                echo '</div>';

                // Dropdown for predefined options
                echo '<div id="' . esc_attr($field_id) . '_predefined" class="so-ssl-csp-option-container" style="' . ($selection_type === 'predefined' ? '' : 'display: none;') . '">';
                echo '<select id="' . esc_attr($field_id) . '_dropdown" class="so-ssl-csp-dropdown" data-target="' . esc_attr($field_id) . '">';

                foreach ($options as $option_value => $option_label) {
                    echo '<option value="' . esc_attr($option_value) . '" ' . selected($value, $option_value, false) . '>' . esc_html($option_label) . '</option>';
                }

                echo '</select>';
                echo '</div>';

                // Text area for custom value
                echo '<div id="' . esc_attr($field_id) . '_custom" class="so-ssl-csp-option-container" style="' . ($selection_type === 'custom' ? '' : 'display: none;') . '">';
                echo '<textarea id="' . esc_attr($field_id) . '" name="' . esc_attr($field_id) . '" rows="2" class="large-text" placeholder="' . esc_attr($args['default_value']) . '">' . esc_textarea($value) . '</textarea>';
                echo '</div>';

                // Provide helper information based on directive
                switch ($directive) {
                    case 'default-src':
                        $description = esc_html__('Fallback for other CSP directives. Example: \'self\' https://*.trusted-cdn.com', 'so-ssl');
                        break;
                    case 'script-src':
                        $description = esc_html__('Controls JavaScript sources. Example: \'self\' \'unsafe-inline\' https://trusted-scripts.com', 'so-ssl');
                        break;
                    case 'style-src':
                        $description = esc_html__('Controls CSS sources. Example: \'self\' \'unsafe-inline\' https://fonts.googleapis.com', 'so-ssl');
                        break;
                    case 'img-src':
                        $description = esc_html__('Controls image sources. Example: \'self\' data: https://*.trusted-cdn.com', 'so-ssl');
                        break;
                    default:
                        $description = sprintf(/* translators: Title */esc_html__('Controls %s sources.', 'so-ssl'), $directive);
                }

                echo '<p class="description">' . esc_html($description) . '</p>';
            }
        }

        /**
             * Get predefined options for CSP directives
             *
             * @param string $directive The CSP directive
             *
             * @return array Array of options
             */
            private function get_csp_predefined_options($directive) {
                // Common options for most directives
                $common_options = array(
                    "'self'" => __("'self' - Allow content from same origin only", 'so-ssl'),
                    "'self' https:" => __("'self' https: - Allow content from same origin and any secure HTTPS source", 'so-ssl'),
                    "'none'" => __("'none' - Block all content of this type", 'so-ssl'),
                    "*" => __("* - Allow content from any source (least secure)", 'so-ssl'),
                );

                // Specific options for different directives
                switch ($directive) {
                    case 'script-src':
                        return array_merge($common_options, array(
                            "'self' 'unsafe-inline'" => __("'self' 'unsafe-inline' - Allow same origin and inline scripts", 'so-ssl'),
                            "'self' 'unsafe-eval'" => __("'self' 'unsafe-eval' - Allow same origin and eval()", 'so-ssl'),
                            "'self' 'unsafe-inline' 'unsafe-eval'" => __("'self' 'unsafe-inline' 'unsafe-eval' - Allow same origin, inline scripts and eval()", 'so-ssl'),
                        ));

                    case 'style-src':
                        return array_merge($common_options, array(
                            "'self' 'unsafe-inline'" => __("'self' 'unsafe-inline' - Allow same origin and inline styles", 'so-ssl'),
                            "'self' https://fonts.googleapis.com" => __("'self' fonts.googleapis.com - Allow same origin and Google Fonts", 'so-ssl'),
                        ));

                    case 'img-src':
                        return array_merge($common_options, array(
                            "'self' data:" => __("'self' data: - Allow same origin and data URIs", 'so-ssl'),
                            "'self' data: https:" => __("'self' data: https: - Allow same origin, data URIs and any HTTPS source", 'so-ssl'),
                        ));

                    case 'connect-src':
                        return array_merge($common_options, array(
                            "'self' https://api.example.com" => __("'self' api.example.com - Allow same origin and specific API", 'so-ssl'),
                        ));

                    case 'font-src':
                        return array_merge($common_options, array(
                            "'self' https://fonts.gstatic.com" => __("'self' fonts.gstatic.com - Allow same origin and Google Fonts", 'so-ssl'),
                            "'self' data:" => __("'self' data: - Allow same origin and data URIs", 'so-ssl'),
                        ));

                    case 'object-src':
                        return array(
                            "'none'" => __("'none' - Block all plugins (recommended)", 'so-ssl'),
                            "'self'" => __("'self' - Allow plugins from same origin only", 'so-ssl'),
                        );

                    case 'media-src':
                        return array_merge($common_options, array(
                            "'self' https://media.example.com" => __("'self' media.example.com - Allow same origin and specific media source", 'so-ssl'),
                        ));

                    case 'frame-src':
                        return array_merge($common_options, array(
                            "'self' https://www.youtube.com" => __("'self' youtube.com - Allow same origin and YouTube", 'so-ssl'),
                            "'self' https://player.vimeo.com" => __("'self' vimeo.com - Allow same origin and Vimeo", 'so-ssl'),
                        ));

                    case 'base-uri':
                        return array(
                            "'self'" => __("'self' - Restrict base URI to same origin", 'so-ssl'),
                            "'none'" => __("'none' - Prevent use of base elements", 'so-ssl'),
                        );

                    case 'form-action':
                        return array(
                            "'self'" => __("'self' - Allow forms to submit to same origin only", 'so-ssl'),
                            "'self' https:" => __("'self' https: - Allow forms to submit to same origin and any HTTPS URL", 'so-ssl'),
                        );

                    default:
                        return $common_options;
                }
            }

            /**
                 * Add Content Security Policy header if enabled.
                 *
                 * @since    1.0.2
                 */
                public function add_content_security_policy_header() {
                    $enable_csp = get_option('so_ssl_enable_csp', 0);

                    if ($enable_csp) {
                        $csp_mode = get_option('so_ssl_csp_mode', 'report-only');
                        $header_name = $csp_mode === 'enforce' ? 'Content-Security-Policy' : 'Content-Security-Policy-Report-Only';

                        $csp_directives = array(
                            'default-src',
                            'script-src',
                            'style-src',
                            'img-src',
                            'connect-src',
                            'font-src',
                            'object-src',
                            'media-src',
                            'frame-src',
                            'base-uri',
                            'form-action'
                        );

                        $policy_parts = array();

                        // Process each directive
                        foreach ($csp_directives as $directive) {
                            $option_name = 'so_ssl_csp_' . str_replace('-', '_', $directive);
                            $value = get_option($option_name, '');

                            if (!empty($value)) {
                                $policy_parts[] = $directive . ' ' . $value;
                            }
                        }

                        // Add upgrade-insecure-requests if enabled
                        if (get_option('so_ssl_csp_upgrade_insecure_requests', '')) {
                            $policy_parts[] = 'upgrade-insecure-requests';
                        }

                        // Only set header if we have at least one directive
                        if (!empty($policy_parts)) {
                            header("$header_name: " . implode('; ', $policy_parts));
                        }
                    }
                }

                  /**
                   * Permissions Policy section description.
                   *
                   * @since    1.0.2
                   */
                  public function permissions_policy_section_callback() {
                      echo '<p>' . esc_html__('The Permissions Policy header allows you to control which browser features and APIs can be used in your site.', 'so-ssl') . '</p>';
                      echo '<p>' . esc_html__('This replaces the older Feature-Policy header with more granular controls.', 'so-ssl') . '</p>';
                  }

                  /**
                   * Enable Permissions Policy field callback.
                   *
                   * @since    1.0.2
                   */
                  public function enable_permissions_policy_callback() {
                      $enable_permissions_policy = get_option('so_ssl_enable_permissions_policy', 0);

                      echo '<label for="so_ssl_enable_permissions_policy">';
                      echo '<input type="checkbox" id="so_ssl_enable_permissions_policy" name="so_ssl_enable_permissions_policy" value="1" ' . checked(1, $enable_permissions_policy, false) . '/>';
                      echo esc_html__('Enable Permissions Policy header', 'so-ssl');
                      echo '</label>';
                      echo '<p class="description">' . esc_html__('Adds the Permissions-Policy header to control browser feature permissions.', 'so-ssl') . '</p>';
                  }

    /**
     * Permissions Policy option field callback.
     *
     * @param    array    $args    The arguments passed to the callback.
     *
     *@since    1.0.2
     */
    public function permissions_policy_option_callback($args) {
        $permission = $args['permission'];
        $option_name = 'so_ssl_permissions_policy_' . str_replace('-', '_', $permission);
        $value = get_option($option_name, $args['default']);

        echo '<select id="' . esc_attr($option_name) . '" name="' . esc_attr($option_name) . '">';
        echo '<option value="*" ' . selected('*', $value, false) . '>' . esc_html__('Allow for all origins (*)', 'so-ssl') . '</option>';
        echo '<option value="self" ' . selected('self', $value, false) . '>' . esc_html__('Allow for own origin only (self)', 'so-ssl') . '</option>';
        echo '<option value="none" ' . selected('none', $value, false) . '>' . esc_html__('Disable entirely (none)', 'so-ssl') . '</option>';
        echo '</select>';

        echo '<p class="description">' . esc_html($args['description']) . '</p>';
    }

    /**
     * Add Permissions Policy header if enabled.
     *
     * @since    1.0.2
     */
    public function add_permissions_policy_header() {
        $enable_permissions_policy = get_option('so_ssl_enable_permissions_policy', 0);

        if ($enable_permissions_policy) {
            // Define available permissions
            $permissions = array(
                'accelerometer', 'ambient-light-sensor', 'autoplay', 'battery', 'camera',
                'display-capture', 'document-domain', 'encrypted-media', 'execution-while-not-rendered',
                'execution-while-out-of-viewport', 'fullscreen', 'geolocation', 'gyroscope',
                'microphone', 'midi', 'navigation-override', 'payment', 'picture-in-picture',
                'publickey-credentials-get', 'screen-wake-lock', 'sync-xhr', 'usb', 'web-share',
                'xr-spatial-tracking'
            );

            $policy_parts = array();

            // Process each permission
            foreach ($permissions as $permission) {
                $option_name = 'so_ssl_permissions_policy_' . str_replace('-', '_', $permission);
                $value = get_option($option_name, '');

                if (!empty($value)) {
                    // Format the directive properly
                    if ($value === 'self') {
                        $directive_value = 'self';
                    } elseif ($value === 'none') {
                        $directive_value = '()';
                    } else {
                        $directive_value = '*';
                    }

                    $policy_parts[] = $permission . '=(' . $directive_value . ')';
                }
            }

            // Only set header if we have at least one directive
            if (!empty($policy_parts)) {
                header('Permissions-Policy: ' . implode(', ', $policy_parts));
            }
        }
    }

    /**
     * Cross-Origin Policy section description.
     *
     * @since    1.0.2
     */
    public function cross_origin_policy_section_callback() {
        echo '<p>' . esc_html__('Cross-Origin policies control how your site\'s resources can be used by other sites and how your site can interact with other sites.', 'so-ssl') . '</p>';
        echo '<div class="notice notice-warning inline"><p>' . esc_html__('Warning: These settings can break cross-origin functionality. Test thoroughly before enabling in production.', 'so-ssl') . '</p></div>';
    }

    /**
     * Cross-Origin Policy field callback.
     *
     * @param    array    $args    The arguments passed to the callback.
     *
     *@since    1.0.2
     */
    public function cross_origin_policy_callback($args) {
        $header = $args['header'];
        $option_name_enabled = $args['label_for'];
        $option_name_value = $args['value_id'];

        $enabled = get_option($option_name_enabled, 0);
        $value = get_option($option_name_value, '');

        // Enable checkbox
        echo '<div style="margin-bottom: 10px;">';
        echo '<label for="' . esc_attr($option_name_enabled) . '">';
        echo '<input type="checkbox" id="' . esc_attr($option_name_enabled) . '" name="' . esc_attr($option_name_enabled) . '" value="1" ' . checked(1, $enabled, false) . '/>';
        echo esc_html__('Enable', 'so-ssl') . ' ' . esc_html($header);
        echo '</label>';
        echo '</div>';

        // Value dropdown
        echo '<select id="' . esc_attr($option_name_value) . '" name="' . esc_attr($option_name_value) . '">';
        foreach ($args['options'] as $option_value => $option_text) {
            echo '<option value="' . esc_attr($option_value) . '" ' . selected($option_value, $value, false) . '>' . esc_html($option_text) . '</option>';
        }
        echo '</select>';

        echo '<p class="description">' . esc_html($args['description']) . '</p>';
    }

    /**
     * Add Cross-Origin Policy headers if enabled.
     *
     * @since    1.0.2
     */
    public function add_cross_origin_policy_headers() {
        $cross_origin_headers = array(
            'cross-origin-embedder-policy' => 'Cross-Origin-Embedder-Policy',
            'cross-origin-opener-policy' => 'Cross-Origin-Opener-Policy',
            'cross-origin-resource-policy' => 'Cross-Origin-Resource-Policy'
        );

        foreach ($cross_origin_headers as $option_key => $header_name) {
            $option_name_enabled = 'so_ssl_enable_' . str_replace('-', '_', $option_key);
            $option_name_value = 'so_ssl_' . str_replace('-', '_', $option_key) . '_value';

            $enabled = get_option($option_name_enabled, 0);

            if ($enabled) {
                $value = get_option($option_name_value, '');

                if (!empty($value)) {
                    header("$header_name: $value");
                }
            }
        }
    }

    /**
 * Check password strength on login
 *
 * @param WP_User $user The user object
 * @param string $password The password
 *
 * @return WP_User|WP_Error The user object or error
 */
public function check_password_strength($user, $password) {
    // If already errored, return the error
    if (is_wp_error($user)) {
        return $user;
    }

    // Only check if weak passwords are disabled
    if (!get_option('so_ssl_disable_weak_passwords', 0)) {
        return $user;
    }

    // Check password strength
    $strength = $this->get_password_strength($password, $user->user_login);

    // If the password is not strong (less than 3), return an error
    if ($strength < 3) {
        return new WP_Error(
            'weak_password',
            __('<strong>ERROR</strong>: Your password does not meet the minimum strength requirements. Please choose a stronger password that includes uppercase letters, lowercase letters, numbers, and special characters.', 'so-ssl')
        );
    }

    return $user;
}

/**
 * Get password strength with better categorization
 *
 * @param string $password The password
 * @param string $username The username
 *
 * @return array Array with 'score' (0-4) and 'level' (weak, medium, strong)
 */
public function get_password_strength($password, $username = '') {
    $score = 0;
    $feedback = array();

    // Initial checks for very weak passwords
    if (strlen($password) < 8) {
        return array('score' => 0, 'level' => 'very-weak', 'feedback' => array('Password must be at least 8 characters long'));
    }

    // Check if password contains username
    if (!empty($username) && stripos($password, $username) !== false) {
        return array('score' => 0, 'level' => 'very-weak', 'feedback' => array('Password cannot contain your username'));
    }

    // Check for common weak patterns
    $weak_patterns = array(
        '/^(.)\1+$/',           // All same character (aaaa, 1111)
        '/^(..)\1+$/',          // Repeated pairs (abab, 1212)
        '/^123456/',            // Sequential numbers
        '/^abcdef/',            // Sequential letters
        '/password/i',          // Contains "password"
        '/qwerty/i',            // Contains "qwerty"
        '/^(.{1,3})\1+$/',      // Short repeating patterns
    );

    foreach ($weak_patterns as $pattern) {
        if (preg_match($pattern, $password)) {
            return array('score' => 0, 'level' => 'very-weak', 'feedback' => array('Password uses a common weak pattern'));
        }
    }

    // Check complexity requirements
    $requirements = array(
        'uppercase' => preg_match('/[A-Z]/', $password),
        'lowercase' => preg_match('/[a-z]/', $password),
        'numbers'   => preg_match('/[0-9]/', $password),
        'symbols'   => preg_match('/[^A-Za-z0-9]/', $password),
    );

    $met_requirements = array_sum($requirements);
    $score = $met_requirements;

    // Length bonus
    if (strlen($password) >= 12) {
        $score += 1;
    }

    // Determine strength level
    if ($score <= 1) {
        $level = 'very-weak';
    } elseif ($score == 2) {
        $level = 'weak';
    } elseif ($score == 3) {
        $level = 'medium';
    } else {
        $level = 'strong';
    }

    // Generate feedback for missing requirements
    $missing = array();
    if (!$requirements['uppercase']) {$missing[] = 'uppercase letters (A-Z)';}
    if (!$requirements['lowercase']) {$missing[] = 'lowercase letters (a-z)';}
    if (!$requirements['numbers']) {$missing[] = 'numbers (0-9)';}
    if (!$requirements['symbols']) {$missing[] = 'special characters (!@#$%^&*...)';}

    $feedback = array();
    if (!empty($missing)) {
        $feedback[] = 'Add ' . implode(', ', $missing);
    }
    if (strlen($password) < 12) {
        $feedback[] = 'Consider using 12+ characters for better security';
    }

    return array('score' => $score, 'level' => $level, 'feedback' => $feedback);
}

/**
 * Add JavaScript to disable the weak password checkbox and enforce strong passwords
 * Fixed version to prevent duplicate elements and improve validation
 */
public function disable_weak_password_js() {
    ?>
    <script>
    jQuery(document).ready(function($) {
        // Ensure this only runs once
        if (window.soSslPasswordEnforced) {
            return;
        }
        window.soSslPasswordEnforced = true;

        function enforceStrongPassword() {
            // Remove weak password checkbox and related elements
            $('.pw-weak').remove();
            $('#pw-weak-text-message').remove();

            // Remove any existing password requirement messages to prevent duplicates
            $('.password-strength-message, .so-ssl-password-requirements').remove();

            // Override WordPress's password strength meter
            if (typeof wp !== 'undefined' && wp.passwordStrength) {
                // Store original function if we haven't already
                if (!wp.passwordStrength.originalCheck) {
                    wp.passwordStrength.originalCheck = wp.passwordStrength.checkPasswordStrength;
                }

                // Override the password strength check function
                wp.passwordStrength.checkPasswordStrength = function(password, blacklist, username, $strengthResult) {
                    // Clear previous classes and messages
                    $strengthResult.removeClass('short bad good strong');
                    $('.password-strength-message, .so-ssl-password-requirements').remove();

                    var submitButton = $('input[type="submit"], button[type="submit"]').not('[name="wp-admin-bar-search-submit"]');

                    if (password.length === 0) {
                        $strengthResult.addClass('short').html('<?php echo esc_js(__('Password is required', 'so-ssl')); ?>');
                        submitButton.prop('disabled', true);
                        return 0;
                    }

                    // Implement comprehensive strength check
                    var strength = calculatePasswordStrength(password, username);
                    var strengthText = '';
                    var strengthClass = '';
                    var isStrong = false;

                    // Determine strength level and styling
                    if (password.length < 8) {
                        strengthText = '<?php echo esc_js(__('Too short - Must be at least 8 characters', 'so-ssl')); ?>';
                        strengthClass = 'short';
                        isStrong = false;
                    } else if (strength < 3) {
                        strengthText = '<?php echo esc_js(__('Weak - Does not meet complexity requirements', 'so-ssl')); ?>';
                        strengthClass = 'bad';
                        isStrong = false;
                    } else if (strength === 3) {
                        strengthText = '<?php echo esc_js(__('Good - Meets most requirements', 'so-ssl')); ?>';
                        strengthClass = 'good';
                        isStrong = true; // Accept "good" passwords
                    } else {
                        strengthText = '<?php echo esc_js(__('Strong - Excellent password strength', 'so-ssl')); ?>';
                        strengthClass = 'strong';
                        isStrong = true;
                    }

                    // Apply styling and enable/disable submit button
                    $strengthResult.addClass(strengthClass).html(strengthText);
                    submitButton.prop('disabled', !isStrong);

                    // Add detailed requirements if password is not strong enough
                    if (!isStrong && !$('.so-ssl-password-requirements').length) {
                        var requirements = $('<div class="so-ssl-password-requirements" style="margin-top: 10px; padding: 10px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px;"></div>');
                        var reqList = $('<div style="font-size: 13px; color: #856404;"></div>');

                        reqList.html(
                            '<strong><?php echo esc_js(__('Password Requirements:', 'so-ssl')); ?></strong><br>' +
                            getRequirementText(password)
                        );

                        requirements.append(reqList);
                        $strengthResult.after(requirements);
                    }

                    return strength;
                };
            }

            // Function to calculate actual password strength
            function calculatePasswordStrength(password, username) {
                var strength = 0;

                // Length requirement (minimum 8 characters)
                if (password.length < 8) {
                    return 0;
                }

                // Check for different character types
                if (/[A-Z]/.test(password)) strength++; // Uppercase
                if (/[a-z]/.test(password)) strength++; // Lowercase
                if (/[0-9]/.test(password)) strength++; // Numbers
                if (/[^A-Za-z0-9]/.test(password)) strength++; // Special characters

                // Check if password contains username
                if (username && password.toLowerCase().includes(username.toLowerCase())) {
                    strength = Math.max(0, strength - 2);
                }

                // Check for common patterns that reduce strength
                if (/(.)\1{3,}/.test(password)) { // Repeated characters
                    strength = Math.max(0, strength - 1);
                }

                if (/^[0-9]+$/.test(password) || /^[a-zA-Z]+$/.test(password)) { // Only numbers or only letters
                    strength = Math.max(0, strength - 1);
                }

                return Math.min(4, strength);
            }

            // Function to generate requirement text with checkmarks
            function getRequirementText(password) {
                var requirements = [
                    {
                        text: '<?php echo esc_js(__('At least 8 characters long', 'so-ssl')); ?>',
                        met: password.length >= 8
                    },
                    {
                        text: '<?php echo esc_js(__('Contains uppercase letters (A-Z)', 'so-ssl')); ?>',
                        met: /[A-Z]/.test(password)
                    },
                    {
                        text: '<?php echo esc_js(__('Contains lowercase letters (a-z)', 'so-ssl')); ?>',
                        met: /[a-z]/.test(password)
                    },
                    {
                        text: '<?php echo esc_js(__('Contains numbers (0-9)', 'so-ssl')); ?>',
                        met: /[0-9]/.test(password)
                    },
                    {
                        text: '<?php echo esc_js(__('Contains special characters (!@#$%^&*...)', 'so-ssl')); ?>',
                        met: /[^A-Za-z0-9]/.test(password)
                    }
                ];

                var html = '';
                requirements.forEach(function(req) {
                    var icon = req.met ? '✓' : '✗';
                    var color = req.met ? '#28a745' : '#dc3545';
                    html += '• <span style="color: ' + color + ';">' + icon + ' ' + req.text + '</span><br>';
                });

                return html;
            }

            // Override the weak password confirmation
            window.pw_weak = false;

            // Prevent form submission if password is weak
            $('form').off('submit.sossl').on('submit.sossl', function(e) {
                var $passwordField = $('#pass1, #pass1-text, input[name="pass1"]');
                if ($passwordField.length && $passwordField.val()) {
                    var $strengthResult = $('#pass-strength-result');

                    // Allow submission only if password is good or strong
                    if (!$strengthResult.hasClass('good') && !$strengthResult.hasClass('strong')) {
                        e.preventDefault();

                        // Show error message if not already visible
                        if (!$('.so-ssl-submit-error').length) {
                            $strengthResult.after(
                                '<div class="so-ssl-submit-error" style="margin-top: 10px; padding: 10px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; color: #721c24;">' +
                                '<strong><?php echo esc_js(__('Cannot save:', 'so-ssl')); ?></strong> <?php echo esc_js(__('Please choose a stronger password that meets all requirements.', 'so-ssl')); ?>' +
                                '</div>'
                            );
                        }

                        return false;
                    } else {
                        // Remove error message if password is now strong
                        $('.so-ssl-submit-error').remove();
                    }
                }
            });
        }

        // Initial enforcement
        enforceStrongPassword();

        // Re-run after DOM changes (for dynamic content)
        var observer = new MutationObserver(function(mutations) {
            var shouldRerun = false;
            mutations.forEach(function(mutation) {
                if (mutation.addedNodes && mutation.addedNodes.length > 0) {
                    for (var i = 0; i < mutation.addedNodes.length; i++) {
                        var node = mutation.addedNodes[i];
                        if (node.nodeType === 1) { // Element node
                            if ($(node).find('#pass-strength-result, input[name="pass1"]').length > 0 ||
                                $(node).is('#pass-strength-result, input[name="pass1"]')) {
                                shouldRerun = true;
                                break;
                            }
                        }
                    }
                }
            });

            if (shouldRerun) {
                setTimeout(enforceStrongPassword, 100);
            }
        });

        // Start observing
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });

        // Handle password field changes
        $(document).on('input', '#pass1, #pass1-text, input[name="pass1"]', function() {
            // Trigger strength check
            if (typeof wp !== 'undefined' && wp.passwordStrength && wp.passwordStrength.checkPasswordStrength) {
                var $this = $(this);
                var password = $this.val();
                var username = $('#user_login').val() || $('#email').val() || '';
                var $strengthResult = $('#pass-strength-result');

                if ($strengthResult.length) {
                    wp.passwordStrength.checkPasswordStrength(password, [], username, $strengthResult);
                }
            }
        });
    });
    </script>

    <style>
    /* Additional styles for better appearance */
    .so-ssl-password-requirements {
        font-size: 13px !important;
        line-height: 1.4 !important;
    }

    .so-ssl-submit-error {
        animation: shake 0.5s ease-in-out;
    }

    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        25% { transform: translateX(-5px); }
        75% { transform: translateX(5px); }
    }

    /* Ensure password strength meter is visible */
    #pass-strength-result {
        padding: 8px !important;
        margin-top: 8px !important;
        border-radius: 4px !important;
        font-weight: 500 !important;
    }

    #pass-strength-result.short,
    #pass-strength-result.bad {
        background-color: #f8d7da !important;
        border: 1px solid #f5c6cb !important;
        color: #721c24 !important;
    }

    #pass-strength-result.good {
        background-color: #d1ecf1 !important;
        border: 1px solid #bee5eb !important;
        color: #0c5460 !important;
    }

    #pass-strength-result.strong {
        background-color: #d4edda !important;
        border: 1px solid #c3e6cb !important;
        color: #155724 !important;
    }
    </style>
    <?php
}

/**
 * Enforce strong password on user profile update and registration
 *
 * @param WP_Error $errors Error object
 * @param bool $update Whether this is an existing user update
 * @param stdClass|WP_User|null $user User object
 *
 * @return WP_Error Updated error object
 */
public function enforce_strong_password($errors, $update, $user = null) {
    // If weak passwords aren't disabled, return original errors
    if (!get_option('so_ssl_disable_weak_passwords', 0)) {
        return $errors;
    }

    // Initialize WP_Error if null
    if (!is_wp_error($errors)) {
        $errors = new WP_Error();
    }

    // Get the password
    $password = '';
    if (
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        isset(
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $_POST['pass1']
        )
     &&
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        !empty($_POST['pass1'])
     ) {
        $password =
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        sanitize_text_field(wp_unslash($_POST['pass1'])
        );
    } elseif
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        (isset($_POST['password'])
        &&
        !empty(
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $_POST['password'])
    ) {
        // Registration form password field
        $password =
         // phpcs:ignore WordPress.Security.NonceVerification.Missing
         sanitize_text_field(wp_unslash($_POST['password'])
        );
    }

    // If no password provided, return (WordPress will handle required password validation)
    if (empty($password)) {
        return $errors;
    }

    // Get username for strength check
    $username = '';
    if ($user instanceof WP_User) {
        $username = $user->user_login;
    } elseif
     // phpcs:ignore WordPress.Security.NonceVerification.Missing
    (isset($_POST['user_login'])
    ) {
        $username =
         // phpcs:ignore WordPress.Security.NonceVerification.Missing
        sanitize_text_field(wp_unslash($_POST['user_login'])
        );
    }

    // Check password strength
    $strength_result = $this->get_password_strength($password, $username);

    // Block weak and medium passwords (require strong passwords only)
    if (in_array($strength_result['level'], array('very-weak', 'weak', 'medium'))) {
        $error_message = '<strong>Error</strong>: Password does not meet security requirements.<br/>';
        $error_message .= '<strong>Password Requirements:</strong><br/>';
        $error_message .= '• At least 8 characters long<br/>';
        $error_message .= '• Include uppercase letters (A-Z)<br/>';
        $error_message .= '• Include lowercase letters (a-z)<br/>';
        $error_message .= '• Include numbers (0-9)<br/>';
        $error_message .= '• Include special characters (!@#$%^&*...)<br/>';

        if (!empty($strength_result['feedback'])) {
            $error_message .= '<br/><strong>Suggestions:</strong> ' . implode(', ', $strength_result['feedback']);
        }

        $errors->add('pass', $error_message);
    }

    return $errors;
}

/**
 * Validate password reset
 *
 * @param WP_Error $errors Error object
 * @param WP_User $user User object
 *
 * @return WP_Error
 */
public function validate_password_reset($errors, $user) {
    // If weak passwords aren't disabled, return original errors
    if (!get_option('so_ssl_disable_weak_passwords', 0)) {
        return $errors;
    }

    // Initialize WP_Error if null
    if (!is_wp_error($errors)) {
        $errors = new WP_Error();
    }

    // Check if password is set
    if
     // phpcs:ignore WordPress.Security.NonceVerification.Missing
    (isset($_POST['pass1'])
     &&
      // phpcs:ignore WordPress.Security.NonceVerification.Missing
     sanitize_text_field( wp_unslash(!empty($_POST['pass1'])))
     ) {
        $password =
         // phpcs:ignore WordPress.Security.NonceVerification.Missing
        sanitize_text_field(wp_unslash($_POST['pass1'])
        );
        $strength_result = $this->get_password_strength($password, $user->user_login);

        if (in_array($strength_result['level'], array('very-weak', 'weak', 'medium'))) {
            $error_message = '<strong>Error</strong>: Password does not meet security requirements.<br/>';
            $error_message .= '<strong>Password Requirements:</strong><br/>';
            $error_message .= '• At least 8 characters long<br/>';
            $error_message .= '• Include uppercase letters (A-Z)<br/>';
            $error_message .= '• Include lowercase letters (a-z)<br/>';
            $error_message .= '• Include numbers (0-9)<br/>';
            $error_message .= '• Include special characters (!@#$%^&*...)<br/>';

            if (!empty($strength_result['feedback'])) {
                $error_message .= '<br/><strong>Suggestions:</strong> ' . implode(', ', $strength_result['feedback']);
            }

            $errors->add('pass', $error_message);
        }
    }

    return $errors;
}

	/**
	 * Enhance the admin tab system to maintain active tab after form submission
	 */
	function enhance_admin_tabs() {
		// Find the display_options_page() method in class-so-ssl.php

		// JavaScript to handle tab state
		?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Get active tab - check URL hash first, then saved value, then default
                let activeTab = '<?php echo esc_js(get_option('so_ssl_active_tab', 'ssl-settings')); ?>';
                const urlHash = window.location.hash.substring(1);

                if (urlHash && $('#' + urlHash).length) {
                    activeTab = urlHash;
                }

                // Initially hide all tabs
                $('.settings-tab').hide();
                $('.nav-tab').removeClass('nav-tab-active');

                // Activate the appropriate tab
                if ($('#' + activeTab).length) {
                    $('#' + activeTab).show();
                    $('.nav-tab[href="#' + activeTab + '"]').addClass('nav-tab-active');
                    $('#active_tab').val(activeTab);
                } else {
                    // Fallback to first tab if the saved tab doesn't exist
                    $('#ssl-settings').show();
                    $('.nav-tab[href="#ssl-settings"]').addClass('nav-tab-active');
                    $('#active_tab').val('ssl-settings');
                    activeTab = 'ssl-settings';
                }

                // Handle tab navigation
                $('.nav-tab').on('click', function(e) {
                    e.preventDefault();

                    // Get target tab
                    const tabId = $(this).attr('href').substring(1);

                    // If already on this tab, do nothing
                    if ($(this).hasClass('nav-tab-active')) {
                        return;
                    }

                    // Check for unsaved changes
                    if (typeof formModified !== 'undefined' && formModified) {
                        if (!confirm('Your changes are not saved. Do you want to continue?')) {
                            return false;
                        }
                    }

                    // Update tabs
                    $('.nav-tab').removeClass('nav-tab-active');
                    $('.settings-tab').hide();

                    $(this).addClass('nav-tab-active');
                    $('#' + tabId).show();

                    // Update hidden input value - CRITICAL for form submission
                    $('#active_tab').val(tabId);

                    // Update URL hash
                    if (history.pushState) {
                        history.pushState(null, null, '#' + tabId);
                    } else {
                        window.location.hash = tabId;
                    }
                });

                // Save active tab on form submission
                $('form').on('submit', function() {
                    // Create a new hidden input field to ensure the active tab is saved
                    // This is backup in case the #active_tab field is somehow lost
                    $(this).append('<input type="hidden" name="so_ssl_active_tab" value="' + $('#active_tab').val() + '">');
                });
            });
        </script>
		<?php
	}

 /**
 * User Sessions section description.
 */
public function user_sessions_section_callback() {
    echo '<p>' . esc_html__('Configure user session management to enhance security by controlling and monitoring active sessions.', 'so-ssl') . '</p>';
}

    /**
     * Enable user sessions management field callback.
     */
    public function enable_user_sessions_callback() {
        $enable_user_sessions = get_option('so_ssl_enable_user_sessions', 0);

        echo '<label for="so_ssl_enable_user_sessions">';
        echo '<input type="checkbox" id="so_ssl_enable_user_sessions" name="so_ssl_enable_user_sessions" value="1" ' . checked(1, $enable_user_sessions, false) . '/>';
        echo esc_html__('Enable user sessions management', 'so-ssl');
        echo '</label>';
        echo '<p class="description">' . esc_html__('Adds the ability to view and manage user login sessions across devices.', 'so-ssl') . '</p>';
  }

    /**
     * Login limit section description.
     */
    public function login_limit_section_callback() {
        echo '<div class="notice notice-warning inline"><p>'.esc_html__('Warning:', 'so-ssl') . esc_html__(' Please have a back up admin user (administration rights) before enabling this feature. It can lock you out for a period of time or blacklist your I.P.', 'so-ssl') . '</p></div>';
    }

    /**
     * Enable login limit field callback.
     */
    public function enable_login_limit_callback() {
        $enable_login_limit = get_option('so_ssl_enable_login_limit', 0);

        echo '<label for="so_ssl_enable_login_limit">';
        echo '<input type="checkbox" id="so_ssl_enable_login_limit" name="so_ssl_enable_login_limit" value="1" ' . checked(1, $enable_login_limit, false) . '/>';
        echo esc_html__('Enable login attempt limiting', 'so-ssl');
        echo '</label>';
        echo '<p class="description">' . esc_html__('Limits the number of failed login attempts allowed per IP address.', 'so-ssl') . '</p>';

        /* translators: %s: URL to Login Security page */
        esc_html__('For detailed settings and statistics, visit the %s page.', 'so-ssl') .
        esc_html__('<a href="', 'so-ssl') .
        esc_url(admin_url('options-general.php?page=class-so-ssl-login-limit')) . '">' .
        esc_html__('Login Security', 'so-ssl') . '</a>';
        }

}
