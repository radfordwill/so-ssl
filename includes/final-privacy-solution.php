
<?php

class So_SSL_Privacy_Compliance {

    /**
     * Initialize the privacy compliance module
     */
    public static function init() {
        if (!get_option('so_ssl_enable_privacy_compliance', 0)) {
            return;
        }

        // Use WordPress 'template_redirect' for frontend and 'admin_init' for backend
        add_action('template_redirect', array(__CLASS__, 'check_privacy_frontend'));
        add_action('admin_init', array(__CLASS__, 'check_privacy_admin'));
        
        // Handle form processing with high priority
        add_action('init', array(__CLASS__, 'process_privacy_form'), 1);
        
        // Add modal display
        add_action('wp_footer', array(__CLASS__, 'display_privacy_modal'));
        add_action('admin_footer', array(__CLASS__, 'display_privacy_modal'));
        
        // Register settings
        add_action('admin_init', array(__CLASS__, 'register_settings'));
    }

    /**
     * Process privacy form submission
     */
    public static function process_privacy_form() {
        if (!isset($_POST['so_ssl_privacy_nonce'])) {
            return;
        }

        if (!wp_verify_nonce($_POST['so_ssl_privacy_nonce'], 'so_ssl_privacy_acknowledgment')) {
            return;
        }

        if (!isset($_POST['so_ssl_privacy_accept']) || $_POST['so_ssl_privacy_accept'] != '1') {
            return;
        }

        $user_id = get_current_user_id();
        if (!$user_id) {
            return;
        }

        // Save acknowledgment
        update_user_meta($user_id, 'so_ssl_privacy_acknowledged', time());
        
        // Set transient to prevent immediate re-check (5 minutes)
        set_transient('so_ssl_privacy_ack_' . $user_id, true, 300);
        
        // Get clean redirect URL
        $redirect = is_admin() ? admin_url() : home_url();
        
        // Remove any privacy-related parameters
        $redirect = remove_query_arg(array('privacy-acknowledgment', 'privacy_required'), $redirect);
        
        // Redirect immediately
        wp_safe_redirect($redirect);
        exit;
    }

    /**
     * Check privacy for frontend
     */
    public static function check_privacy_frontend() {
        self::check_privacy_requirement();
    }

    /**
     * Check privacy for admin
     */
    public static function check_privacy_admin() {
        self::check_privacy_requirement();
    }

    /**
     * Main privacy check logic
     */
    private static function check_privacy_requirement() {
        // Skip if not logged in
        if (!is_user_logged_in()) {
            return;
        }

        $user_id = get_current_user_id();
        
        // Check transient first (prevents checking immediately after acknowledgment)
        if (get_transient('so_ssl_privacy_ack_' . $user_id)) {
            return;
        }

        // Check if user needs to acknowledge
        if (!self::user_needs_acknowledgment($user_id)) {
            return;
        }

        // Check if we're already showing the modal
        if (isset($_GET['privacy_required']) && $_GET['privacy_required'] == '1') {
            add_filter('so_ssl_show_privacy_modal', '__return_true');
            return;
        }

        // Add parameter to current URL to trigger modal
        $current_url = $_SERVER['REQUEST_URI'];
        $modal_url = add_query_arg('privacy_required', '1', $current_url);
        
        // Only redirect if we haven't already
        if (!isset($_GET['privacy_redirected'])) {
            $modal_url = add_query_arg('privacy_redirected', '1', $modal_url);
            wp_redirect($modal_url);
            exit;
        }
    }

