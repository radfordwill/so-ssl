<?php
  /**
   * The core plugin class.
   *
   * @since      1.1.0
   * @package    So_SSL
   */

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
        // Only load if 2FA is enabled
        if (get_option('so_ssl_enable_2fa', 0)) {
            // Load session handler first
            require_once SO_SSL_PATH . 'includes/so-ssl-session-handler.php';

            // Load TOTP implementation
            require_once SO_SSL_PATH . 'includes/totp.php';

            // Load 2FA functionality
            require_once SO_SSL_PATH . 'includes/so-ssl-two-factor.php';
        }
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
            add_filter('wp_is_application_passwords_available', '__return_false'); // Disable application passwords
            add_action('login_enqueue_scripts', array($this, 'disable_weak_password_js'));
            add_action('admin_enqueue_scripts', array($this, 'disable_weak_password_js'));
            add_action('wp_authenticate_user', array($this, 'check_password_strength'), 10, 2);
            add_action('user_profile_update_errors', array($this, 'enforce_strong_password'), 10, 3);
            add_action('validate_password_reset', array($this, 'validate_password_reset'), 10, 2);
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
        add_options_page(
            /* translators: %s: Plugin Settings*/
            __('So SSL Settings', 'so-ssl'),
            /* translators: %s: Plugin Title */
            __('So SSL', 'so-ssl'),
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
        }
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        // Only load on plugin pages
        if (strpos($hook, 'so-ssl') !== false || $hook === 'settings_page_so-ssl') {
            wp_enqueue_script('so-ssl-admin', SO_SSL_URL . 'assets/js/so-ssl-admin.js', array('jquery'), SO_SSL_VERSION, true);
        }
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
                    <?php
                    do_settings_sections('so-ssl-2fa');
                    ?>

                    <?php if (get_option('so_ssl_enable_2fa', 0)): ?>
                    <div class="so-ssl-notice">
                        <p><strong><?php esc_html_e('Next Steps:', 'so-ssl'); ?></strong></p>
                        <ol>
                            <li><?php esc_html_e('Enable 2FA for specific user roles above', 'so-ssl'); ?></li>
                            <li><?php esc_html_e('Users will need to configure 2FA in their profile settings', 'so-ssl'); ?></li>
                            <li><?php esc_html_e('Users should generate backup codes for emergency access', 'so-ssl'); ?></li>
                        </ol>
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
                    <div class="so-ssl-notice">
                        <p>
                            <?php
                            printf(
                                /* translators: %s: URL to Login Security page */
                                esc_html__('For detailed settings and statistics, visit the %s page.', 'so-ssl'),
                                '<a href="' . esc_url(admin_url('options-general.php?page=so-ssl-login-limit')) . '">' . esc_html__('Login Security', 'so-ssl') . '</a>'
                            );
                            ?>
                        </p>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Add hidden input for active tab -->
                <input type="hidden" name="active_tab" id="active_tab" value="ssl-settings">

                <?php submit_button(); ?>
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
            </div>
        </div>

        <!-- Inline script to ensure tabs work correctly -->
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Initially hide all tabs except the first one
            $('.settings-tab').hide();
            $('#ssl-settings').show();

            // Handle tab clicks
            $('.nav-tab').on('click', function(e) {
                e.preventDefault();

                // Get the tab ID from href attribute
                const tabId = $(this).attr('href').substring(1);

                // Remove active class from all tabs
                $('.nav-tab').removeClass('nav-tab-active');
                $('.settings-tab').hide();

                // Add active class to clicked tab
                $(this).addClass('nav-tab-active');
                $('#' + tabId).show();

                // Update hidden input
                $('#active_tab').val(tabId);
            });

            // Check URL hash for tab on page load
            let activeTab = window.location.hash.substring(1);
            if (activeTab && $('#' + activeTab).length) {
                $('.nav-tab').removeClass('nav-tab-active');
                $('.settings-tab').hide();

                $('.nav-tab[href="#' + activeTab + '"]').addClass('nav-tab-active');
                $('#' + activeTab).show();

                // Update hidden input
                $('#active_tab').val(activeTab);
            }
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
    echo '<p class="description">' . esc_html__('Disable the "confirm use of weak password" checkbox and prevent users from setting weak passwords.', 'so-ssl') . '</p>';
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

    echo '<select multiple id="so_ssl_2fa_user_roles" name="so_ssl_2fa_user_roles[]" class="regular-text">';
    foreach ($roles as $role_value => $role_name) {
        $selected = in_array($role_value, $selected_roles) ? 'selected="selected"' : '';
        echo '<p class="description">' . esc_html__('Warning: Only enable this if you have a valid SSL certificate installed.', 'so-ssl') . '</p>';
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
         * Content Security Policy section description.
         *
         * @since    1.0.2
         */
        public function csp_full_section_callback() {
            echo '<p>' . esc_html__('Content Security Policy (CSP) is an added layer of security that helps to detect and mitigate certain types of attacks, including Cross-Site Scripting (XSS) and data injection attacks.', 'so-ssl') . '</p>';
            echo '<p>' . esc_html__('It is recommended to first enable CSP in "Report-Only" mode to ensure it does not break your site functionality.', 'so-ssl') . '</p>';
            echo '<div class="notice notice-warning inline"><p>' . esc_html__('<strong>Warning:</strong> Incorrect CSP settings can break functionality on your site. Make sure to test thoroughly.', 'so-ssl') . '</p></div>';
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
         * @since    1.0.2
         * @param    array    $args    The arguments passed to the callback.
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
     * @since    1.0.2
     * @param    array    $args    The arguments passed to the callback.
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
     * Cross-Origin Policy section description.
     *
     * @since    1.0.2
     */
    public function cross_origin_policy_section_callback() {
        echo '<p>' . esc_html__('Cross-Origin policies control how your site\'s resources can be used by other sites and how your site can interact with other sites.', 'so-ssl') . '</p>';
        echo '<div class="notice notice-warning inline"><p>' . esc_html__('<strong>Warning:</strong> These settings can break cross-origin functionality. Test thoroughly before enabling in production.', 'so-ssl') . '</p></div>';
    }

    /**
     * Cross-Origin Policy field callback.
     *
     * @since    1.0.2
     * @param    array    $args    The arguments passed to the callback.
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
     * Add JavaScript to disable the weak password checkbox
     *
     * @since    1.3.0
     */
    public function disable_weak_password_js() {
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Find and disable the confirm weak password checkbox
            var weakPwCheckbox = document.querySelector('.pw-weak');
            if (weakPwCheckbox) {
                weakPwCheckbox.style.display = 'none';
            }

            // Hide the weak password confirmation message
            var weakPwConfirm = document.getElementById('pw-weak-text-message');
            if (weakPwConfirm) {
                weakPwConfirm.style.display = 'none';
            }

            // Override the weak password confirmation JS function
            if (typeof wp !== 'undefined' && wp.passwordStrength && wp.passwordStrength.userInputDisallowedList) {
                // Store the original checkPasswordStrength function
                var originalCheckPasswordStrength = wp.passwordStrength.checkPasswordStrength;

                // Override the function
                wp.passwordStrength.checkPasswordStrength = function(password, blacklist, username, strengthResult) {
                    var result = originalCheckPasswordStrength(password, blacklist, username, strengthResult);

                    // If the password is weak (2 or less), disable the submit button
                    if (result < 3) {
                        var submitButton = document.querySelector('input[type="submit"]');
                        if (submitButton) {
                            submitButton.disabled = true;
                        }

                        // Add a message about strong password requirement
                        var strengthMeter = document.querySelector('.password-strength-meter');
                        if (strengthMeter) {
                            var messageDiv = document.createElement('div');
                            messageDiv.className = 'strong-password-message';
                            messageDiv.style.color = '#dc3232';
                            messageDiv.style.marginTop = '5px';
                            messageDiv.textContent = '<?php echo esc_js(esc_html__('Strong password is required. Please choose a stronger password.', 'so-ssl')); ?>';

                            // Remove any existing message before adding a new one
                            var existingMessage = document.querySelector('.strong-password-message');
                            if (existingMessage) {
                                existingMessage.remove();
                            }

                            strengthMeter.parentNode.appendChild(messageDiv);
                        }
                    } else {
                        var submitButton = document.querySelector('input[type="submit"]');
                        if (submitButton) {
                            submitButton.disabled = false;
                        }

                        // Remove the message if password is strong enough
                        var existingMessage = document.querySelector('.strong-password-message');
                        if (existingMessage) {
                            existingMessage.remove();
                        }
                    }

                    return result;
                };
            }
        });
        </script>
        <?php
    }

    /**
     * Check password strength on login
     *
     * @since    1.3.0
     * @param WP_User $user The user object
     * @param string $password The password
     * @return WP_User|WP_Error The user object or error
     */
    public function check_password_strength($user, $password) {
        // If already errored, return the error
        if (is_wp_error($user)) {
            return $user;
        }

        // Skip for password reset or non-login actions
        // For the WordPress login form, verify the login nonce if it exists
        if (isset($_POST['log']) && isset($_POST['pwd'])) {
            // Check if this is a standard WordPress login form with a nonce
            if (isset($_POST['_wpnonce'])) {
                $nonce = sanitize_text_field(wp_unslash($_POST['_wpnonce']));
                if (!wp_verify_nonce($nonce, 'wp-login')) {
                    return new WP_Error('invalid_nonce', __('<strong>ERROR</strong>: Security verification failed.', 'so-ssl'));
                }
            } else {
                // If no nonce is present, we're in a different login flow (e.g., XML-RPC, custom form)
                // We can still proceed with password checking in these cases
                // The WordPress authentication process has its own security checks
            }

            // Check password strength
            $strength = $this->get_password_strength($password, $user->user_login);

            // If the password is not strong (less than 4), return an error
            if ($strength < 4) {
                return new WP_Error('weak_password', esc_html__('<strong>ERROR</strong>: Your password is not strong enough. Please choose a stronger password with uppercase letters, lowercase letters, numbers, and special characters.', 'so-ssl'));
            }
        }

        return $user;
    }

    /**
     * Enforce strong password on user profile update
     *
     * @since    1.3.0
     * @param WP_Error $errors The error object
     * @param bool $update Whether this is an update
     * @param WP_User $user The user object
     */
    public function enforce_strong_password($errors, $update, $user) {
        // Profile updates are already verified by WordPress with the nonce 'update-user_'.$user_id
        // We can check for this nonce but it's redundant since WordPress already does
        if (isset($_POST['pass1']) && !empty($_POST['pass1'])) {
            // Verify nonce - redundant but required for static analysis
            if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'update-user_' . $user->ID)) {
                // WordPress already handles this, so we don't need to add an error
                return;
            }

            // Sanitize password - though password input is already sanitized by WordPress
            $password = isset($_POST['pass1']) ? sanitize_text_field(wp_unslash($_POST['pass1'])) : '';

            $strength = $this->get_password_strength($password, $user->user_login);

            // If the password is not strong (less than 4), add an error
            if ($strength < 4) {
                $errors->add('weak_password', esc_html__('<strong>ERROR</strong>: Please choose a stronger password. The password must include uppercase letters, lowercase letters, numbers, and special characters.', 'so-ssl'));
            }
        }
    }

    /**
     * Validate password strength on password reset
     *
     * @since    1.3.0
     * @param WP_Error $errors The error object
     * @param WP_User $user The user object
     */
    public function validate_password_reset($errors, $user) {
        // Password reset form already verifies a nonce
        // Check for the appropriate nonce
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'reset-password')) {
            // WordPress already handles this
            return;
        }

        if (isset($_POST['pass1']) && !empty($_POST['pass1'])) {
            // Sanitize password
            $password = isset($_POST['pass1']) ? sanitize_text_field(wp_unslash($_POST['pass1'])) : '';

            $strength = $this->get_password_strength($password, $user->user_login);

            // If the password is not strong (less than 4), add an error
            if ($strength < 4) {
                $errors->add('weak_password', esc_html__('<strong>ERROR</strong>: Please choose a stronger password. The password must include uppercase letters, lowercase letters, numbers, and special characters.', 'so-ssl'));
            }
        }
    }

    /**
     * Get password strength
     *
     * @since    1.3.0
     * @param string $password The password
     * @param string $username The username
     * @return int The password strength (0-4)
     */
    public function get_password_strength($password, $username) {
        // Check password length
        if (strlen($password) < 8) {
            return 1; // Very weak
        }

        // Check if password contains username
        if (strpos(strtolower($password), strtolower($username)) !== false) {
            return 1; // Very weak
        }

        // Calculate password strength
        $strength = 0;

        // Has lowercase letters
        if (preg_match('/[a-z]/', $password)) {
            $strength++;
        }

        // Has uppercase letters
        if (preg_match('/[A-Z]/', $password)) {
            $strength++;
        }

        // Has numbers
        if (preg_match('/[0-9]/', $password)) {
            $strength++;
        }

        // Has special characters
        if (preg_match('/[^a-zA-Z0-9]/', $password)) {
            $strength++;
        }

        return $strength;
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
        echo '<p>' . esc_html__('Configure login attempt limiting to protect your site from brute force attacks.', 'so-ssl') . '</p>';
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
        esc_url(admin_url('options-general.php?page=so-ssl-login-limit')) . '">' .
        esc_html__('Login Security', 'so-ssl') . '</a>';
        }
}
