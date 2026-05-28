<?php
if (!defined('ABSPATH')) { exit; }

class So_SSL_Plugin {
    const OPTION = 'so_ssl_options';
    const LOGIN_ATTEMPTS = 'so_ssl_login_attempts';

    private $defaults = array(
        'force_ssl' => 0,
        'block_search_indexing' => 0,
        'enable_hsts' => 0,
        'hsts_max_age' => 31536000,
        'hsts_include_subdomains' => 0,
        'hsts_preload' => 0,
        'x_frame_options' => 'SAMEORIGIN',
        'referrer_policy' => 'strict-origin-when-cross-origin',
        'permissions_policy' => 'camera=(), microphone=(), geolocation=()',
        'csp_enabled' => 0,
        'csp_policy' => "default-src 'self'; img-src 'self' data: https:; font-src 'self' data: https:; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; worker-src 'self' blob:;",
        'coep' => '',
        'coop' => '',
        'corp' => '',
        'privacy_enabled' => 0,
        'privacy_roles' => array('subscriber','contributor','author','editor','administrator'),
        'privacy_expiry_days' => 365,
        'privacy_title' => 'Privacy Acknowledgment',
        'privacy_text' => 'Please review and acknowledge this privacy notice before continuing.',
        'privacy_checkbox' => 'I have read and acknowledge this privacy notice.',
        'admin_agreement_enabled' => 0,
        'admin_agreement_expiry_days' => 365,
        'admin_agreement_text' => 'I understand that changing SSL and security settings can affect site availability and security.',
        'admin_agreement_checkbox' => 'I accept administrator responsibility for these settings.',
        'admin_emergency_override' => 1,
        'two_factor_master_enabled' => 1,
        'two_factor_enabled' => 0,
        'two_factor_roles' => array('administrator'),
        'two_factor_method' => 'email',
        'two_factor_email_fallback' => 0,
        'two_factor_log_failures' => 1,
        'strong_passwords' => 0,
        'session_limit' => 0,
        'session_max_hours' => 0,
        'login_limit_enabled' => 0,
        'login_max_attempts' => 5,
        'login_lockout_minutes' => 15,
        'login_email_notifications' => 0,
        'ip_whitelist' => '',
        'ip_blacklist' => '',
    );

    public function __construct() {
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_init', array($this, 'enforce_mandatory_totp_setup'));
        add_filter('login_redirect', array($this, 'mandatory_totp_login_redirect'), 10, 3);
        add_action('admin_enqueue_scripts', array($this, 'admin_assets'));
        add_action('wp_enqueue_scripts', array($this, 'public_assets'));
        add_action('send_headers', array($this, 'send_security_headers'));
        add_action('wp_head', array($this, 'output_robots_meta'), 1);
        add_filter('robots_txt', array($this, 'filter_robots_txt'), 10, 2);
        add_action('template_redirect', array($this, 'force_ssl_redirect'), 0);
        add_action('admin_notices', array($this, 'ssl_notice'));
        add_action('admin_notices', array($this, 'two_factor_admin_notice'));
        add_action('wp_footer', array($this, 'privacy_modal'));
        add_action('admin_footer', array($this, 'admin_agreement_modal'));
        add_action('wp_ajax_so_ssl_accept_privacy', array($this, 'ajax_accept_privacy'));
        add_action('wp_ajax_so_ssl_accept_admin_agreement', array($this, 'ajax_accept_admin_agreement'));
        add_action('wp_login', array($this, 'on_login'), 10, 2);
        add_action('authenticate', array($this, 'check_login_limit'), 30, 3);
        add_action('wp_login_failed', array($this, 'record_login_failure'));
        add_filter('registration_errors', array($this, 'validate_registration_password'), 10, 3);
        add_action('user_profile_update_errors', array($this, 'validate_profile_password'), 10, 3);
        add_shortcode('so_ssl_privacy_acknowledgment', array($this, 'privacy_shortcode'));
        add_action('show_user_profile', array($this, 'user_2fa_fields'));
        add_action('edit_user_profile', array($this, 'user_2fa_fields'));
        add_action('personal_options_update', array($this, 'save_user_2fa_fields'));
        add_action('edit_user_profile_update', array($this, 'save_user_2fa_fields'));
        add_filter('authenticate', array($this, 'two_factor_authenticate'), 50, 3);
        add_action('login_form_so_ssl_2fa', array($this, 'two_factor_form'));
        add_action('admin_post_so_ssl_email_backup_codes', array($this, 'email_backup_codes'));
        add_action('login_enqueue_scripts', array($this, 'login_styles'));
    }

    public static function activate() {
        $self = new self();
        $opts = get_option(self::OPTION, array());
        update_option(self::OPTION, wp_parse_args($opts, $self->defaults));
    }

    public static function deactivate() {}

    public function options() {
        $opts = get_option(self::OPTION, array());
        return wp_parse_args(is_array($opts) ? $opts : array(), $this->defaults);
    }

    public function admin_menu() {
        add_menu_page(
            'So SSL',
            'So SSL',
            'manage_options',
            'so-ssl',
            array($this, 'settings_page'),
            'dashicons-shield-alt',
            80
        );

        add_submenu_page(
            'so-ssl',
            'So SSL Settings',
            'Settings',
            'manage_options',
            'so-ssl',
            array($this, 'settings_page')
        );

        add_submenu_page(
            'so-ssl',
            'So SSL Sessions',
            'Sessions',
            'manage_options',
            'so-ssl-sessions',
            array($this, 'sessions_page')
        );

        add_submenu_page(
            'so-ssl',
            'So SSL 2FA Recovery',
            '2FA Recovery',
            'manage_options',
            'so-ssl-2fa-recovery',
            array($this, 'two_factor_recovery_page')
        );

        add_submenu_page(
            'so-ssl',
            'So SSL 2FA Logs',
            '2FA Logs',
            'manage_options',
            'so-ssl-2fa-logs',
            array($this, 'two_factor_logs_page')
        );
    }

    public function register_settings() {
        register_setting('so_ssl_group', self::OPTION, array($this, 'sanitize_options'));

        foreach ($this->settings_api_tabs() as $tab_id => $tab) {
            $page = $this->settings_api_page($tab_id);
            add_settings_section(
                'so_ssl_section_' . $tab_id,
                '',
                array($this, 'render_settings_api_section'),
                $page,
                array('title' => $tab['title'])
            );

            foreach ($tab['fields'] as $index => $field) {
                $field_id = isset($field['id']) ? $field['id'] : ('custom_' . $index);
                add_settings_field(
                    'so_ssl_field_' . $tab_id . '_' . sanitize_key($field_id) . '_' . $index,
                    '',
                    array($this, 'render_settings_api_field'),
                    $page,
                    'so_ssl_section_' . $tab_id,
                    $field
                );
            }
        }
    }

    public function sanitize_options($input) {
        $old = $this->options();
        $out = $old;
        $checkboxes = array('force_ssl','block_search_indexing','enable_hsts','hsts_include_subdomains','hsts_preload','csp_enabled','privacy_enabled','admin_agreement_enabled','admin_emergency_override','two_factor_master_enabled','two_factor_enabled','two_factor_email_fallback','two_factor_log_failures','strong_passwords','login_limit_enabled','login_email_notifications');
        foreach ($checkboxes as $key) { $out[$key] = empty($input[$key]) ? 0 : 1; }
        foreach (array('hsts_max_age','privacy_expiry_days','admin_agreement_expiry_days','session_limit','session_max_hours','login_max_attempts','login_lockout_minutes') as $key) {
            $out[$key] = isset($input[$key]) ? absint($input[$key]) : absint($old[$key]);
        }
        foreach (array('x_frame_options','referrer_policy','coep','coop','corp','two_factor_method') as $key) {
            $out[$key] = isset($input[$key]) ? sanitize_text_field($input[$key]) : $old[$key];
        }
        foreach (array('permissions_policy','csp_policy','privacy_title','privacy_checkbox','admin_agreement_checkbox','ip_whitelist','ip_blacklist') as $key) {
            $out[$key] = isset($input[$key]) ? sanitize_textarea_field($input[$key]) : $old[$key];
        }
        foreach (array('privacy_text','admin_agreement_text') as $key) {
            $out[$key] = isset($input[$key]) ? wp_kses_post($input[$key]) : $old[$key];
        }
        $out['privacy_roles'] = isset($input['privacy_roles']) && is_array($input['privacy_roles']) ? array_map('sanitize_key', $input['privacy_roles']) : array();
        $out['two_factor_roles'] = isset($input['two_factor_roles']) && is_array($input['two_factor_roles']) ? array_map('sanitize_key', $input['two_factor_roles']) : array();
        return $out;
    }

    public function admin_assets($hook) {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        $screen_id = $screen ? $screen->id : '';
        $is_so_ssl_screen = (strpos($hook, 'so-ssl') !== false) || in_array($screen_id, array('profile', 'user-edit'), true);
        if (!$is_so_ssl_screen) { return; }
        wp_enqueue_style('so-ssl-admin', SO_SSL_URL . 'assets/admin.css', array(), SO_SSL_VERSION);
        wp_enqueue_script('so-ssl-qrcode', SO_SSL_URL . 'assets/vendor/qrcode-local.js', array(), SO_SSL_VERSION, true);
        wp_enqueue_script('so-ssl-admin', SO_SSL_URL . 'assets/admin.js', array('jquery','so-ssl-qrcode'), SO_SSL_VERSION, true);
        wp_localize_script('so-ssl-admin', 'SoSSLAdmin', array('ajax' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('so_ssl_admin')));
    }

    public function public_assets() {
        wp_enqueue_style('so-ssl-public', SO_SSL_URL . 'assets/public.css', array(), SO_SSL_VERSION);
        wp_enqueue_script('so-ssl-public', SO_SSL_URL . 'assets/public.js', array('jquery'), SO_SSL_VERSION, true);
        wp_localize_script('so-ssl-public', 'SoSSL', array('ajax' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('so_ssl_public')));
    }

    public function login_styles() {
        wp_enqueue_style('so-ssl-login', SO_SSL_URL . 'assets/public.css', array(), SO_SSL_VERSION);
    }

