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
            register_setting(
                'so_ssl_options',
                'so_ssl_csp_' . str_replace('-', '_', $directive),
                array(
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_textarea_field',
                    'default' => $default_value,
                )
            );
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
    }<?php
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
        // For future use if additional classes are needed
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
    }

    /**
     * Add admin menu for plugin settings.
     *
     * @since    1.0.2
     */
    public function add_admin_menu() {
        add_options_page(
            __('So SSL Settings', 'so-ssl'),
            __('So SSL', 'so-ssl'),
            'manage_options',
            'so-ssl',
            array($this, 'display_options_page')
        );
    }

    /**
     * Plugin settings page content.
     *
     * @since    1.0.2
     */
    public function display_options_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="nav-tab-wrapper">
                <a href="#ssl-settings" class="nav-tab nav-tab-active" data-tab="ssl-settings"><?php _e('SSL Settings', 'so-ssl'); ?></a>
                <a href="#content-security" class="nav-tab" data-tab="content-security"><?php _e('Content Security', 'so-ssl'); ?></a>
                <a href="#browser-features" class="nav-tab" data-tab="browser-features"><?php _e('Browser Features', 'so-ssl'); ?></a>
                <a href="#cross-origin" class="nav-tab" data-tab="cross-origin"><?php _e('Cross-Origin', 'so-ssl'); ?></a>
            </div>
            
            <form action="options.php" method="post">
                <?php settings_fields('so_ssl_options'); ?>
                
                <!-- SSL Settings Tab -->
                <div id="ssl-settings" class="settings-tab active">
                    <h2><?php _e('SSL & Basic Security Settings', 'so-ssl'); ?></h2>
                    <?php 
                    do_settings_sections('so-ssl-ssl');
                    ?>
                </div>
                
                <!-- Content Security Tab -->
                <div id="content-security" class="settings-tab">
                    <h2><?php _e('Content Security Policies', 'so-ssl'); ?></h2>
                    <?php 
                    do_settings_sections('so-ssl-csp');
                    do_settings_sections('so-ssl-referrer');
                    ?>
                </div>
                
                <!-- Browser Features Tab -->
                <div id="browser-features" class="settings-tab">
                    <h2><?php _e('Browser Feature Controls', 'so-ssl'); ?></h2>
                    <?php 
                    do_settings_sections('so-ssl-permissions');
                    ?>
                </div>
                
                <!-- Cross-Origin Tab -->
                <div id="cross-origin" class="settings-tab">
                    <h2><?php _e('Cross-Origin Security Controls', 'so-ssl'); ?></h2>
                    <?php 
                    do_settings_sections('so-ssl-cross-origin');
                    do_settings_sections('so-ssl-xframe');
                    do_settings_sections('so-ssl-csp-frame');
                    ?>
                </div>
                
                <?php submit_button(); ?>
            </form>
        </div>
        
        <style>
            .settings-tab {
                display: none;
                margin-top: 15px;
            }
            .settings-tab.active {
                display: block;
            }
            .so-ssl-section-title {
                border-bottom: 1px solid #ccc;
                padding-bottom: 10px;
                margin-top: 20px;
            }
            .form-table th {
                width: 250px;
            }
        </style>
        
        <script>
            jQuery(document).ready(function($) {
                // Tab navigation
                $('.nav-tab').on('click', function(e) {
                    e.preventDefault();
                    
                    // Remove active class from all tabs
                    $('.nav-tab').removeClass('nav-tab-active');
                    $('.settings-tab').removeClass('active');
                    
                    // Add active class to clicked tab
                    $(this).addClass('nav-tab-active');
                    
                    // Show corresponding tab content
                    $('#' + $(this).data('tab')).addClass('active');
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
     * SSL section description.
     *
     * @since    1.0.2
     */
    public function ssl_section_callback() {
        echo '<p>' . __('Configure SSL settings for your website.', 'so-ssl') . '</p>';
        
        // Display current SSL status
        if (is_ssl()) {
            echo '<div class="notice notice-success inline"><p>' . __('Your site is currently using SSL/HTTPS.', 'so-ssl') . '</p></div>';
        } else {
            echo '<div class="notice notice-warning inline"><p>' . __('Your site is not using SSL/HTTPS. Enabling force SSL without having a valid SSL certificate may make your site inaccessible.', 'so-ssl') . '</p></div>';
        }
    }

    /**
     * HSTS section description.
     *
     * @since    1.0.2
     */
    public function hsts_section_callback() {
        echo '<p>' . __('HTTP Strict Transport Security (HSTS) instructs browsers to only access your site over HTTPS, even if the user enters or clicks on a plain HTTP URL. This helps protect against SSL stripping attacks.', 'so-ssl') . '</p>';
        echo '<div class="notice notice-warning inline"><p>' . __('<strong>Warning:</strong> Only enable HSTS if you are certain your site will always use HTTPS. Once a browser receives this header, it will not allow access to your site over HTTP until the max-age expires, even if you disable SSL later.', 'so-ssl') . '</p></div>';
    }

    /**
     * X-Frame-Options section description.
     *
     * @since    1.0.2
     */
    public function xframe_section_callback() {
        echo '<p>' . __('X-Frame-Options header controls whether your site can be loaded in an iframe. This helps prevent clickjacking attacks where an attacker might embed your site in their own malicious site.', 'so-ssl') . '</p>';
    }

    /**
     * CSP section description.
     *
     * @since    1.0.2
     */
    public function csp_section_callback() {
        echo '<p>' . __('Content Security Policy (CSP) with frame-ancestors directive is a modern replacement for X-Frame-Options. It provides more flexibility for controlling which domains can embed your site in an iframe.', 'so-ssl') . '</p>';
        echo '<p>' . __('Note: You can use both X-Frame-Options and CSP frame-ancestors for better browser compatibility.', 'so-ssl') . '</p>';
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
        echo __('Force all traffic to use HTTPS/SSL', 'so-ssl');
        echo '</label>';
        echo '<p class="description">' . __('Warning: Only enable this if you have a valid SSL certificate installed.', 'so-ssl') . '</p>';
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
        echo __('Enable HTTP Strict Transport Security (HSTS)', 'so-ssl');
        echo '</label>';
        echo '<p class="description">' . __('Adds the Strict-Transport-Security header to tell browsers to always use HTTPS for this domain.', 'so-ssl') . '</p>';
    }

    /**
     * HSTS Max Age field callback.
     *
     * @since    1.0.2
     */
    public function hsts_max_age_callback() {
        $max_age = get_option('so_ssl_hsts_max_age', 31536000);
        
        echo '<select id="so_ssl_hsts_max_age" name="so_ssl_hsts_max_age">';
        echo '<option value="86400" ' . selected(86400, $max_age, false) . '>' . __('1 Day (86400 seconds)', 'so-ssl') . '</option>';
        echo '<option value="604800" ' . selected(604800, $max_age, false) . '>' . __('1 Week (604800 seconds)', 'so-ssl') . '</option>';
        echo '<option value="2592000" ' . selected(2592000, $max_age, false) . '>' . __('1 Month (2592000 seconds)', 'so-ssl') . '</option>';
        echo '<option value="31536000" ' . selected(31536000, $max_age, false) . '>' . __('1 Year (31536000 seconds) - Recommended', 'so-ssl') . '</option>';
        echo '<option value="63072000" ' . selected(63072000, $max_age, false) . '>' . __('2 Years (63072000 seconds)', 'so-ssl') . '</option>';
        echo '</select>';
        echo '<p class="description">' . __('How long browsers should remember that this site is only to be accessed using HTTPS.', 'so-ssl') . '</p>';
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
        echo __('Apply HSTS to all subdomains', 'so-ssl');
        echo '</label>';
        echo '<p class="description">' . __('Warning: Only enable if ALL subdomains have SSL certificates!', 'so-ssl') . '</p>';
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
        echo __('Add preload flag', 'so-ssl');
        echo '</label>';
        echo '<p class="description">' . sprintf(
            __('This is necessary for submitting to the <a href="%s" target="_blank">HSTS Preload List</a>. Only enable if you intend to submit your site to this list.', 'so-ssl'),
            'https://hstspreload.org/'
        ) . '</p>';
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
        echo __('Enable X-Frame-Options header', 'so-ssl');
        echo '</label>';
        echo '<p class="description">' . __('Controls whether your site can be loaded in an iframe (recommended for security).', 'so-ssl') . '</p>';
    }

    /**
     * X-Frame-Options value field callback.
     *
     * @since    1.0.2
     */
    public function xframe_option_callback() {
        $xframe_option = get_option('so_ssl_xframe_option', 'sameorigin');
        
        echo '<select id="so_ssl_xframe_option" name="so_ssl_xframe_option">';
        echo '<option value="deny" ' . selected('deny', $xframe_option, false) . '>' . __('DENY - Prevents any site from loading this site in an iframe', 'so-ssl') . '</option>';
        echo '<option value="sameorigin" ' . selected('sameorigin', $xframe_option, false) . '>' . __('SAMEORIGIN - Only allow same site to frame content (recommended)', 'so-ssl') . '</option>';
        echo '<option value="allowfrom" ' . selected('allowfrom', $xframe_option, false) . '>' . __('ALLOW-FROM - Allow a specific domain to frame content', 'so-ssl') . '</option>';
        echo '</select>';
        echo '<p class="description">' . __('Determines which sites (if any) can load your site in an iframe.', 'so-ssl') . '</p>';
    }

    /**
     * X-Frame-Options Allow-From domain field callback.
     *
     * @since    1.0.2
     */
    public function xframe_allow_from_callback() {
        $allow_from = get_option('so_ssl_xframe_allow_from', '');
        
        echo '<input type="url" id="so_ssl_xframe_allow_from" name="so_ssl_xframe_allow_from" value="' . esc_attr($allow_from) . '" class="regular-text" placeholder="https://example.com" />';
        echo '<p class="description">' . __('Enter the full domain that is allowed to load your site in an iframe (only used with ALLOW-FROM option).', 'so-ssl') . '</p>';
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
        echo __('Enable Content Security Policy: frame-ancestors directive', 'so-ssl');
        echo '</label>';
        echo '<p class="description">' . __('Adds the Content-Security-Policy header with frame-ancestors directive to control iframe embedding.', 'so-ssl') . '</p>';
    }

    /**
     * CSP Frame-Ancestors value field callback.
     *
     * @since    1.0.2
     */
    public function csp_frame_ancestors_option_callback() {
        $csp_option = get_option('so_ssl_csp_frame_ancestors_option', 'none');
        
        echo '<select id="so_ssl_csp_frame_ancestors_option" name="so_ssl_csp_frame_ancestors_option">';
        echo '<option value="none" ' . selected('none', $csp_option, false) . '>' . __('\'none\' - No site can embed your content (most restrictive)', 'so-ssl') . '</option>';
        echo '<option value="self" ' . selected('self', $csp_option, false) . '>' . __('\'self\' - Only your own site can embed your content', 'so-ssl') . '</option>';
        echo '<option value="custom" ' . selected('custom', $csp_option, false) . '>' . __('Custom - Specify allowed domains', 'so-ssl') . '</option>';
        echo '</select>';
        echo '<p class="description">' . __('Determines which sites (if any) can embed your site in an iframe.', 'so-ssl') . '</p>';
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
        echo __('Include \'self\' in allowed domains', 'so-ssl');
        echo '</label>';
        echo '<p class="description">' . __('Allow your own site to embed your content when using custom domains.', 'so-ssl') . '</p>';
    }

    /**
     * CSP Frame-Ancestors domains field callback.
     *
     * @since    1.0.2
     */
    public function csp_frame_ancestors_domains_callback() {
        $domains = get_option('so_ssl_csp_frame_ancestors_domains', '');
        
        echo '<textarea id="so_ssl_csp_frame_ancestors_domains" name="so_ssl_csp_frame_ancestors_domains" rows="5" class="large-text" placeholder="https://example.com">' . esc_textarea($domains) . '</textarea>';
        echo '<p class="description">' . __('Enter domains that are allowed to embed your site, one per line. Example: https://example.com', 'so-ssl') . '</p>';
        echo '<p class="description">' . __('You can also use wildcards like *.example.com to allow all subdomains.', 'so-ssl') . '</p>';
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
            
            // On change
            $('#so_ssl_xframe_option').on('change', function() {
                toggleAllowFrom();
            });
            
            $('#so_ssl_csp_frame_ancestors_option').on('change', function() {
                toggleCSPCustomFields();
            });
        });
        </script>
        <?php
    }
        });
        </script>
        <?php
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
        <?php
    }
            // On change
            $('#so_ssl_xframe_option').on('change', function() {
                toggleAllowFrom();
            });
            
            $('#so_ssl_csp_frame_ancestors_option').on('change', function() {
                toggleCSPCustomFields();
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
            // Get current URL
            $current_url = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
            $ssl_url = str_replace('http://', 'https://', $current_url);
            
            // Redirect to HTTPS
            wp_redirect($ssl_url, 301);
            exit;
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
        echo '<p>' . __('The Referrer-Policy HTTP header controls how much referrer information should be included with requests.', 'so-ssl') . '</p>';
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
        echo __('Enable Referrer Policy header', 'so-ssl');
        echo '</label>';
        echo '<p class="description">' . __('Adds the Referrer-Policy header to control what information is sent in the Referer header.', 'so-ssl') . '</p>';
    }
    
    /**
     * Referrer Policy value field callback.
     *
     * @since    1.0.2
     */
    public function referrer_policy_option_callback() {
        $referrer_policy = get_option('so_ssl_referrer_policy_option', 'strict-origin-when-cross-origin');
        
        echo '<select id="so_ssl_referrer_policy_option" name="so_ssl_referrer_policy_option">';
        echo '<option value="no-referrer" ' . selected('no-referrer', $referrer_policy, false) . '>' . __('no-referrer - No referrer information is sent', 'so-ssl') . '</option>';
        echo '<option value="no-referrer-when-downgrade" ' . selected('no-referrer-when-downgrade', $referrer_policy, false) . '>' . __('no-referrer-when-downgrade - No referrer when downgrading (e.g., HTTPS→HTTP)', 'so-ssl') . '</option>';
        echo '<option value="origin" ' . selected('origin', $referrer_policy, false) . '>' . __('origin - Only send the origin of the document', 'so-ssl') . '</option>';
        echo '<option value="origin-when-cross-origin" ' . selected('origin-when-cross-origin', $referrer_policy, false) . '>' . __('origin-when-cross-origin - Full path for same origin, only origin for cross-origin', 'so-ssl') . '</option>';
        echo '<option value="same-origin" ' . selected('same-origin', $referrer_policy, false) . '>' . __('same-origin - Send referrer only for same-origin requests', 'so-ssl') . '</option>';
        echo '<option value="strict-origin" ' . selected('strict-origin', $referrer_policy, false) . '>' . __('strict-origin - Only send origin when protocol security level stays the same', 'so-ssl') . '</option>';
        echo '<option value="strict-origin-when-cross-origin" ' . selected('strict-origin-when-cross-origin', $referrer_policy, false) . '>' . __('strict-origin-when-cross-origin - (Recommended) Send full referrer to same-origin, only send origin when protocol security level stays the same', 'so-ssl') . '</option>';
        echo '<option value="unsafe-url" ' . selected('unsafe-url', $referrer_policy, false) . '>' . __('unsafe-url - Always send full referrer information (least secure)', 'so-ssl') . '</option>';
        echo '</select>';
        echo '<p class="description">' . __('Determines how much information is included in the Referer header when making requests.', 'so-ssl') . '</p>';
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
            register_setting(
                'so_ssl_options',
                'so_ssl_csp_' . str_replace('-', '_', $directive),
                array(
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_textarea_field',
                    'default' => $default_value,
                )
            );
        }
        
        // Content Security Policy Settings Section
        add_settings_section(
            'so_ssl_csp_full_section',
            __('Content Security Policy (CSP)', 'so-ssl'),
            array($this, 'csp_full_section_callback'),
            'so-ssl'
        );
        
        add_settings_field(
            'so_ssl_enable_csp',
            __('Enable Content Security Policy', 'so-ssl'),
            array($this, 'enable_csp_callback'),
            'so-ssl',
            'so_ssl_csp_full_section'
        );
        
        add_settings_field(
            'so_ssl_csp_mode',
            __('CSP Mode', 'so-ssl'),
            array($this, 'csp_mode_callback'),
            'so-ssl',
            'so_ssl_csp_full_section'
        );
        
        // Add fields for each CSP directive
        foreach ($csp_directives as $directive => $default_value) {
            $field_id = 'so_ssl_csp_' . str_replace('-', '_', $directive);
            
            add_settings_field(
                $field_id,
                $directive,
                array($this, 'csp_directive_callback'),
                'so-ssl',
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
        echo '<p>' . __('Content Security Policy (CSP) is an added layer of security that helps to detect and mitigate certain types of attacks, including Cross-Site Scripting (XSS) and data injection attacks.', 'so-ssl') . '</p>';
        echo '<p>' . __('It is recommended to first enable CSP in "Report-Only" mode to ensure it does not break your site functionality.', 'so-ssl') . '</p>';
        echo '<div class="notice notice-warning inline"><p>' . __('<strong>Warning:</strong> Incorrect CSP settings can break functionality on your site. Make sure to test thoroughly.', 'so-ssl') . '</p></div>';
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
        echo __('Enable Content Security Policy header', 'so-ssl');
        echo '</label>';
        echo '<p class="description">' . __('Adds the Content-Security-Policy header to restrict what resources can be loaded.', 'so-ssl') . '</p>';
    }
    
    /**
     * CSP Mode field callback.
     *
     * @since    1.0.2
     */
    public function csp_mode_callback() {
        $csp_mode = get_option('so_ssl_csp_mode', 'report-only');
        
        echo '<select id="so_ssl_csp_mode" name="so_ssl_csp_mode">';
        echo '<option value="report-only" ' . selected('report-only', $csp_mode, false) . '>' . __('Report-Only - Just report violations, do not enforce (recommended for testing)', 'so-ssl') . '</option>';
        echo '<option value="enforce" ' . selected('enforce', $csp_mode, false) . '>' . __('Enforce - Enforce the policy (only use after testing)', 'so-ssl') . '</option>';
        echo '</select>';
        echo '<p class="description">' . __('Determines whether the browser should enforce the policy or just report violations.', 'so-ssl') . '</p>';
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
            echo __('Enable upgrade-insecure-requests directive', 'so-ssl');
            echo '</label>';
            echo '<p class="description">' . __('Instructs browsers to upgrade HTTP requests to HTTPS before fetching.', 'so-ssl') . '</p>';
        } else {
            echo '<textarea id="' . esc_attr($field_id) . '" name="' . esc_attr($field_id) . '" rows="2" class="large-text" placeholder="' . esc_attr($args['default_value']) . '">' . esc_textarea($value) . '</textarea>';
            
            // Provide helper information based on directive
            switch ($directive) {
                case 'default-src':
                    $description = __('Fallback for other CSP directives. Example: \'self\' https://*.trusted-cdn.com', 'so-ssl');
                    break;
                case 'script-src':
                    $description = __('Controls JavaScript sources. Example: \'self\' \'unsafe-inline\' https://trusted-scripts.com', 'so-ssl');
                    break;
                case 'style-src':
                    $description = __('Controls CSS sources. Example: \'self\' \'unsafe-inline\' https://fonts.googleapis.com', 'so-ssl');
                    break;
                case 'img-src':
                    $description = __('Controls image sources. Example: \'self\' data: https://*.trusted-cdn.com', 'so-ssl');
                    break;
                default:
                    $description = sprintf(__('Controls %s sources.', 'so-ssl'), $directive);
            }
            
            echo '<p class="description">' . $description . '</p>';
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
            register_setting(
                'so_ssl_options',
                $option_name,
                array(
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'default' => $info['default'],
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
        echo '<p>' . __('The Permissions Policy header allows you to control which browser features and APIs can be used in your site.', 'so-ssl') . '</p>';
        echo '<p>' . __('This replaces the older Feature-Policy header with more granular controls.', 'so-ssl') . '</p>';
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
        echo __('Enable Permissions Policy header', 'so-ssl');
        echo '</label>';
        echo '<p class="description">' . __('Adds the Permissions-Policy header to control browser feature permissions.', 'so-ssl') . '</p>';
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
        echo '<option value="*" ' . selected('*', $value, false) . '>' . __('Allow for all origins (*)', 'so-ssl') . '</option>';
        echo '<option value="self" ' . selected('self', $value, false) . '>' . __('Allow for own origin only (self)', 'so-ssl') . '</option>';
        echo '<option value="none" ' . selected('none', $value, false) . '>' . __('Disable entirely (none)', 'so-ssl') . '</option>';
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
        echo '<p>' . __('Cross-Origin policies control how your site\'s resources can be used by other sites and how your site can interact with other sites.', 'so-ssl') . '</p>';
        echo '<div class="notice notice-warning inline"><p>' . __('<strong>Warning:</strong> These settings can break cross-origin functionality. Test thoroughly before enabling in production.', 'so-ssl') . '</p></div>';
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
        echo __('Enable', 'so-ssl') . ' ' . esc_html($header);
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
}