    /**
     * Check if user needs acknowledgment
     */
    private static function user_needs_acknowledgment($user_id) {
        $current_user = wp_get_current_user();
        
        // Check admin exemption
        if (get_option('so_ssl_privacy_exempt_admins', true) && current_user_can('manage_options')) {
            return false;
        }
        
        // Check original admin exemption
        if (get_option('so_ssl_privacy_exempt_original_admin', true) && $user_id === 1) {
            return false;
        }
        
        // Check user roles
        $required_roles = get_option('so_ssl_privacy_required_roles', array('subscriber', 'contributor', 'author', 'editor'));
        $user_has_required_role = false;
        
        foreach ($current_user->roles as $role) {
            if (in_array($role, $required_roles)) {
                $user_has_required_role = true;
                break;
            }
        }
        
        if (!$user_has_required_role) {
            return false;
        }
        
        // Check acknowledgment with direct database query to avoid caching
        global $wpdb;
        $acknowledgment = $wpdb->get_var($wpdb->prepare(
            "SELECT meta_value FROM $wpdb->usermeta WHERE user_id = %d AND meta_key = %s",
            $user_id,
            'so_ssl_privacy_acknowledged'
        ));
        
        if (empty($acknowledgment)) {
            return true;
        }
        
        // Check expiry
        $expiry_days = intval(get_option('so_ssl_privacy_expiry_days', 30));
        if ((time() - intval($acknowledgment)) > ($expiry_days * DAY_IN_SECONDS)) {
            return true;
        }
        
        return false;
    }

    /**
     * Display privacy modal
     */
    public static function display_privacy_modal() {
        if (!apply_filters('so_ssl_show_privacy_modal', false)) {
            return;
        }

        $page_title = get_option('so_ssl_privacy_page_title', 'Privacy Acknowledgment Required');
        $notice_text = get_option('so_ssl_privacy_notice_text', '');
        $checkbox_text = get_option('so_ssl_privacy_checkbox_text', '');
        
        ?>
        <div id="so-ssl-privacy-modal" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 999999; display: flex; align-items: center; justify-content: center;">
            <div style="background: white; padding: 30px; max-width: 600px; width: 90%; border-radius: 8px;">
                <h2><?php echo esc_html($page_title); ?></h2>
                <div style="margin-bottom: 20px;"><?php echo wp_kses_post($notice_text); ?></div>
                
                <form method="post" action="<?php echo esc_url($_SERVER['REQUEST_URI']); ?>">
                    <?php wp_nonce_field('so_ssl_privacy_acknowledgment', 'so_ssl_privacy_nonce'); ?>
                    
                    <p>
                        <label>
                            <input type="checkbox" name="so_ssl_privacy_accept" value="1" id="privacy-accept">
                            <?php echo esc_html($checkbox_text); ?>
                        </label>
                    </p>
                    
                    <p>
                        <button type="submit" name="so_ssl_privacy_submit" value="1" class="button button-primary" id="privacy-submit" disabled>
                            <?php esc_html_e('Continue', 'so-ssl'); ?>
                        </button>
                        
                        <a href="<?php echo esc_url(wp_logout_url()); ?>" class="button">
                            <?php esc_html_e('Logout', 'so-ssl'); ?>
                        </a>
                    </p>
                </form>
            </div>
        </div>
        
        <script>
        document.getElementById('privacy-accept').addEventListener('change', function() {
            document.getElementById('privacy-submit').disabled = !this.checked;
        });
        </script>
        <?php
    }
}




