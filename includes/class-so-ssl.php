<?php
/**
 * The core plugin class.
 *
 * @since      1.0.2
 * @package    So_SSL
 */

class So_SSL {

    /**
     * Plugin version.
     *
     * @since    1.0.2
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
            <form action="options.php" method="post">
                <?php
                settings_fields('so_ssl_options');
                do_settings_sections('so-ssl');
                submit_button();
                ?>
            </form>
        </div>
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
            'so-ssl'
        );

        add_settings_field(
            'so_ssl_force_ssl',
            __('Force SSL', 'so-ssl'),
            array($this, 'force_ssl_callback'),
            'so-ssl',
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
            'so-ssl'
        );

        add_settings_field(
            'so_ssl_enable_hsts',
            __('Enable HSTS', 'so-ssl'),
            array($this, 'enable_hsts_callback'),
            'so-ssl',
            'so_ssl_hsts_section'
        );

        add_settings_field(
            'so_ssl_hsts_max_age',
            __('Max Age', 'so-ssl'),
            array($this, 'hsts_max_age_callback'),
            'so-ssl',
            'so_ssl_hsts_section'
        );

        add_settings_field(
            'so_ssl_hsts_subdomains',
            __('Include Subdomains', 'so-ssl'),
            array($this, 'hsts_subdomains_callback'),
            'so-ssl',
            'so_ssl_hsts_section'
        );

        add_settings_field(
            'so_ssl_hsts_preload',
            __('Preload', 'so-ssl'),
            array($this, 'hsts_preload_callback'),
            'so-ssl',
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
            'so-ssl'
        );

        add_settings_field(
            'so_ssl_enable_xframe',
            __('Enable X-Frame-Options', 'so-ssl'),
            array($this, 'enable_xframe_callback'),
            'so-ssl',
            'so_ssl_xframe_section'
        );

        add_settings_field(
            'so_ssl_xframe_option',
            __('X-Frame-Options Value', 'so-ssl'),
            array($this, 'xframe_option_callback'),
            'so-ssl',
            'so_ssl_xframe_section'
        );

        add_settings_field(
            'so_ssl_xframe_allow_from',
            __('Allow From Domain', 'so-ssl'),
            array($this, 'xframe_allow_from_callback'),
            'so-ssl',
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
            'so-ssl'
        );

        add_settings_field(
            'so_ssl_enable_csp_frame_ancestors',
            __('Enable CSP Frame-Ancestors', 'so-ssl'),
            array($this, 'enable_csp_frame_ancestors_callback'),
            'so-ssl',
            'so_ssl_csp_section'
        );

        add_settings_field(
            'so_ssl_csp_frame_ancestors_option',
            __('Frame-Ancestors Value', 'so-ssl'),
            array($this, 'csp_frame_ancestors_option_callback'),
            'so-ssl',
            'so_ssl_csp_section'
        );

        add_settings_field(
            'so_ssl_csp_include_self',
            __('Include Self', 'so-ssl'),
            array($this, 'csp_include_self_callback'),
            'so-ssl',
            'so_ssl_csp_section',
            array('class' => 'so_ssl_csp_custom_field')
        );

        add_settings_field(
            'so_ssl_csp_frame_ancestors_domains',
            __('Allowed Domains', 'so-ssl'),
            array($this, 'csp_frame_ancestors_domains_callback'),
            'so-ssl',
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

            // Initial state
            toggleAllowFrom();
            toggleCSPCustomFields();

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
  }