    public function force_ssl_redirect() {
        $o = $this->options();
        if (!empty($o['force_ssl']) && !is_ssl() && !headers_sent()) {
            $host = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : wp_parse_url(home_url(), PHP_URL_HOST);
            $request_uri = isset($_SERVER['REQUEST_URI']) ? esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])) : '/';
            $request_uri = '/' . ltrim($request_uri, '/');
            wp_safe_redirect('https://' . $host . $request_uri, 301);
            exit;
        }
    }

    public function send_security_headers() {
        if (headers_sent()) { return; }
        $o = $this->options();
        if (!empty($o['enable_hsts']) && is_ssl()) {
            $hsts = 'max-age=' . absint($o['hsts_max_age']);
            if (!empty($o['hsts_include_subdomains'])) { $hsts .= '; includeSubDomains'; }
            if (!empty($o['hsts_preload'])) { $hsts .= '; preload'; }
            header('Strict-Transport-Security: ' . $hsts);
        }
        if (!empty($o['x_frame_options'])) { header('X-Frame-Options: ' . sanitize_text_field($o['x_frame_options'])); }
        if (!empty($o['referrer_policy'])) { header('Referrer-Policy: ' . sanitize_text_field($o['referrer_policy'])); }
        if (!empty($o['permissions_policy'])) { header('Permissions-Policy: ' . str_replace(array("\r","\n"), '', $o['permissions_policy'])); }
        if (!empty($o['csp_enabled']) && !empty($o['csp_policy'])) { header('Content-Security-Policy: ' . $this->normalize_csp_policy($o['csp_policy'])); }
        if (!empty($o['coep'])) { header('Cross-Origin-Embedder-Policy: ' . sanitize_text_field($o['coep'])); }
        if (!empty($o['coop'])) { header('Cross-Origin-Opener-Policy: ' . sanitize_text_field($o['coop'])); }
        if (!empty($o['corp'])) { header('Cross-Origin-Resource-Policy: ' . sanitize_text_field($o['corp'])); }
        if (!empty($o['block_search_indexing'])) { header('X-Robots-Tag: noindex, nofollow, noarchive', true); }
    }

    public function output_robots_meta() {
        $o = $this->options();
        if (empty($o['block_search_indexing'])) { return; }
        echo "\n" . '<meta name="robots" content="noindex,nofollow,noarchive">' . "\n";
    }

    public function filter_robots_txt($output, $public) {
        $o = $this->options();
        if (empty($o['block_search_indexing'])) { return $output; }
        $block = "User-agent: *\nDisallow: /\n";
        if (strpos($output, 'User-agent: *') === false || strpos($output, 'Disallow: /') === false) {
            $output = trim($output) . "\n\n# Added by So SSL search indexing protection\n" . $block;
        }
        return $output;
    }

    private function normalize_csp_policy($policy) {
        $policy = trim(str_replace(array("\r", "\n"), '', (string) $policy));
        if ($policy === '') { return ''; }

        // WordPress, themes, and some builders can load icon/web fonts as data: URLs.
        // If the user has not explicitly set font-src, add a safe, narrow font directive
        // so default-src does not accidentally block base64 WOFF/WOFF2 fonts.
        if (!preg_match('/(^|;)\s*font-src\s+/i', $policy)) {
            $policy = rtrim($policy, '; ') . "; font-src 'self' data: https:";
        }

        // WordPress core can create blob: workers, including from the emoji loader.
        // If worker-src is not explicit, browsers fall back to script-src and can block it.
        if (!preg_match('/(^|;)\s*worker-src\s+/i', $policy)) {
            $policy = rtrim($policy, '; ') . "; worker-src 'self' blob:";
        }

        return $policy;
    }

    public function ssl_notice() {
        $o = $this->options();
        if (!empty($o['force_ssl']) && !is_ssl() && current_user_can('manage_options')) {
            echo '<div class="notice notice-warning"><p><strong>So SSL:</strong> Force SSL is enabled, but this admin request is not currently using HTTPS. Verify your SSL certificate before enforcing HTTPS on production.</p></div>';
        }
    }

    private function user_has_role_requirement($roles) {
        $user = wp_get_current_user();
        if (!$user || empty($user->roles)) { return false; }
        return (bool) array_intersect((array) $roles, (array) $user->roles);
    }

    private function acknowledgment_valid($meta_key, $days) {
        $stamp = absint(get_user_meta(get_current_user_id(), $meta_key, true));
        if (!$stamp) { return false; }
        $days = absint($days);
        if (!$days) { return true; }
        return $stamp >= (time() - ($days * DAY_IN_SECONDS));
    }

    public function privacy_modal() {
        if (!is_user_logged_in() || is_admin()) { return; }
        $o = $this->options();
        if (empty($o['privacy_enabled']) || !$this->user_has_role_requirement($o['privacy_roles']) || $this->acknowledgment_valid('so_ssl_privacy_ack', $o['privacy_expiry_days'])) { return; }
        echo wp_kses($this->modal_markup('so-ssl-privacy-modal', $o['privacy_title'], wpautop(wp_kses_post($o['privacy_text'])), $o['privacy_checkbox'], 'so_ssl_accept_privacy'), $this->allowed_html());
    }

    public function admin_agreement_modal() {
        if (!is_admin() || !current_user_can('manage_options')) { return; }
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || strpos($screen->id, 'so-ssl') === false) { return; }
        $o = $this->options();
        if (empty($o['admin_agreement_enabled']) || $this->acknowledgment_valid('so_ssl_admin_agreement_ack', $o['admin_agreement_expiry_days'])) { return; }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only emergency override check; no data is saved.
        if (isset($_GET['so_ssl_emergency_override']) && '1' === sanitize_text_field(wp_unslash($_GET['so_ssl_emergency_override'])) && !empty($o['admin_emergency_override'])) { return; }
        echo wp_kses($this->modal_markup('so-ssl-admin-agreement-modal', 'Administrator Agreement', wpautop(wp_kses_post($o['admin_agreement_text'])), $o['admin_agreement_checkbox'], 'so_ssl_accept_admin_agreement'), $this->allowed_html());
    }

    private function modal_markup($id, $title, $body, $checkbox, $action) {
        return '<div id="' . esc_attr($id) . '" class="so-ssl-modal" data-action="' . esc_attr($action) . '"><div class="so-ssl-modal-card"><h2>' . esc_html($title) . '</h2><div class="so-ssl-modal-body">' . wp_kses_post($body) . '</div><label class="so-ssl-check"><input type="checkbox"> ' . esc_html($checkbox) . '</label><button class="button button-primary so-ssl-accept" disabled>Acknowledge and Continue</button><p class="so-ssl-modal-error" aria-live="polite"></p></div></div>';
    }

    public function ajax_accept_privacy() {
        check_ajax_referer('so_ssl_public', 'nonce');
        if (!is_user_logged_in()) { wp_send_json_error('Not logged in.'); }
        update_user_meta(get_current_user_id(), 'so_ssl_privacy_ack', time());
        wp_send_json_success();
    }

    public function ajax_accept_admin_agreement() {
        check_ajax_referer('so_ssl_admin', 'nonce');
        if (!current_user_can('manage_options')) { wp_send_json_error('Unauthorized.'); }
        update_user_meta(get_current_user_id(), 'so_ssl_admin_agreement_ack', time());
        wp_send_json_success();
    }

    public function privacy_shortcode() {
        $o = $this->options();
        return '<div class="so-ssl-privacy-preview"><h2>' . esc_html($o['privacy_title']) . '</h2>' . wpautop(wp_kses_post($o['privacy_text'])) . '</div>';
    }

    public function on_login($user_login, $user) {
        $this->limit_sessions($user->ID);
        update_user_meta($user->ID, 'so_ssl_last_login', time());
    }

    private function limit_sessions($user_id) {
        $o = $this->options();
        if (empty($o['session_limit']) && empty($o['session_max_hours'])) { return; }
        if (!class_exists('WP_Session_Tokens')) { return; }
        $manager = WP_Session_Tokens::get_instance($user_id);
        $sessions = $manager->get_all();
        if (!empty($o['session_max_hours'])) {
            foreach ($sessions as $verifier => $session) {
                if (!empty($session['login']) && $session['login'] < (time() - absint($o['session_max_hours']) * HOUR_IN_SECONDS)) {
                    $manager->destroy($verifier);
                }
            }
        }
        if (!empty($o['session_limit'])) {
            uasort($sessions, function($a, $b){ return ($a['login'] ?? 0) <=> ($b['login'] ?? 0); });
            while (count($sessions) >= absint($o['session_limit'])) {
                $verifier = key($sessions);
                $manager->destroy($verifier);
                unset($sessions[$verifier]);
            }
        }
    }

    public function sessions_page() {
        if (!current_user_can('manage_options')) { return; }
        if (isset($_POST['so_ssl_destroy_sessions']) && check_admin_referer('so_ssl_sessions')) {
            $uid = isset($_POST['user_id']) ? absint(wp_unslash($_POST['user_id'])) : 0;
            if ($uid && class_exists('WP_Session_Tokens')) {
                WP_Session_Tokens::get_instance($uid)->destroy_all();
                echo '<div class="notice notice-success"><p>Sessions destroyed.</p></div>';
            }
        }
        $users = get_users(array('number' => 50, 'orderby' => 'registered', 'order' => 'DESC'));
        echo wp_kses('<div class="wrap so-ssl-admin"><h1>So SSL User Sessions ' . $this->info_icon('sessions_page') . '</h1><p>View recent users and terminate all active sessions for a selected account. ' . $this->info_icon('sessions_page_summary') . '</p><table class="widefat"><thead><tr><th>User</th><th>Email</th><th>Last Login</th><th>Action</th></tr></thead><tbody>', $this->allowed_html());
        foreach ($users as $u) {
            echo '<tr><td>' . esc_html($u->display_name) . '</td><td>' . esc_html($u->user_email) . '</td><td>' . esc_html(get_user_meta($u->ID, 'so_ssl_last_login', true) ? wp_date('Y-m-d H:i', get_user_meta($u->ID, 'so_ssl_last_login', true)) : 'Never recorded') . '</td><td><form method="post">';
            wp_nonce_field('so_ssl_sessions');
            echo '<input type="hidden" name="user_id" value="' . esc_attr($u->ID) . '"><button class="button" name="so_ssl_destroy_sessions" value="1">Terminate Sessions ' . wp_kses($this->info_icon('terminate_sessions'), $this->allowed_html()) . '</button></form></td></tr>';
        }
        echo '</tbody></table></div>';
    }

    private function client_ip() {
        foreach (array('HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','REMOTE_ADDR') as $key) {
            if (!empty($_SERVER[$key])) {
                $server_value = sanitize_text_field(wp_unslash($_SERVER[$key]));
                $ip = trim(explode(',', $server_value)[0]);
                return sanitize_text_field($ip);
            }
        }
        return 'unknown';
    }

    private function ip_list_contains($list, $ip) {
        $items = array_filter(array_map('trim', preg_split('/[\r\n,]+/', (string) $list)));
        return in_array($ip, $items, true);
    }

    public function check_login_limit($user, $username, $password) {
        $o = $this->options();
        if (empty($o['login_limit_enabled']) || empty($username)) { return $user; }
        $ip = $this->client_ip();
        if ($this->ip_list_contains($o['ip_whitelist'], $ip)) { return $user; }
        if ($this->ip_list_contains($o['ip_blacklist'], $ip)) { return new WP_Error('so_ssl_blocked', __('This IP address is blocked.', 'so-ssl')); }
        $attempts = get_transient(self::LOGIN_ATTEMPTS . '_' . md5($ip));
        $attempts = is_array($attempts) ? $attempts : array('count' => 0, 'lock_until' => 0);
        if (!empty($attempts['lock_until']) && time() < $attempts['lock_until']) {
            return new WP_Error('so_ssl_locked', __('Too many failed login attempts. Please try again later.', 'so-ssl'));
        }
        return $user;
    }

    public function record_login_failure($username) {
        $o = $this->options();
        if (empty($o['login_limit_enabled'])) { return; }
        $ip = $this->client_ip();
        if ($this->ip_list_contains($o['ip_whitelist'], $ip)) { return; }
        $key = self::LOGIN_ATTEMPTS . '_' . md5($ip);
        $attempts = get_transient($key);
        $attempts = is_array($attempts) ? $attempts : array('count' => 0, 'lock_until' => 0);
        $attempts['count']++;
        if ($attempts['count'] >= max(1, absint($o['login_max_attempts']))) {
            $attempts['lock_until'] = time() + max(1, absint($o['login_lockout_minutes'])) * MINUTE_IN_SECONDS;
            if (!empty($o['login_email_notifications'])) {
                wp_mail(get_option('admin_email'), 'So SSL Login Lockout', 'IP ' . $ip . ' has been locked out after failed login attempts.');
            }
        }
        set_transient($key, $attempts, DAY_IN_SECONDS);
    }

    public function validate_registration_password($errors, $sanitized_user_login, $user_email) {
        $o = $this->options();
        if (!empty($o['strong_passwords']) && isset($_POST['_wpnonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'add-user') && !empty($_POST['pass1']) && !$this->password_is_strong(sanitize_text_field(wp_unslash($_POST['pass1'])))) {
            $errors->add('weak_password', __('Please choose a stronger password with upper/lowercase letters, numbers, and symbols.', 'so-ssl'));
        }
        return $errors;
    }

    public function validate_profile_password($errors, $update, $user) {
        $o = $this->options();
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- The profile/update-user nonce is verified by profile_update_nonce_valid() before reading pass1.
        if (!empty($o['strong_passwords']) && $this->profile_update_nonce_valid($user->ID) && !empty($_POST['pass1']) && !$this->password_is_strong(sanitize_text_field(wp_unslash($_POST['pass1'])))) {
            $errors->add('weak_password', __('Please choose a stronger password with upper/lowercase letters, numbers, and symbols.', 'so-ssl'));
        }
    }

    private function profile_update_nonce_valid($user_id) {
        if (!isset($_POST['_wpnonce'])) {
            return false;
        }
        $nonce = sanitize_text_field(wp_unslash($_POST['_wpnonce']));
        return wp_verify_nonce($nonce, 'update-user_' . (int) $user_id) || wp_verify_nonce($nonce, 'add-user');
    }

    private function password_is_strong($pass) {
        return strlen($pass) >= 12 && preg_match('/[a-z]/', $pass) && preg_match('/[A-Z]/', $pass) && preg_match('/\d/', $pass) && preg_match('/[^A-Za-z0-9]/', $pass);
    }

    private function two_factor_master_enabled() {
        $o = $this->options();
        return !(defined('SO_SSL_DISABLE_2FA') && SO_SSL_DISABLE_2FA) && !empty($o['two_factor_master_enabled']);
    }

    private function get_user_2fa_override($user_id) {
        $override = get_user_meta($user_id, 'so_ssl_2fa_override', true);
        return in_array($override, array('default', 'enabled', 'disabled'), true) ? $override : 'default';
    }

    private function get_user_2fa_state($user) {
        $o = $this->options();
        $state = array(
            'required' => false,
            'method' => !empty($o['two_factor_method']) ? $o['two_factor_method'] : 'email',
            'role_required' => false,
            'override' => 'default',
            'override_exists' => false,
            'completed' => false,
            'master_enabled' => $this->two_factor_master_enabled(),
        );
        if (!$user instanceof WP_User) { return $state; }
        $state['override_exists'] = metadata_exists('user', $user->ID, 'so_ssl_2fa_override');
        $state['override'] = $this->get_user_2fa_override($user->ID);
        $state['completed'] = (bool) get_user_meta($user->ID, 'so_ssl_2fa_enabled', true);
        if (!$state['master_enabled']) { return $state; }

        // Flow order is intentional:
        // 1. The master 2FA toggle disables every So SSL 2FA check.
        // 2. A profile override always wins over role-based defaults.
        // 3. Role-based 2FA only applies when the profile override is set to default.
        if ($state['override'] === 'disabled') {
            $state['required'] = false;
            $state['role_required'] = false;
            $state['method'] = 'totp';
            return $state;
        }
        if ($state['override'] === 'enabled') {
            $state['required'] = true;
            $state['role_required'] = false;
            $state['method'] = 'totp';
            return $state;
        }

        $state['role_required'] = !empty($o['two_factor_enabled']) && (bool) array_intersect((array) $o['two_factor_roles'], (array) $user->roles);
        if ($state['role_required']) {
            $state['required'] = true;
            $state['method'] = !empty($o['two_factor_method']) ? $o['two_factor_method'] : 'email';
            return $state;
        }
        if ($state['completed'] && empty($state['override_exists'])) {
            // Legacy profile-enabled users from earlier So SSL versions remain protected until an admin chooses an explicit profile override.
            $state['required'] = true;
            $state['method'] = 'totp';
        }
        return $state;
    }

    private function user_needs_mandatory_totp_setup($user_id) {
        $user = get_user_by('id', $user_id);
        if (!$user || empty($user->roles)) { return false; }
        $state = $this->get_user_2fa_state($user);
        return !empty($state['required']) && $state['method'] === 'totp' && empty($state['completed']);
    }

    private function mandatory_totp_setup_url() {
        return add_query_arg('so_ssl_setup_2fa', '1', self_admin_url('profile.php#so-ssl-2fa-setup'));
    }

    public function mandatory_totp_login_redirect($redirect_to, $requested_redirect_to, $user) {
        if ($user instanceof WP_User && $this->user_needs_mandatory_totp_setup($user->ID)) {
            return $this->mandatory_totp_setup_url();
        }
        return $redirect_to;
    }

    public function enforce_mandatory_totp_setup() {
        if (!is_user_logged_in() || !get_current_user_id()) { return; }
        if (!$this->user_needs_mandatory_totp_setup(get_current_user_id())) { return; }
        if (defined('DOING_AJAX') && DOING_AJAX) { return; }
        if (defined('DOING_CRON') && DOING_CRON) { return; }
        global $pagenow;
        $allowed_pages = array('profile.php', 'user-edit.php', 'admin-ajax.php', 'admin-post.php');
        if (in_array($pagenow, $allowed_pages, true)) { return; }
        wp_safe_redirect($this->mandatory_totp_setup_url());
        exit;
    }

    public function user_2fa_fields($user) {
        if (!current_user_can('edit_user', $user->ID)) { return; }
        $secret = get_user_meta($user->ID, 'so_ssl_totp_secret', true);
        if (!$secret) {
            $secret = So_SSL_TOTP::base32_secret();
            update_user_meta($user->ID, 'so_ssl_totp_secret', $secret);
        }
        $enabled = (int) get_user_meta($user->ID, 'so_ssl_2fa_enabled', true);
        $state = $this->get_user_2fa_state($user);
        $needs_setup = $this->user_needs_mandatory_totp_setup($user->ID);
        $is_own_profile = ((int) $user->ID === get_current_user_id());
        $is_admin_manager = current_user_can('manage_options');
        if (!$state['master_enabled'] && !$is_admin_manager) {
            return;
        }
        if (!$enabled && !$needs_setup && !$is_admin_manager) {
            return;
        }
        $issuer = wp_parse_url(home_url(), PHP_URL_HOST);
        if (!$issuer) { $issuer = sanitize_text_field(get_bloginfo('name')); }
        $uri = So_SSL_TOTP::provisioning_uri($user->user_login, $secret, $issuer);
        echo wp_kses('<h2 id="so-ssl-2fa-setup">So SSL Authenticator App 2FA ' . $this->info_icon('user_2fa') . '</h2>', $this->allowed_html());
        if (!$state['master_enabled']) {
            echo '<table class="form-table so-ssl-2fa-profile"><tr><th>Status</th><td><strong class="so-ssl-status-disabled">Globally disabled</strong><p class="description">So SSL two-factor authentication is turned off in the plugin settings. Profile 2FA controls are disabled until the master 2FA toggle is enabled.</p></td></tr></table>';
            return;
        }
        if ($needs_setup && $is_own_profile) {
            echo '<div class="notice notice-warning inline"><p><strong>So SSL requires authenticator app setup before continuing.</strong> Scan the QR code or enter the setup key below, type the current 6-digit code from your authenticator app, then save your profile to complete setup.</p></div>';
        }
        if ($state['override'] === 'disabled') {
            $status_markup = '<strong class="so-ssl-status-disabled">Disabled by profile override</strong>';
            $status_note = 'Two-factor login is disabled for this user by an administrator profile override.';
        } elseif ($enabled) {
            $status_markup = '<strong class="so-ssl-status-enabled">Enabled</strong>';
            $status_note = 'Authenticator app 2FA is active for this user.';
        } elseif ($needs_setup) {
            $status_markup = '<strong class="so-ssl-status-disabled">Required - setup incomplete</strong>';
            $status_note = 'This user is required by role to complete authenticator app setup before continuing.';
        } else {
            $status_markup = '<strong class="so-ssl-status-disabled">Not enabled</strong>';
            $status_note = 'Authenticator app 2FA is not active for this user.';
        }
        $codes = get_user_meta($user->ID, 'so_ssl_2fa_backup_codes', true);
        $backup_count = is_array($codes) ? count($codes) : 0;
        $backup_note = $enabled ? ' Backup recovery codes available: ' . $backup_count . '.' : ' Backup recovery codes are created after authenticator setup is completed.';
        echo '<table class="form-table so-ssl-2fa-profile"><tr><th>Status</th><td>' . wp_kses($status_markup, $this->allowed_html()) . '<p class="description">' . esc_html($status_note . $backup_note) . '</p></td></tr>';
        echo '<tr><th>Setup key</th><td><p>Enter this key manually in Google Authenticator, Microsoft Authenticator, Authy, 1Password, Bitwarden, or another TOTP app.</p><code class="so-ssl-setup-key">' . esc_html($secret) . '</code><p class="description">Keep this key private. Regenerate it if it may have been exposed.</p></td></tr>';
        echo '<tr><th>QR code</th><td><div id="so-ssl-2fa-qr" class="so-ssl-2fa-qr" data-otpauth="' . esc_attr($uri) . '"></div><p class="description">Scan this QR code with your authenticator app. When supported by the app, the entry will show this website domain so users can identify which site the code belongs to.</p></td></tr>';
        echo '<tr><th>Authenticator app links</th><td>' . wp_kses($this->authenticator_app_links_dropdown(), $this->allowed_html()) . '<p class="description">These links open official app or help pages when a public link is available. Any TOTP-compatible authenticator app should work.</p></td></tr>';
        echo '<tr><th>Authenticator URI</th><td><textarea readonly rows="3" class="large-text code">' . esc_textarea($uri) . '</textarea><p class="description">Some authenticator apps can import this otpauth URI directly. The URI includes the website domain as the issuer/account label when available.</p></td></tr>';
        $plain_codes = get_transient('so_ssl_2fa_backup_codes_' . $user->ID . '_' . get_current_user_id());
        if ($is_own_profile && is_array($plain_codes) && !empty($plain_codes)) {
            delete_transient('so_ssl_2fa_backup_codes_' . $user->ID . '_' . get_current_user_id());
            echo '<tr><th>Your new backup codes</th><td><div class="notice notice-warning inline"><p><strong>Save these codes now.</strong> They are one-time recovery codes and will not be shown again.</p><pre class="so-ssl-backup-codes">' . esc_html(implode("\n", $plain_codes)) . '</pre><p class="description">Use one of these codes on the So SSL two-factor login screen if your authenticator app is unavailable.</p>' . wp_kses($this->backup_codes_email_offer($user->ID), $this->allowed_html()) . '</div></td></tr>';
        }
        if ($needs_setup && $is_own_profile && !current_user_can('manage_options')) {
            echo '<tr><th>Complete required setup</th><td><input type="hidden" name="so_ssl_2fa_self_enable" value="1"><p><label>Current 6-digit authenticator code<br><input type="text" name="so_ssl_2fa_setup_code" value="" class="regular-text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9 ]{6,8}"></label></p><p class="description">Enter the current code from your authenticator app and save your profile to activate required 2FA. Backup recovery codes will be generated and shown once after setup succeeds.</p></td></tr>';
        }
        if ($is_own_profile && $enabled && !current_user_can('manage_options')) {
            echo '<tr><th>Backup recovery codes</th><td><label><input type="checkbox" name="so_ssl_2fa_generate_own_backup_codes" value="1"> Generate new one-time backup codes for my account.</label><p class="description">New codes replace any existing backup codes and will be shown once after saving your profile.</p></td></tr>';
        }
        if ($is_admin_manager) {
            echo '<tr><th>Profile 2FA override</th><td>';
            echo '<label><input type="radio" name="so_ssl_2fa_override" value="default" ' . checked($state['override'], 'default', false) . '> Use default role-based setting</label><br>';
            echo '<label><input type="radio" name="so_ssl_2fa_override" value="enabled" ' . checked($state['override'], 'enabled', false) . '> Enable authenticator app 2FA for this user</label><br>';
            echo '<label><input type="radio" name="so_ssl_2fa_override" value="disabled" ' . checked($state['override'], 'disabled', false) . '> Disable 2FA for this user</label>';
            echo '<p><label>Current 6-digit code<br><input type="text" name="so_ssl_2fa_setup_code" value="" class="regular-text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9 ]{6,8}"></label></p><p class="description">This profile override works even when role-based 2FA is not enabled. A valid code is required the first time authenticator app 2FA is enabled for this user or after regenerating the secret.</p></td></tr>';
            echo '<tr><th>Reset setup key</th><td><label><input type="checkbox" name="so_ssl_2fa_reset_secret" value="1"> Generate a new setup key and disable 2FA for this user.</label><p class="description">Use this if the authenticator app was lost or the setup key was exposed.</p></td></tr>';
            echo '<tr><th>Backup recovery codes</th><td><label><input type="checkbox" name="so_ssl_2fa_generate_backup_codes" value="1"> Generate new one-time backup codes for this user.</label><p class="description">Backup codes can be used at the two-factor login screen if the authenticator app is unavailable. New codes replace any existing backup codes.</p></td></tr>';
            $manager_plain_codes = get_transient('so_ssl_2fa_backup_codes_' . $user->ID . '_' . get_current_user_id());
            if (!$is_own_profile && is_array($manager_plain_codes) && !empty($manager_plain_codes)) {
                delete_transient('so_ssl_2fa_backup_codes_' . $user->ID . '_' . get_current_user_id());
                echo '<tr><th>New backup codes</th><td><div class="notice notice-warning inline"><p><strong>Save these codes now.</strong> They will not be shown again.</p><pre class="so-ssl-backup-codes">' . esc_html(implode("\n", $manager_plain_codes)) . '</pre>' . wp_kses($this->backup_codes_email_offer($user->ID), $this->allowed_html()) . '</div></td></tr>';
            }
        }
        echo '</table>';
    }

    private function backup_codes_email_transient_key($user_id) {
        return 'so_ssl_2fa_backup_codes_email_' . (int) $user_id . '_' . get_current_user_id();
    }

    private function store_new_backup_codes_for_display_and_email($user_id, $plain_codes) {
        set_transient('so_ssl_2fa_backup_codes_' . (int) $user_id . '_' . get_current_user_id(), $plain_codes, 5 * MINUTE_IN_SECONDS);
        set_transient($this->backup_codes_email_transient_key($user_id), $plain_codes, 15 * MINUTE_IN_SECONDS);
    }

    private function backup_codes_email_offer($user_id) {
        $user = get_user_by('id', $user_id);
        if (!$user || empty($user->user_email)) { return ''; }
        if (!get_transient($this->backup_codes_email_transient_key($user_id))) { return ''; }
        $action = esc_url(admin_url('admin-post.php'));
        $nonce = wp_create_nonce('so_ssl_email_backup_codes_' . (int) $user_id);
        $email = esc_html($user->user_email);
        return '<div class="so-ssl-backup-email-offer" style="margin-top:12px;">'
            . '<input type="hidden" name="so_ssl_email_backup_codes_user_id" value="' . esc_attr($user_id) . '">'
            . '<input type="hidden" name="so_ssl_email_backup_codes_nonce" value="' . esc_attr($nonce) . '">'
            . '<p><button type="submit" class="button" formaction="' . $action . '" formmethod="post" name="action" value="so_ssl_email_backup_codes">Email these backup codes to the registered email</button></p>'
            . '<p class="description">Optional: sends this newly generated backup code set to ' . $email . '. This option is only available right after new codes are generated.</p>'
            . '</div>';
    }

    public function email_backup_codes() {
        $user_id = isset($_POST['so_ssl_email_backup_codes_user_id']) ? absint(wp_unslash($_POST['so_ssl_email_backup_codes_user_id'])) : 0;
        if (!$user_id || !current_user_can('edit_user', $user_id)) {
            wp_die(esc_html__('You do not have permission to email these backup codes.', 'so-ssl'));
        }
        $nonce = isset($_POST['so_ssl_email_backup_codes_nonce']) ? sanitize_text_field(wp_unslash($_POST['so_ssl_email_backup_codes_nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'so_ssl_email_backup_codes_' . $user_id)) {
            wp_die(esc_html__('The backup code email request could not be verified.', 'so-ssl'));
        }
        $codes = get_transient($this->backup_codes_email_transient_key($user_id));
        if (!is_array($codes) || empty($codes)) {
            set_transient('so_ssl_2fa_error_' . get_current_user_id(), 'The newly generated backup codes are no longer available to email. Generate a new set if needed.', 60);
            wp_safe_redirect(wp_get_referer() ? wp_get_referer() : self_admin_url('profile.php'));
            exit;
        }
        $user = get_user_by('id', $user_id);
        if (!$user || empty($user->user_email)) {
            set_transient('so_ssl_2fa_error_' . get_current_user_id(), 'The selected user does not have a registered email address for backup code delivery.', 60);
            wp_safe_redirect(wp_get_referer() ? wp_get_referer() : self_admin_url('profile.php'));
            exit;
        }
        $site = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
        $subject = sprintf('[%s] So SSL backup recovery codes', $site);
        $message = "Your new So SSL backup recovery codes are below. Each code can be used one time if your authenticator app is unavailable.

";
        $message .= implode("
", array_map('sanitize_text_field', $codes));
        $message .= "

Save these codes somewhere safe. If you did not request these codes, contact the site administrator and change your password.

Site: " . home_url();
        $sent = wp_mail($user->user_email, $subject, $message);
        if ($sent) {
            delete_transient($this->backup_codes_email_transient_key($user_id));
            set_transient('so_ssl_2fa_notice_' . get_current_user_id(), 'The newly generated So SSL backup recovery codes were emailed to the registered email address.', 60);
        } else {
            set_transient('so_ssl_2fa_error_' . get_current_user_id(), 'WordPress could not email the backup recovery codes. Please save them manually or try again.', 60);
        }
        wp_safe_redirect(wp_get_referer() ? wp_get_referer() : self_admin_url('profile.php'));
        exit;
    }

    public function save_user_2fa_fields($user_id) {
        if (!current_user_can('edit_user', $user_id)) { return; }
        if (!$this->profile_update_nonce_valid($user_id)) { return; }

        $is_admin_manager = current_user_can('manage_options');
        $is_own_profile = ((int) $user_id === get_current_user_id());
        $was_enabled = (int) get_user_meta($user_id, 'so_ssl_2fa_enabled', true);

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- The profile/update-user nonce is verified above by profile_update_nonce_valid().
        if ($is_admin_manager && isset($_POST['so_ssl_2fa_reset_secret'])) {
            update_user_meta($user_id, 'so_ssl_totp_secret', So_SSL_TOTP::base32_secret());
            update_user_meta($user_id, 'so_ssl_2fa_enabled', 0);
            update_user_meta($user_id, 'so_ssl_2fa_override', 'disabled');
            delete_user_meta($user_id, 'so_ssl_2fa_backup_codes');
            set_transient('so_ssl_2fa_notice_' . get_current_user_id(), 'The So SSL 2FA setup key was regenerated and 2FA was disabled for that user.', 60);
            return;
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- The profile/update-user nonce is verified above by profile_update_nonce_valid().
        if ($is_admin_manager && isset($_POST['so_ssl_2fa_generate_backup_codes'])) {
            $plain_codes = $this->generate_backup_codes($user_id);
            $this->store_new_backup_codes_for_display_and_email($user_id, $plain_codes);
            set_transient('so_ssl_2fa_notice_' . get_current_user_id(), 'New So SSL backup recovery codes were generated. Save them from the profile page now; they will not be shown again.', 60);
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- The profile/update-user nonce is verified above by profile_update_nonce_valid().
        if (!$is_admin_manager && $is_own_profile && $was_enabled && isset($_POST['so_ssl_2fa_generate_own_backup_codes'])) {
            $plain_codes = $this->generate_backup_codes($user_id);
            $this->store_new_backup_codes_for_display_and_email($user_id, $plain_codes);
            set_transient('so_ssl_2fa_notice_' . get_current_user_id(), 'New So SSL backup recovery codes were generated. Save them from your profile page now; they will not be shown again.', 60);
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- The profile/update-user nonce is verified above by profile_update_nonce_valid().
        $admin_override = $is_admin_manager && isset($_POST['so_ssl_2fa_override']) ? sanitize_key(wp_unslash($_POST['so_ssl_2fa_override'])) : null;
        if ($is_admin_manager && !in_array($admin_override, array('default', 'enabled', 'disabled'), true)) {
            $admin_override = 'default';
        }
        if ($is_admin_manager && $admin_override !== null) {
            update_user_meta($user_id, 'so_ssl_2fa_override', $admin_override);
            if ($admin_override === 'disabled') {
                update_user_meta($user_id, 'so_ssl_2fa_enabled', 0);
                set_transient('so_ssl_2fa_notice_' . get_current_user_id(), 'So SSL 2FA was disabled for that user by profile override.', 60);
                return;
            }
            if ($admin_override === 'default') {
                set_transient('so_ssl_2fa_notice_' . get_current_user_id(), 'So SSL 2FA override was reset to the role-based default for that user.', 60);
                return;
            }
        }

        $admin_wants_enabled = ($is_admin_manager && $admin_override === 'enabled');
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- The profile/update-user nonce is verified above by profile_update_nonce_valid().
        $self_wants_enabled = (!$is_admin_manager && $is_own_profile && isset($_POST['so_ssl_2fa_self_enable']) && $this->user_needs_mandatory_totp_setup($user_id));

        if (!$admin_wants_enabled && !$self_wants_enabled) {
            return;
        }

        $secret = get_user_meta($user_id, 'so_ssl_totp_secret', true);
        if (!$secret) {
            $secret = So_SSL_TOTP::base32_secret();
            update_user_meta($user_id, 'so_ssl_totp_secret', $secret);
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- The profile/update-user nonce is verified above by profile_update_nonce_valid().
        $code = isset($_POST['so_ssl_2fa_setup_code']) ? sanitize_text_field(wp_unslash($_POST['so_ssl_2fa_setup_code'])) : '';
        if ($was_enabled || ($code && So_SSL_TOTP::verify($secret, $code))) {
            update_user_meta($user_id, 'so_ssl_2fa_enabled', 1);
            if (!$was_enabled) {
                if ($is_own_profile) {
                    $plain_codes = $this->generate_backup_codes($user_id);
                    $this->store_new_backup_codes_for_display_and_email($user_id, $plain_codes);
                    set_transient('so_ssl_2fa_notice_' . get_current_user_id(), 'Authenticator app 2FA was enabled. Save your new backup recovery codes from this profile page now; they will not be shown again.', 60);
                } else {
                    set_transient('so_ssl_2fa_notice_' . get_current_user_id(), 'Authenticator app 2FA was enabled for the user.', 60);
                }
            }
            return;
        }
        set_transient('so_ssl_2fa_error_' . get_current_user_id(), 'So SSL did not enable 2FA because the verification code was missing or invalid.', 60);
    }

    public function two_factor_admin_notice() {
        $uid = get_current_user_id();
        if (!$uid) { return; }
        $error = get_transient('so_ssl_2fa_error_' . $uid);
        if ($error) {
            delete_transient('so_ssl_2fa_error_' . $uid);
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($error) . '</p></div>';
        }
        $notice = get_transient('so_ssl_2fa_notice_' . $uid);
        if ($notice) {
            delete_transient('so_ssl_2fa_notice_' . $uid);
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($notice) . '</p></div>';
        }
    }

    private function two_factor_login_key($login) {
        return 'so_ssl_2fa_login_' . wp_hash(sanitize_user($login));
    }

    public function two_factor_authenticate($user, $username, $password) {
        if (defined('SO_SSL_DISABLE_2FA') && SO_SSL_DISABLE_2FA) { return $user; }
        if (is_wp_error($user) || !$user instanceof WP_User) { return $user; }
        $o = $this->options();
        $state = $this->get_user_2fa_state($user);
        if (empty($state['required'])) { return $user; }
        $method = $state['method'];
        if ($method === 'totp') {
            $secret = get_user_meta($user->ID, 'so_ssl_totp_secret', true);
            if (!$secret) {
                $secret = So_SSL_TOTP::base32_secret();
                update_user_meta($user->ID, 'so_ssl_totp_secret', $secret);
            }
            if (empty($state['completed'])) {
                // Allow the password login to complete, then force the user to their profile setup screen.
                return $user;
            }
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Login redirect is read-only and validated before redirecting.
        $redirect_to = isset($_REQUEST['redirect_to']) ? esc_url_raw(wp_unslash($_REQUEST['redirect_to'])) : admin_url();
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- rememberme is read during the authenticated login flow before the second-factor challenge is displayed.
        set_transient($this->two_factor_login_key($user->user_login), array('user_id' => $user->ID, 'remember' => isset($_POST['rememberme']), 'redirect_to' => $redirect_to, 'method' => $method), 10 * MINUTE_IN_SECONDS);
        if ($method === 'email') {
            $code = wp_rand(100000, 999999);
            set_transient('so_ssl_2fa_email_' . $user->ID, wp_hash_password($code), 10 * MINUTE_IN_SECONDS);
            wp_mail($user->user_email, 'Your So SSL login code', 'Your login verification code is: ' . $code);
        }
        wp_safe_redirect(add_query_arg(array('action' => 'so_ssl_2fa', 'login' => rawurlencode($user->user_login)), wp_login_url()));
        exit;
    }

    private function login_2fa_nonce_valid() {
        $login = isset($_POST['log']) ? sanitize_user(wp_unslash($_POST['log'])) : '';
        if ('' === $login || !isset($_POST['so_ssl_2fa_nonce'])) {
            return false;
        }
        $nonce = sanitize_text_field(wp_unslash($_POST['so_ssl_2fa_nonce']));
        return (bool) wp_verify_nonce($nonce, 'so_ssl_2fa_login_' . $login);
    }

    public function two_factor_form() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Login is read-only here to load the pending 2FA challenge; POST actions verify the form nonce before changing state.
        $login = isset($_GET['login']) ? sanitize_user(wp_unslash($_GET['login'])) : '';
        $error = '';
        $notice = '';
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- This branch only detects the requested action; login_2fa_nonce_valid() verifies the nonce before processing it.
        if (isset($_POST['so_ssl_2fa_send_fallback'])) {
            if (!$this->login_2fa_nonce_valid()) {
                $error = 'The verification request could not be confirmed. Please try again.';
            } else {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified above by login_2fa_nonce_valid().
            $login = isset($_POST['log']) ? sanitize_user(wp_unslash($_POST['log'])) : '';
            $user = get_user_by('login', $login);
            $session = get_transient($this->two_factor_login_key($login));
            $o = $this->options();
            $method = is_array($session) && !empty($session['method']) ? $session['method'] : (!empty($o['two_factor_method']) ? $o['two_factor_method'] : 'email');
            if ($user && is_array($session) && (int) ($session['user_id'] ?? 0) === (int) $user->ID && $method !== 'email' && !empty($o['two_factor_email_fallback'])) {
                $code = wp_rand(100000, 999999);
                $sent = wp_mail($user->user_email, 'Your So SSL fallback login code', 'Your fallback login verification code is: ' . $code . "\n\nThis code expires after 10 minutes. If you did not request it, change your password and review your account security.");
                if ($sent) {
                    set_transient('so_ssl_2fa_email_fallback_' . $user->ID, wp_hash_password($code), 10 * MINUTE_IN_SECONDS);
                    $notice = 'A fallback verification code was emailed. Enter that emailed fallback code below. Check your spam or junk folder if it does not arrive.';
                    $this->log_2fa_event($user->ID, $user->user_login, 'email_fallback', 'sent', 'fallback_code_requested');
                } else {
                    $error = 'WordPress could not send the fallback email. Please try again or contact the site administrator.';
                    $this->log_2fa_event($user->ID, $user->user_login, 'email_fallback', 'failed', 'mail_send_failed');
                }
            } else {
                $error = 'Unable to send a fallback code for this login session.';
            }
        }
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- This branch only detects the requested action; login_2fa_nonce_valid() verifies the nonce before processing it.
        if (isset($_POST['so_ssl_2fa_submit'])) {
            if (!$this->login_2fa_nonce_valid()) {
                $error = 'The verification request could not be confirmed. Please try again.';
            } else {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified above by login_2fa_nonce_valid().
            $login = isset($_POST['log']) ? sanitize_user(wp_unslash($_POST['log'])) : '';
            $user = get_user_by('login', $login);
            $session = get_transient($this->two_factor_login_key($login));
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified above by login_2fa_nonce_valid().
            $code = isset($_POST['so_ssl_2fa_code']) ? sanitize_text_field(wp_unslash($_POST['so_ssl_2fa_code'])) : '';
            if (!$user || !is_array($session) || empty($session['user_id']) || (int) $session['user_id'] !== (int) $user->ID) {
                $error = 'Your verification session expired. Please sign in again.';
                $this->log_2fa_event(0, $login, 'unknown', 'failed', 'expired_session');
            } else {
                $o = $this->options();
                $ok = false;
                $used_method = !empty($session['method']) ? $session['method'] : $o['two_factor_method'];
                if ($used_method === 'email') {
                    $hash = get_transient('so_ssl_2fa_email_' . $user->ID);
                    $ok = $hash && wp_check_password($code, $hash);
                    $used_method = 'email';
                } else {
                    $secret = get_user_meta($user->ID, 'so_ssl_totp_secret', true);
                    $ok = $secret && So_SSL_TOTP::verify($secret, $code);
                    $used_method = 'totp';
                    if (!$ok && $this->verify_backup_code($user->ID, $code)) {
                        $ok = true;
                        $used_method = 'backup_code';
                    }
                    if (!$ok && !empty($o['two_factor_email_fallback'])) {
                        $hash = get_transient('so_ssl_2fa_email_fallback_' . $user->ID);
                        if ($hash && wp_check_password($code, $hash)) {
                            $ok = true;
                            $used_method = 'email_fallback';
                        }
                    }
                }
                if ($ok) {
                    delete_transient('so_ssl_2fa_email_' . $user->ID);
                    delete_transient('so_ssl_2fa_email_fallback_' . $user->ID);
                    delete_transient($this->two_factor_login_key($login));
                    $this->log_2fa_event($user->ID, $user->user_login, $used_method, 'success', 'verified');
                    wp_set_auth_cookie($user->ID, !empty($session['remember']));
                    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- This is the core WordPress login action after manual 2FA completion.
                    do_action('wp_login', $user->user_login, $user);
                    $redirect_to = !empty($session['redirect_to']) ? $session['redirect_to'] : admin_url();
                    wp_safe_redirect(wp_validate_redirect($redirect_to, admin_url()));
                    exit;
                }
                $error = 'Invalid verification code.';
                $this->log_2fa_event($user->ID, $user->user_login, $o['two_factor_method'], 'failed', 'invalid_code');
            }
            }
        }
        $o = $this->options();
        $method = !empty($o['two_factor_method']) ? $o['two_factor_method'] : 'email';
        $fallback_active = false;
        if ($login !== '') {
            $fallback_user = get_user_by('login', $login);
            $fallback_session = get_transient($this->two_factor_login_key($login));
            if ($fallback_user && is_array($fallback_session) && (int) ($fallback_session['user_id'] ?? 0) === (int) $fallback_user->ID) {
                $method = !empty($fallback_session['method']) ? $fallback_session['method'] : $method;
                if ($method !== 'email') {
                    $fallback_active = (bool) get_transient('so_ssl_2fa_email_fallback_' . $fallback_user->ID);
                }
            }
        }
        if ($method === 'email') {
            $code_label = 'Email verification code';
            $help_title = 'Check your email for the So SSL verification code.';
            $help_text = 'Enter the 6-digit code sent to the email address on your WordPress account. The code expires after 10 minutes. If you do not receive it, check your spam or junk folder, then return to login and sign in again to send a new code.';
        } elseif ($fallback_active) {
            $code_label = 'Authenticator, backup, or fallback email code';
            $help_title = 'Enter your authenticator app code, backup code, or emailed fallback code.';
            $help_text = 'Your authenticator app code still works on this screen. You may also enter a one-time backup recovery code or the 6-digit fallback code emailed to your WordPress account. The emailed fallback code expires after 10 minutes. Check your spam or junk folder if it does not arrive.';
        } else {
            $code_label = 'Authenticator app code';
            $help_title = 'Open your authenticator app for the current code.';
            $help_text = 'Enter the current 6-digit code from your authenticator app. You may also enter a one-time backup recovery code. If email fallback is enabled, you can request a temporary email code from this screen.';
        }
        login_header('Two-Factor Authentication');
        if ($error) { echo '<div id="login_error">' . esc_html($error) . '</div>'; }
        if ($notice) { echo '<div class="message so-ssl-2fa-notice"><p>' . esc_html($notice) . '</p></div>'; }
        echo '<form name="so_ssl_2faform" id="loginform" action="' . esc_url(site_url('wp-login.php?action=so_ssl_2fa', 'login_post')) . '" method="post">';
        echo '<div class="message so-ssl-2fa-login-message"><p><strong>' . esc_html($help_title) . '</strong></p><p>' . esc_html($help_text) . '</p></div>';
        echo '<p><label>' . esc_html($code_label) . '<br><input type="text" name="so_ssl_2fa_code" class="input" size="20" inputmode="numeric" autocomplete="one-time-code" pattern="[A-Za-z0-9 -]{6,20}" autofocus></label></p>';
        echo '<input type="hidden" name="log" value="' . esc_attr($login) . '">';
        wp_nonce_field('so_ssl_2fa_login_' . $login, 'so_ssl_2fa_nonce');
        echo '<p class="submit"><input type="submit" name="so_ssl_2fa_submit" class="button button-primary button-large" value="Verify"></p>';
        if ($method !== 'email' && !empty($o['two_factor_email_fallback'])) {
            echo '<p><button type="submit" name="so_ssl_2fa_send_fallback" value="1" class="button">Email me a fallback code</button></p><p class="description">Use this only if you cannot access your authenticator app. After the email sends, this form will accept the emailed fallback code. Check your spam or junk folder if the code does not arrive.</p>';
        }
        echo '<p class="so-ssl-login-help"><a href="' . esc_url(wp_login_url()) . '">Return to login</a></p></form>';
        login_footer();
        exit;
    }

    private function generate_backup_codes($user_id, $count = 10) {
        $plain_codes = array();
        $hashed_codes = array();
        for ($i = 0; $i < $count; $i++) {
            $raw = strtoupper(wp_generate_password(10, false, false));
            $code = substr($raw, 0, 5) . '-' . substr($raw, 5, 5);
            $plain_codes[] = $code;
            $hashed_codes[] = wp_hash_password(str_replace('-', '', $code));
        }
        update_user_meta($user_id, 'so_ssl_2fa_backup_codes', $hashed_codes);
        return $plain_codes;
    }

    private function verify_backup_code($user_id, $code) {
        $normalized = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string) $code));
        if ($normalized === '') { return false; }
        $codes = get_user_meta($user_id, 'so_ssl_2fa_backup_codes', true);
        if (!is_array($codes) || empty($codes)) { return false; }
        foreach ($codes as $index => $hash) {
            if (wp_check_password($normalized, $hash)) {
                unset($codes[$index]);
                update_user_meta($user_id, 'so_ssl_2fa_backup_codes', array_values($codes));
                return true;
            }
        }
        return false;
    }

    private function log_2fa_event($user_id, $login, $method, $result, $reason) {
        $o = $this->options();
        if (empty($o['two_factor_log_failures'])) { return; }
        $log = get_option('so_ssl_2fa_log', array());
        if (!is_array($log)) { $log = array(); }
        array_unshift($log, array(
            'time' => time(),
            'user_id' => absint($user_id),
            'login' => sanitize_user($login),
            'method' => sanitize_text_field($method),
            'result' => sanitize_text_field($result),
            'reason' => sanitize_text_field($reason),
            'ip' => $this->client_ip(),
        ));
        $log = array_slice($log, 0, 100);
        update_option('so_ssl_2fa_log', $log, false);
    }

    public function two_factor_recovery_page() {
        if (!current_user_can('manage_options')) { return; }
        $message = '';
        $plain_codes = array();
        if (isset($_POST['so_ssl_2fa_recovery_action']) && check_admin_referer('so_ssl_2fa_recovery')) {
            $uid = isset($_POST['user_id']) ? absint(wp_unslash($_POST['user_id'])) : 0;
            $user = $uid ? get_user_by('id', $uid) : false;
            if ($user) {
                $recovery_action = isset($_POST['so_ssl_2fa_recovery_action']) ? sanitize_key(wp_unslash($_POST['so_ssl_2fa_recovery_action'])) : '';
                if ($recovery_action === 'reset') {
                    update_user_meta($uid, 'so_ssl_totp_secret', So_SSL_TOTP::base32_secret());
                    update_user_meta($uid, 'so_ssl_2fa_enabled', 0);
                    delete_user_meta($uid, 'so_ssl_2fa_backup_codes');
                    $message = 'Authenticator setup was reset and disabled for ' . $user->user_login . '.';
                } elseif ($recovery_action === 'backup') {
                    $plain_codes = $this->generate_backup_codes($uid);
                    $message = 'New backup codes were generated for ' . $user->user_login . '. Save them now; they will not be shown again.';
                }
            }
        }
        $users = get_users(array('number' => 50, 'orderby' => 'registered', 'order' => 'DESC'));
        echo wp_kses('<div class="wrap so-ssl-admin"><h1>So SSL 2FA Recovery ' . $this->info_icon('two_factor_recovery') . '</h1>', $this->allowed_html());
        if ($message) { echo '<div class="notice notice-success"><p>' . esc_html($message) . '</p></div>'; }
        if (!empty($plain_codes)) { echo '<div class="notice notice-warning"><p><strong>Save these backup codes now.</strong> They will not be shown again.</p><pre class="so-ssl-backup-codes">' . esc_html(implode("\n", $plain_codes)) . '</pre></div>'; }
        echo '<p>Use this page to help users who lost access to their authenticator app. Resetting setup disables app 2FA until it is configured again.</p>';
        echo '<table class="widefat"><thead><tr><th>User</th><th>Email</th><th>2FA Status</th><th>Backup Codes Left</th><th>Actions</th></tr></thead><tbody>';
        foreach ($users as $u) {
            $enabled = (int) get_user_meta($u->ID, 'so_ssl_2fa_enabled', true);
            $codes = get_user_meta($u->ID, 'so_ssl_2fa_backup_codes', true);
            $code_count = is_array($codes) ? count($codes) : 0;
            echo '<tr><td>' . esc_html($u->display_name) . ' <code>' . esc_html($u->user_login) . '</code></td><td>' . esc_html($u->user_email) . '</td><td>' . ($enabled ? '<strong class="so-ssl-status-enabled">Enabled</strong>' : '<span class="so-ssl-status-disabled">Not enabled</span>') . '</td><td>' . esc_html($code_count) . '</td><td><form method="post" style="display:inline-block;margin-right:8px;">';
            wp_nonce_field('so_ssl_2fa_recovery');
            echo '<input type="hidden" name="user_id" value="' . esc_attr($u->ID) . '"><button class="button" name="so_ssl_2fa_recovery_action" value="backup">Generate Backup Codes</button></form><form method="post" style="display:inline-block;">';
            wp_nonce_field('so_ssl_2fa_recovery');
            echo '<input type="hidden" name="user_id" value="' . esc_attr($u->ID) . '"><button class="button" name="so_ssl_2fa_recovery_action" value="reset" onclick="return confirm(\'Reset and disable authenticator app 2FA for this user?\');">Reset 2FA Setup</button></form></td></tr>';
        }
        echo '</tbody></table></div>';
    }

    public function two_factor_logs_page() {
        if (!current_user_can('manage_options')) { return; }
        if (isset($_POST['so_ssl_clear_2fa_log']) && check_admin_referer('so_ssl_clear_2fa_log')) {
            delete_option('so_ssl_2fa_log');
            echo '<div class="notice notice-success"><p>2FA log cleared.</p></div>';
        }
        $log = get_option('so_ssl_2fa_log', array());
        if (!is_array($log)) { $log = array(); }
        echo wp_kses('<div class="wrap so-ssl-admin"><h1>So SSL 2FA Logs ' . $this->info_icon('two_factor_logs') . '</h1><p>Recent two-factor verification attempts are listed below.</p>', $this->allowed_html());
        echo '<form method="post">'; wp_nonce_field('so_ssl_clear_2fa_log'); echo '<p><button class="button" name="so_ssl_clear_2fa_log" value="1">Clear 2FA Log</button></p></form>';
        echo '<table class="widefat"><thead><tr><th>Time</th><th>Login</th><th>Method</th><th>Result</th><th>Reason</th><th>IP</th></tr></thead><tbody>';
        if (empty($log)) {
            echo '<tr><td colspan="6">No 2FA events recorded yet.</td></tr>';
        } else {
            foreach ($log as $entry) {
                echo '<tr><td>' . esc_html(!empty($entry['time']) ? wp_date('Y-m-d H:i:s', absint($entry['time'])) : '') . '</td><td>' . esc_html($entry['login'] ?? '') . '</td><td>' . esc_html($entry['method'] ?? '') . '</td><td>' . esc_html($entry['result'] ?? '') . '</td><td>' . esc_html($entry['reason'] ?? '') . '</td><td>' . esc_html($entry['ip'] ?? '') . '</td></tr>';
            }
        }
        echo '</tbody></table></div>';
    }

    public function settings_page() {
        if (!current_user_can('manage_options')) { return; }
        $o = $this->options();
        $score = $this->security_score($o);
        $tabs = $this->settings_tabs();
        echo '<div class="wrap so-ssl-admin"><h1>So SSL <span class="so-ssl-version">v' . esc_html(SO_SSL_VERSION) . '</span></h1>';
        echo '<div class="so-ssl-settings-banner"><img src="' . esc_url(SO_SSL_URL . 'assets/banner-772x250.png') . '" alt="So SSL WordPress Security"></div>';
        if ($this->is_original_admin_account()) {
            echo '<div class="notice notice-warning is-dismissible so-ssl-admin-recovery-notice"><p><strong>Recommended:</strong> Create and secure a second administrator account for daily use, then keep the original administrator account reserved for emergency recovery and important site events.</p></div>';
        }
        echo '<div class="so-ssl-score"><strong>Security Score:</strong><span>' . esc_html($score) . '%</span></div>';
        echo '<h2 class="nav-tab-wrapper so-ssl-tabs" role="tablist" aria-label="So SSL settings sections">';
        foreach ($tabs as $tab_id => $tab_label) {
            $is_first = array_key_first($tabs) === $tab_id;
            echo '<a href="#so-ssl-tab-' . esc_attr($tab_id) . '" class="nav-tab' . ($is_first ? ' nav-tab-active' : '') . '" role="tab" aria-selected="' . ($is_first ? 'true' : 'false') . '" aria-controls="so-ssl-tab-' . esc_attr($tab_id) . '" data-so-ssl-tab="' . esc_attr($tab_id) . '">' . esc_html($tab_label) . '</a>';
        }
        echo '</h2>';
        echo '<form method="post" action="options.php">';
        settings_fields('so_ssl_group');
        if (empty($o['two_factor_master_enabled'])) {
            $this->hidden_preserve_2fa_options($o);
        }
        foreach ($tabs as $tab_id => $tab_label) {
            $is_first = array_key_first($tabs) === $tab_id;
            echo '<div id="so-ssl-tab-' . esc_attr($tab_id) . '" class="so-ssl-tab-panel' . ($is_first ? ' is-active' : '') . '" role="tabpanel" data-so-ssl-tab-panel="' . esc_attr($tab_id) . '">';
            do_settings_sections($this->settings_api_page($tab_id));
            echo '</div>';
        }
        submit_button('Save So SSL Settings');
        echo '</form></div>';
    }

    private function security_score($o) {
        $keys = array('force_ssl','enable_hsts','csp_enabled','privacy_enabled','admin_agreement_enabled','two_factor_master_enabled','strong_passwords','login_limit_enabled');
        $score = 0; foreach ($keys as $k) { if (!empty($o[$k])) { $score++; } }
        return round(($score / count($keys)) * 100);
    }

    private function section($title) { echo '<h2 class="so-ssl-section">' . esc_html($title) . '</h2>'; }
    private function settings_tabs() {
        return array(
            'ssl' => 'SSL',
            'headers' => 'Security Headers',
            'privacy' => 'Privacy',
            'admin-agreement' => 'Admin Agreement',
            'two-factor' => '2FA',
            'login' => 'Login Protection',
            'sessions' => 'Sessions',
        );
    }

    private function settings_api_page($tab_id) {
        return 'so_ssl_settings_' . sanitize_key($tab_id);
    }

    private function settings_api_tabs() {
        return array(
            'ssl' => array(
                'title' => 'SSL Enforcement',
                'fields' => array(
                    array('type' => 'checkbox', 'id' => 'force_ssl', 'label' => 'Force all traffic to use HTTPS/SSL'),
                    array('type' => 'checkbox', 'id' => 'block_search_indexing', 'label' => 'Block web search engines from indexing this site'),
                ),
            ),
            'headers' => array(
                'title' => 'Security Headers',
                'fields' => array(
                    array('type' => 'checkbox', 'id' => 'enable_hsts', 'label' => 'Enable HTTP Strict Transport Security (HSTS)'),
                    array('type' => 'number', 'id' => 'hsts_max_age', 'label' => 'HSTS Max Age'),
                    array('type' => 'checkbox', 'id' => 'hsts_include_subdomains', 'label' => 'Include subdomains in HSTS'),
                    array('type' => 'checkbox', 'id' => 'hsts_preload', 'label' => 'Enable HSTS preload flag'),
                    array('type' => 'select', 'id' => 'x_frame_options', 'label' => 'X-Frame-Options', 'choices' => array('' => 'Disabled', 'DENY' => 'DENY', 'SAMEORIGIN' => 'SAMEORIGIN')),
                    array('type' => 'select', 'id' => 'referrer_policy', 'label' => 'Referrer Policy', 'choices' => array('no-referrer' => 'no-referrer', 'same-origin' => 'same-origin', 'strict-origin' => 'strict-origin', 'strict-origin-when-cross-origin' => 'strict-origin-when-cross-origin', 'unsafe-url' => 'unsafe-url')),
                    array('type' => 'textarea', 'id' => 'permissions_policy', 'label' => 'Permissions Policy'),
                    array('type' => 'checkbox', 'id' => 'csp_enabled', 'label' => 'Enable Content Security Policy'),
                    array('type' => 'textarea', 'id' => 'csp_policy', 'label' => 'CSP Policy'),
                    array('type' => 'select', 'id' => 'coep', 'label' => 'Cross-Origin-Embedder-Policy', 'choices' => array('' => 'Disabled', 'require-corp' => 'require-corp', 'credentialless' => 'credentialless')),
                    array('type' => 'select', 'id' => 'coop', 'label' => 'Cross-Origin-Opener-Policy', 'choices' => array('' => 'Disabled', 'same-origin' => 'same-origin', 'same-origin-allow-popups' => 'same-origin-allow-popups', 'unsafe-none' => 'unsafe-none')),
                    array('type' => 'select', 'id' => 'corp', 'label' => 'Cross-Origin-Resource-Policy', 'choices' => array('' => 'Disabled', 'same-origin' => 'same-origin', 'same-site' => 'same-site', 'cross-origin' => 'cross-origin')),
                ),
            ),
            'privacy' => array(
                'title' => 'Privacy Compliance',
                'fields' => array(
                    array('type' => 'checkbox', 'id' => 'privacy_enabled', 'label' => 'Require privacy acknowledgment after login'),
                    array('type' => 'roles', 'id' => 'privacy_roles', 'label' => 'Roles required to acknowledge privacy notice'),
                    array('type' => 'number', 'id' => 'privacy_expiry_days', 'label' => 'Privacy re-acknowledgment expiry days'),
                    array('type' => 'text', 'id' => 'privacy_title', 'label' => 'Privacy modal title'),
                    array('type' => 'textarea', 'id' => 'privacy_text', 'label' => 'Privacy notice text'),
                    array('type' => 'text', 'id' => 'privacy_checkbox', 'label' => 'Privacy checkbox label'),
                    array('type' => 'html', 'html' => '<p><strong>Preview shortcode:</strong> <code>[so_ssl_privacy_acknowledgment]</code></p>'),
                ),
            ),
            'admin-agreement' => array(
                'title' => 'Administrator Agreement',
                'fields' => array(
                    array('type' => 'checkbox', 'id' => 'admin_agreement_enabled', 'label' => 'Require administrators to accept agreement before using plugin settings'),
                    array('type' => 'number', 'id' => 'admin_agreement_expiry_days', 'label' => 'Admin agreement expiry days'),
                    array('type' => 'textarea', 'id' => 'admin_agreement_text', 'label' => 'Administrator agreement text'),
                    array('type' => 'text', 'id' => 'admin_agreement_checkbox', 'label' => 'Administrator checkbox label'),
                    array('type' => 'checkbox', 'id' => 'admin_emergency_override', 'label' => 'Allow emergency override with ?so_ssl_emergency_override=1'),
                ),
            ),
            'two-factor' => array(
                'title' => 'Two-Factor Authentication',
                'fields' => array(
                    array('type' => 'checkbox', 'id' => 'two_factor_master_enabled', 'label' => 'Enable So SSL two-factor authentication system'),
                    array('type' => 'two_factor_disabled_note'),
                    array('type' => 'checkbox', 'id' => 'two_factor_enabled', 'label' => 'Enable role-based 2FA checks', 'bold' => true, 'class' => 'so-ssl-2fa-child-control'),
                    array('type' => 'roles', 'id' => 'two_factor_roles', 'label' => 'Roles requiring 2FA', 'bold' => false, 'class' => 'so-ssl-2fa-child-control'),
                    array('type' => 'html', 'class' => 'so-ssl-2fa-child-control', 'html' => '<p class="description">Role-based checks apply only to users using the default profile setting. The profile option <strong>Enable authenticator app 2FA for this user</strong> or <strong>Disable 2FA for this user</strong> overrides these role rules.</p>'),
                    array('type' => 'select', 'id' => 'two_factor_method', 'label' => 'Default 2FA Method', 'choices' => array('email' => 'Email verification code', 'totp' => 'Authenticator app / TOTP'), 'class' => 'so-ssl-2fa-child-control'),
                    array('type' => 'checkbox', 'id' => 'two_factor_email_fallback', 'label' => 'Allow email fallback codes for authenticator app logins', 'class' => 'so-ssl-2fa-child-control'),
                    array('type' => 'checkbox', 'id' => 'two_factor_log_failures', 'label' => 'Log 2FA verification attempts', 'class' => 'so-ssl-2fa-child-control'),
                    array('type' => 'html', 'class' => 'so-ssl-2fa-child-control', 'html' => '<p><a class="button" href="' . esc_url(admin_url('admin.php?page=so-ssl-2fa-recovery')) . '">Manage 2FA Recovery</a> <a class="button" href="' . esc_url(admin_url('admin.php?page=so-ssl-2fa-logs')) . '">View 2FA Logs</a></p>'),
                ),
            ),
            'login' => array(
                'title' => 'Login Protection',
                'fields' => array(
                    array('type' => 'checkbox', 'id' => 'strong_passwords', 'label' => 'Enforce strong passwords'),
                    array('type' => 'checkbox', 'id' => 'login_limit_enabled', 'label' => 'Enable login limiting'),
                    array('type' => 'number', 'id' => 'login_max_attempts', 'label' => 'Maximum failed attempts before lockout'),
                    array('type' => 'number', 'id' => 'login_lockout_minutes', 'label' => 'Lockout minutes'),
                    array('type' => 'checkbox', 'id' => 'login_email_notifications', 'label' => 'Email administrators on lockout'),
                    array('type' => 'textarea', 'id' => 'ip_whitelist', 'label' => 'IP whitelist, one per line'),
                    array('type' => 'textarea', 'id' => 'ip_blacklist', 'label' => 'IP blacklist, one per line'),
                ),
            ),
            'sessions' => array(
                'title' => 'User Session Management',
                'fields' => array(
                    array('type' => 'number', 'id' => 'session_limit', 'label' => 'Maximum concurrent sessions per user (0 disables)'),
                    array('type' => 'number', 'id' => 'session_max_hours', 'label' => 'Maximum session duration in hours (0 disables)'),
                    array('type' => 'html', 'html' => '<p><a class="button" href="' . esc_url(admin_url('admin.php?page=so-ssl-sessions')) . '">Manage User Sessions ' . $this->info_icon('manage_user_sessions') . '</a></p>'),
                ),
            ),
        );
    }

    public function render_settings_api_section($args) {
        $title = isset($args['title']) ? $args['title'] : '';
        if ($title) { $this->section($title); }
    }

    public function render_settings_api_field($field) {
        $o = $this->options();
        $roles = wp_roles()->roles;
        $field_class = !empty($field['class']) ? ' ' . sanitize_html_class($field['class']) : '';
        if ($field_class) { echo '<div class="so-ssl-settings-api-field' . esc_attr($field_class) . '">'; }
        switch ($field['type']) {
            case 'checkbox':
                $this->checkbox($field['id'], $field['label'], $o, !empty($field['bold']));
                break;
            case 'number':
                $this->number($field['id'], $field['label'], $o);
                break;
            case 'text':
                $this->text($field['id'], $field['label'], $o);
                break;
            case 'textarea':
                $this->textarea($field['id'], $field['label'], $o);
                break;
            case 'select':
                $this->select($field['id'], $field['label'], $o, $field['choices']);
                break;
            case 'roles':
                $this->role_checkboxes($field['id'], $field['label'], $o, $roles, !isset($field['bold']) ? true : (bool) $field['bold']);
                break;
            case 'two_factor_disabled_note':
                if (empty($o['two_factor_master_enabled'])) {
                    echo '<p class="description so-ssl-2fa-disabled-note"><strong>2FA is currently disabled.</strong> The settings below are shown for reference only and will not run until the master 2FA toggle is enabled.</p>';
                }
                break;
            case 'html':
                echo isset($field['html']) ? wp_kses($field['html'], $this->allowed_html()) : '';
                break;
        }
        if ($field_class) { echo '</div>'; }
    }

    private function open_settings_tab($tab_id, $title, $active = false) {
        echo '<div id="so-ssl-tab-' . esc_attr($tab_id) . '" class="so-ssl-tab-panel' . ($active ? ' is-active' : '') . '" role="tabpanel" data-so-ssl-tab-panel="' . esc_attr($tab_id) . '">';
        $this->section($title);
    }

    private function close_settings_tab() {
        echo '</div>';
    }

    private function field_name($key) { return self::OPTION . '[' . $key . ']'; }

    private function field_descriptions() {
        return array(
            'force_ssl' => 'Redirects visitors to HTTPS when an SSL certificate is active. Counts toward the Security Score.',
            'block_search_indexing' => 'Adds noindex protections through meta robots, X-Robots-Tag, and robots.txt without affecting the security score.',
            'enable_hsts' => 'Tells browsers to use HTTPS for future visits. Counts toward the Security Score.',
            'hsts_max_age' => 'Sets how long browsers should remember the HSTS rule, in seconds.',
            'hsts_include_subdomains' => 'Applies HSTS to subdomains as well as the main domain.',
            'hsts_preload' => 'Adds the preload flag for sites intended for browser HSTS preload lists.',
            'x_frame_options' => 'Controls whether other sites can place your site inside frames or iframes.',
            'referrer_policy' => 'Controls how much referrer information browsers send when visitors follow links.',
            'permissions_policy' => 'Limits browser features such as camera, microphone, and geolocation.',
            'csp_enabled' => 'Enables Content Security Policy headers to restrict allowed content sources. Counts toward the Security Score.',
            'csp_policy' => 'Defines the allowed sources for scripts, styles, images, fonts, workers, and other content.',
            'coep' => 'Sets Cross-Origin-Embedder-Policy for stricter cross-origin resource loading.',
            'coop' => 'Sets Cross-Origin-Opener-Policy for safer browser window isolation.',
            'corp' => 'Sets Cross-Origin-Resource-Policy for controlling who can load this site’s resources.',
            'privacy_enabled' => 'Shows a privacy acknowledgment modal to selected logged-in user roles. Counts toward the Security Score.',
            'privacy_roles' => 'Selects which user roles must acknowledge the privacy notice.',
            'privacy_expiry_days' => 'Controls how many days pass before users must acknowledge the privacy notice again.',
            'privacy_title' => 'Sets the title displayed at the top of the privacy acknowledgment modal.',
            'privacy_text' => 'Sets the privacy notice content shown to users before acknowledgment.',
            'privacy_checkbox' => 'Sets the checkbox text users must accept before continuing.',
            'admin_agreement_enabled' => 'Requires administrators to accept an agreement before using So SSL settings. Counts toward the Security Score.',
            'admin_agreement_expiry_days' => 'Controls how many days pass before administrators must accept the agreement again.',
            'admin_agreement_text' => 'Sets the administrator agreement content shown inside the modal.',
            'admin_agreement_checkbox' => 'Sets the checkbox text administrators must accept before continuing.',
            'admin_emergency_override' => 'Allows a URL override parameter to prevent accidental administrator lockout.',
            'two_factor_master_enabled' => 'Turns all So SSL two-factor authentication features on or off. Counts toward the Security Score.',
            'two_factor_enabled' => 'Turns on the role-based 2FA rule. It only affects users whose profile override is set to default; profile enable/disable overrides always win.',
            'two_factor_method' => 'Chooses whether the default verification method is email code or authenticator app.',
            'two_factor_email_fallback' => 'Lets authenticator app users request a short-lived email code if their app is unavailable.',
            'two_factor_log_failures' => 'Records recent 2FA successes and failures for administrator review.',
            'user_2fa' => 'Lets an individual user set up app-based one-time login codes.',
            'two_factor_roles' => 'Chooses which roles must use the default 2FA method when role-based checks are enabled. This is ignored for users with a profile override.',
            'strong_passwords' => 'Requires stronger passwords with length, mixed case, numbers, and symbols. Counts toward the Security Score.',
            'login_limit_enabled' => 'Limits repeated failed login attempts to reduce brute-force attacks. Counts toward the Security Score.',
            'login_max_attempts' => 'Sets how many failed attempts are allowed before a lockout starts.',
            'login_lockout_minutes' => 'Sets how long an IP is locked out after too many failed attempts.',
            'login_email_notifications' => 'Emails the site administrator when a login lockout occurs.',
            'ip_whitelist' => 'Lists IP addresses that should bypass login limiting checks.',
            'ip_blacklist' => 'Lists IP addresses that should always be blocked from logging in.',
            'session_limit' => 'Limits the number of concurrent sessions each user account may keep active.',
            'session_max_hours' => 'Expires sessions after the selected number of hours.',
            'manage_user_sessions' => 'Opens the user session manager so administrators can review and terminate sessions.',
            'sessions_page' => 'Lists recent user accounts and provides administrator session controls.',
            'sessions_page_summary' => 'Use this page to review recent users and clear sessions when an account needs to be forced out.',
            'terminate_sessions' => 'Logs this user out everywhere by destroying all active WordPress sessions for the account.',
            'two_factor_recovery' => 'Lets administrators reset authenticator setup and generate one-time backup recovery codes.',
            'two_factor_logs' => 'Shows recent two-factor authentication successes and failures for administrator review.',
        );
    }

    private function authenticator_app_links_dropdown() {
        $links = array(
            'Google Authenticator' => 'https://support.google.com/accounts/answer/1066447',
            'Google Authenticator for Android' => 'https://play.google.com/store/apps/details?id=com.google.android.apps.authenticator2',
            'Google Authenticator for iPhone' => 'https://apps.apple.com/us/app/google-authenticator/id388497605',
            'Microsoft Authenticator' => 'https://support.microsoft.com/en-us/authenticator/download-microsoft-authenticator',
            'Authy' => 'https://authy.com/download/',
            '1Password Authenticator Help' => 'https://support.1password.com/one-time-passwords/',
            'Bitwarden Authenticator' => 'https://bitwarden.com/products/authenticator/',
        );
        $html = '<select class="so-ssl-authenticator-links" aria-label="Authenticator app download links"><option value="">Choose an authenticator app link...</option>';
        foreach ($links as $label => $url) {
            $html .= '<option value="' . esc_url($url) . '">' . esc_html($label) . '</option>';
        }
        $html .= '</select> <button type="button" class="button so-ssl-open-authenticator-link">Open link</button>';
        return $html;
    }

    private function is_original_admin_account() {
        $current_user_id = get_current_user_id();
        if (1 !== (int) $current_user_id || !current_user_can('manage_options')) {
            return false;
        }

        $original_user = get_userdata(1);
        return ($original_user && user_can($original_user, 'manage_options'));
    }

    private function hidden_preserve_2fa_options($o) {
        foreach (array('two_factor_enabled','two_factor_email_fallback','two_factor_log_failures') as $key) {
            echo '<input type="hidden" name="' . esc_attr($this->field_name($key)) . '" value="' . esc_attr(!empty($o[$key]) ? 1 : 0) . '">';
        }
        foreach (array('two_factor_method') as $key) {
            echo '<input type="hidden" name="' . esc_attr($this->field_name($key)) . '" value="' . esc_attr($o[$key]) . '">';
        }
        foreach ((array) $o['two_factor_roles'] as $role_key) {
            echo '<input type="hidden" name="' . esc_attr(self::OPTION . '[two_factor_roles][]') . '" value="' . esc_attr($role_key) . '">';
        }
    }

    private function allowed_html() {
        $allowed = wp_kses_allowed_html('post');
        $allowed['input'] = array(
            'type' => true, 'name' => true, 'value' => true, 'checked' => true, 'class' => true, 'id' => true,
            'inputmode' => true, 'autocomplete' => true, 'pattern' => true, 'readonly' => true,
        );
        $allowed['button'] = array(
            'type' => true, 'class' => true, 'name' => true, 'value' => true, 'disabled' => true,
            'formaction' => true, 'formmethod' => true,
        );
        $allowed['select'] = array('name' => true, 'class' => true, 'aria-label' => true);
        $allowed['option'] = array('value' => true, 'selected' => true);
        $allowed['textarea'] = array('name' => true, 'class' => true, 'rows' => true, 'readonly' => true);
        $allowed['span'] = array('class' => true, 'tabindex' => true, 'role' => true, 'aria-label' => true, 'data-so-ssl-info' => true);
        $allowed['div']['data-action'] = true;
        $allowed['div']['data-otpauth'] = true;
        $allowed['form'] = array('method' => true, 'action' => true, 'class' => true, 'id' => true, 'name' => true);
        return $allowed;
    }

    private function safe_html($html) {
        return wp_kses($html, $this->allowed_html());
    }

    private function info_icon($key, $description = '') {
        $descriptions = $this->field_descriptions();
        $text = $description;
        if (empty($text) && !empty($descriptions[$key])) {
            $text = $descriptions[$key];
        }
        if (empty($text)) {
            return '';
        }
        return '<span class="so-ssl-info-icon" tabindex="0" role="img" aria-label="' . esc_attr($text) . '" data-so-ssl-info="' . esc_attr($text) . '">?</span>';
    }

    private function field_label($key, $label) {
        return '<span class="so-ssl-field-label">' . esc_html($label) . ' ' . wp_kses($this->info_icon($key), $this->allowed_html()) . '</span>';
    }

    private function checkbox($key,$label,$o,$bold=false) { $label_html = $this->field_label($key, $label); if ($bold) { $label_html = '<strong>' . $label_html . '</strong>'; } echo '<p class="so-ssl-field so-ssl-field-checkbox"><label><input type="checkbox" name="' . esc_attr($this->field_name($key)) . '" value="1" ' . checked(!empty($o[$key]), true, false) . '> ' . wp_kses($label_html, $this->allowed_html()) . '</label></p>'; }
    private function number($key,$label,$o) { echo '<p class="so-ssl-field"><label><strong>' . wp_kses($this->field_label($key, $label)) . '</strong><br><input type="number" class="small-text" name="' . esc_attr($this->field_name($key)) . '" value="' . esc_attr($o[$key]) . '"></label></p>'; }
    private function text($key,$label,$o) { echo '<p class="so-ssl-field"><label><strong>' . wp_kses($this->field_label($key, $label)) . '</strong><br><input type="text" class="large-text" name="' . esc_attr($this->field_name($key)) . '" value="' . esc_attr($o[$key]) . '"></label></p>'; }
    private function textarea($key,$label,$o) { echo '<p class="so-ssl-field"><label><strong>' . wp_kses($this->field_label($key, $label)) . '</strong><br><textarea class="large-text" rows="5" name="' . esc_attr($this->field_name($key)) . '">' . esc_textarea($o[$key]) . '</textarea></label></p>'; }
    private function select($key,$label,$o,$choices) { echo '<p class="so-ssl-field"><label><strong>' . wp_kses($this->field_label($key, $label)) . '</strong><br><select name="' . esc_attr($this->field_name($key)) . '">'; foreach($choices as $v=>$l){ echo '<option value="' . esc_attr($v) . '" ' . selected($o[$key], $v, false) . '>' . esc_html($l) . '</option>'; } echo '</select></label></p>'; }
    private function role_checkboxes($key,$label,$o,$roles,$bold=true) {
        $legend = $this->field_label($key, $label);
        if ($bold) { $legend = '<strong>' . $legend . '</strong>'; }
        echo '<fieldset class="so-ssl-field"><legend>' . wp_kses($legend, $this->allowed_html()) . '</legend>';
        foreach ($roles as $role_key => $role) {
            echo '<label class="so-ssl-role"><input type="checkbox" name="' . esc_attr(self::OPTION . '[' . $key . '][]') . '" value="' . esc_attr($role_key) . '" ' . checked(in_array($role_key, (array)$o[$key], true), true, false) . '> ' . esc_html($role['name']) . '</label>';
        }
        echo '</fieldset>';
    }
}