<?php
/**
 * So SSL Privacy Compliance Module - Production-Ready Version
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

        // Check if admin agreement is being shown first
        if (apply_filters('so_ssl_showing_admin_agreement', false)) {
            return;
        }

        // Handle form processing with highest priority
        add_action('init', array(__CLASS__, 'process_privacy_form'), 1);

        // Check privacy requirements
        add_action('template_redirect', array(__CLASS__, 'check_privacy_frontend'), 20);
        add_action('admin_init', array(__CLASS__, 'check_privacy_admin'), 20);

        // Add modal display
        add_action('wp_footer', array(__CLASS__, 'display_privacy_modal'), 100);
        add_action('admin_footer', array(__CLASS__, 'display_privacy_modal'), 100);

        // Register admin settings
        add_action('admin_init', array(__CLASS__, 'register_settings'));

        // Add hook for admin scripts (for TinyMCE editor)
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_admin_scripts'));

        // Add flush rewrite rules button
        add_action('so_ssl_privacy_compliance_section_after', array(__CLASS__, 'add_flush_rules_button'));
    }

    /**
     * Process privacy form submission
     */
    public static function process_privacy_form() {
        // Only process if form was submitted
        if (!isset($_POST['so_ssl_privacy_nonce'])) {
            return;
        }

        // Verify nonce
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['so_ssl_privacy_nonce'])), 'so_ssl_privacy_acknowledgment')) {
            return;
        }

        // Check if accepted
        if (!isset($_POST['so_ssl_privacy_accept']) || $_POST['so_ssl_privacy_accept'] != '1') {
            add_filter('so_ssl_privacy_error', '__return_true');
            return;
        }

        $user_id = get_current_user_id();
        if (!$user_id) {
            return;
        }

        // Save acknowledgment
        update_user_meta($user_id, 'so_ssl_privacy_acknowledged', time());

        // Set WordPress option as backup (works better across domains)
        update_option('so_ssl_privacy_ack_user_' . $user_id, time());

        // Set transient to prevent immediate re-check (5 minutes)
        set_transient('so_ssl_privacy_ack_' . $user_id, true, 300);

        // Clear any caches
        clean_user_cache($user_id);
        wp_cache_delete($user_id, 'user_meta');

        // Determine redirect URL based on environment
        if (is_admin()) {
            $redirect = admin_url();
        } else {
            $redirect = home_url('/');
        }

        // Remove all privacy-related parameters
        $redirect = remove_query_arg(array(
            'privacy-acknowledgment',
            'privacy_required',
            'privacy_modal',
            'so_ssl_privacy_submit'
        ), $redirect);

        // Set a cookie that works across subdomains
        $domain = parse_url(home_url(), PHP_URL_HOST);
        if (strpos($domain, 'www.') === 0) {
            $domain = substr($domain, 4);
        }
        setcookie('so_ssl_privacy_done', '1', time() + 300, '/', '.' . $domain, is_ssl(), true);

        // Perform redirect
        wp_safe_redirect($redirect);
        exit;
    }

    /**
     * Check privacy for frontend
     */
    public static function check_privacy_frontend() {
        self::check_privacy_requirement(false);
    }

    /**
     * Check privacy for admin
     */
    public static function check_privacy_admin() {
        self::check_privacy_requirement(true);
    }

    /**
     * Main privacy check logic
     */
    private static function check_privacy_requirement($is_admin = false) {
        // Skip AJAX, cron, etc.
        if (wp_doing_ajax() || wp_doing_cron() || (defined('WP_CLI') && WP_CLI)) {
            return;
        }

        // Skip if not logged in
        if (!is_user_logged_in()) {
            return;
        }

        // Skip logout requests
        if (isset($_GET['action']) && $_GET['action'] === 'logout') {
            return;
        }

        $user_id = get_current_user_id();

        // Check cookie first (works better across domains)
        if (isset($_COOKIE['so_ssl_privacy_done'])) {
            return;
        }

        // Check transient (prevents checking immediately after acknowledgment)
        if (get_transient('so_ssl_privacy_ack_' . $user_id)) {
            return;
        }

        // Check WordPress option as backup
        $option_ack = get_option('so_ssl_privacy_ack_user_' . $user_id);
        if ($option_ack && (time() - intval($option_ack)) < 300) {
            return;
        }

        // Check if user is exempt
        if (self::is_user_exempt($user_id)) {
            return;
        }

        // Check if user has acknowledged
        if (self::has_valid_acknowledgment($user_id)) {
            return;
        }

        // Show modal directly instead of redirecting
        add_filter('so_ssl_show_privacy_modal', '__return_true');
    }

    /**
     * Check if user is exempt from privacy requirements
     */
    private static function is_user_exempt($user_id) {
        $current_user = wp_get_current_user();

        // Check admin exemption
        if (get_option('so_ssl_privacy_exempt_admins', true) && current_user_can('manage_options')) {
            return true;
        }

        // Check original admin exemption
        if (get_option('so_ssl_privacy_exempt_original_admin', true) && $user_id === 1) {
            return true;
        }

        // Check user roles
        $required_roles = get_option('so_ssl_privacy_required_roles', array('subscriber', 'contributor', 'author', 'editor'));
        if (empty($required_roles)) {
            return true; // No roles required
        }

        $user_has_required_role = false;

        foreach ($current_user->roles as $role) {
            if (in_array($role, $required_roles)) {
                $user_has_required_role = true;
                break;
            }
        }

        return !$user_has_required_role;
    }

    /**
     * Check if user has valid acknowledgment
     */
    private static function has_valid_acknowledgment($user_id) {
        // First check user meta
        $acknowledgment = get_user_meta($user_id, 'so_ssl_privacy_acknowledged', true);

        // If not found, try direct database query
        if (empty($acknowledgment)) {
            global $wpdb;
            $acknowledgment = $wpdb->get_var($wpdb->prepare(
                "SELECT meta_value FROM $wpdb->usermeta WHERE user_id = %d AND meta_key = %s LIMIT 1",
                $user_id,
                'so_ssl_privacy_acknowledged'
            ));
        }

        if (empty($acknowledgment)) {
            return false;
        }

        // Check expiry
        $expiry_days = intval(get_option('so_ssl_privacy_expiry_days', 30));
        if ((time() - intval($acknowledgment)) > ($expiry_days * DAY_IN_SECONDS)) {
            return false;
        }

        return true;
    }

    /**
     * Display privacy modal
     */
    public static function display_privacy_modal() {
        // Don't show if admin agreement is active
        if (apply_filters('so_ssl_showing_admin_agreement', false)) {
            return;
        }

        if (!apply_filters('so_ssl_show_privacy_modal', false)) {
            return;
        }

        // Ensure user is logged in
        if (!is_user_logged_in()) {
            return;
        }

        $page_title = get_option('so_ssl_privacy_page_title', 'Privacy Acknowledgment Required');
        $notice_text = get_option('so_ssl_privacy_notice_text', 'This site tracks certain information for security purposes including IP addresses, login attempts, and session data. By using this site, you acknowledge and consent to this data collection in accordance with our Privacy Policy and applicable data protection laws including GDPR and US privacy regulations.');
        $checkbox_text = get_option('so_ssl_privacy_checkbox_text', 'I acknowledge and consent to the privacy notice above');

        // Show error if form was submitted without checkbox
        $show_error = apply_filters('so_ssl_privacy_error', false);

        // Determine form action URL
        $form_action = $_SERVER['REQUEST_URI'];

        ?>
        <style>
            .so-ssl-privacy-modal-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.8);
                z-index: 999999;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .so-ssl-privacy-modal {
                background: white;
                padding: 0;
                max-width: 600px;
                width: 90%;
                border-radius: 8px;
                max-height: 90vh;
                overflow-y: auto;
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            }

            .so-ssl-privacy-header {
                background: #fff;
                border-bottom: 1px solid #dcdcde;
                padding: 20px 25px;
                border-radius: 8px 8px 0 0;
            }

            .so-ssl-privacy-title {
                color: #2271b1;
                font-size: 24px;
                font-weight: 600;
                margin: 0;
                display: flex;
                align-items: center;
            }

            .so-ssl-privacy-title .dashicons {
                margin-right: 10px;
                font-size: 28px;
                width: 28px;
                height: 28px;
            }

            .so-ssl-privacy-content {
                padding: 25px;
            }

            .so-ssl-privacy-notice {
                background: #f0f6fc;
                border-left: 4px solid #2271b1;
                padding: 15px 20px;
                margin-bottom: 25px;
                border-radius: 0 4px 4px 0;
                line-height: 1.6;
            }

            .so-ssl-privacy-form {
                padding: 20px 25px 25px;
                background: #f8f9fa;
                border-top: 1px solid #dcdcde;
                border-radius: 0 0 8px 8px;
            }

            .so-ssl-privacy-checkbox {
                margin: 15px 0 20px;
            }

            .so-ssl-privacy-checkbox label {
                cursor: pointer;
                font-weight: 500;
            }

            .so-ssl-privacy-actions {
                display: flex;
                justify-content: space-between;
                align-items: center;
                flex-wrap: wrap;
                gap: 10px;
            }

            .so-ssl-privacy-submit {
                background: #2271b1;
                border: 1px solid #2271b1;
                color: white;
                padding: 8px 20px;
                border-radius: 3px;
                cursor: pointer;
                font-size: 14px;
                font-weight: 500;
                transition: all 0.2s ease;
            }

            .so-ssl-privacy-submit:hover:not(:disabled) {
                background: #135e96;
                border-color: #135e96;
            }

            .so-ssl-privacy-submit:disabled {
                background: #c3c4c7;
                border-color: #c3c4c7;
                cursor: not-allowed;
                opacity: 0.7;
            }

            .so-ssl-privacy-logout {
                color: #646970;
                text-decoration: none;
                font-size: 14px;
            }

            .so-ssl-privacy-logout:hover {
                color: #d63638;
                text-decoration: underline;
            }

            .so-ssl-privacy-error {
                color: #d63638;
                background: #fcf0f1;
                border-left: 4px solid #d63638;
                padding: 10px 15px;
                margin-top: 15px;
                border-radius: 0 4px 4px 0;
            }

            /* Ensure proper display in admin area */
            body.wp-admin .so-ssl-privacy-modal-overlay {
                z-index: 999999;
            }

            @media (max-width: 768px) {
                .so-ssl-privacy-modal {
                    width: 95%;
                }
                .so-ssl-privacy-actions {
                    flex-direction: column;
                    width: 100%;
                }
                .so-ssl-privacy-submit,
                .so-ssl-privacy-logout {
                    width: 100%;
                    text-align: center;
                }
            }
        </style>

        <div class="so-ssl-privacy-modal-overlay" id="so-ssl-privacy-modal-overlay">
            <div class="so-ssl-privacy-modal">
                <div class="so-ssl-privacy-header">
                    <h2 class="so-ssl-privacy-title">
                        <span class="dashicons dashicons-shield"></span>
                        <?php echo esc_html($page_title); ?>
                    </h2>
                </div>

                <div class="so-ssl-privacy-content">
                    <div class="so-ssl-privacy-notice">
                        <?php echo wp_kses_post($notice_text); ?>
                    </div>
                </div>

                <div class="so-ssl-privacy-form">
                    <form method="post" action="<?php echo esc_url($form_action); ?>" id="so-ssl-privacy-form">
                        <?php wp_nonce_field('so_ssl_privacy_acknowledgment', 'so_ssl_privacy_nonce'); ?>

                        <div class="so-ssl-privacy-checkbox">
                            <label>
                                <input type="checkbox" name="so_ssl_privacy_accept" value="1" id="so-ssl-privacy-accept">
                                <?php echo esc_html($checkbox_text); ?>
                            </label>
                        </div>

                        <div class="so-ssl-privacy-actions">
                            <button type="submit" name="so_ssl_privacy_submit" value="1" class="so-ssl-privacy-submit" id="so-ssl-privacy-submit" disabled>
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

        <script type="text/javascript">
        (function() {
            // Ensure compatibility across browsers
            document.addEventListener('DOMContentLoaded', function() {
                var checkbox = document.getElementById('so-ssl-privacy-accept');
                var submitBtn = document.getElementById('so-ssl-privacy-submit');
                var form = document.getElementById('so-ssl-privacy-form');

                if (checkbox && submitBtn) {
                    // Enable/disable submit button
                    function updateButton() {
                        submitBtn.disabled = !checkbox.checked;
                    }

                    checkbox.addEventListener('change', updateButton);

                    // Initial state
                    updateButton();
                }

                // Prevent closing modal by clicking outside
                var overlay = document.getElementById('so-ssl-privacy-modal-overlay');
                if (overlay) {
                    overlay.addEventListener('click', function(e) {
                        if (e.target === overlay) {
                            e.preventDefault();
                            e.stopPropagation();
                            return false;
                        }
                    });
                }

                // Ensure form submission works
                if (form) {
                    form.addEventListener('submit', function(e) {
                        if (!checkbox.checked) {
                            e.preventDefault();
                            return false;
                        }
                    });
                }
            });
        })();
        </script>
        <?php
    }

    /**
     * Enqueue admin scripts
     */
    public static function enqueue_admin_scripts($hook) {
        // Only load on our plugin's settings page
        if (strpos($hook, 'so-ssl') !== false || $hook === 'settings_page_so-ssl') {
            wp_enqueue_editor();
            wp_enqueue_media();
        }
    }

    /**
     * Register settings for privacy compliance - keeping all the existing settings
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
    }

    /**
     * Show flush rewrite rules button
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
}

// Initialize the class
So_SSL_Privacy_Compliance::init();