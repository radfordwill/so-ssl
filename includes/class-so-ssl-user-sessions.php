<?php
/**
 * User Sessions Management for So SSL Plugin
 *
 * This file implements user sessions management functionality for the So SSL plugin.
 */

class So_SSL_User_Sessions {

    /**
     * Initialize user sessions management functionality
     */
    public static function init() {
        // Only proceed if user sessions management is enabled
        if (!get_option('so_ssl_enable_user_sessions', 0)) {
            return;
        }

        // Add sessions management to user profile
        add_action('show_user_profile', array(__CLASS__, 'add_sessions_management_profile'));
        add_action('edit_user_profile', array(__CLASS__, 'add_sessions_management_profile'));

        // Add admin page for global session management
        add_action('admin_menu', array(__CLASS__, 'add_sessions_menu'), 90);

        // AJAX handlers for session management
        add_action('wp_ajax_so_ssl_terminate_session', array(__CLASS__, 'ajax_terminate_session'));
        add_action('wp_ajax_so_ssl_terminate_all_sessions', array(__CLASS__, 'ajax_terminate_all_sessions'));
        add_action('wp_ajax_so_ssl_terminate_other_sessions', array(__CLASS__, 'ajax_terminate_other_sessions'));

        // Hook to check session expiry on auth cookie expiration
        add_action('auth_cookie_expired', array(__CLASS__, 'check_session_expiry'));
        add_action('wp_login', array(__CLASS__, 'record_session_login'), 10, 2);

        // Session expiry schedule
        if (!wp_next_scheduled('so_ssl_cleanup_expired_sessions')) {
            wp_schedule_event(time(), 'daily', 'so_ssl_cleanup_expired_sessions');
        }
        add_action('so_ssl_cleanup_expired_sessions', array(__CLASS__, 'cleanup_expired_sessions'));
    }

    /**
     * Add sessions management UI to user profile
     *
     * @param WP_User $user The user object
     */
    public static function add_sessions_management_profile($user) {
        // Check capabilities - user can only view their own sessions unless they're an admin
        if (!current_user_can('edit_user', $user->ID)) {
            return;
        }

        // Get user sessions
        $sessions = self::get_user_sessions($user->ID);
        $current_session = wp_get_session_token();

        wp_enqueue_style('so-ssl-user-sessions', SO_SSL_URL . 'assets/css/so-ssl-user-sessions.css', array(), SO_SSL_VERSION);
        wp_enqueue_script('so-ssl-user-sessions', SO_SSL_URL . 'assets/js/so-ssl-user-sessions.js', array('jquery'), SO_SSL_VERSION, true);
        wp_localize_script('so-ssl-user-sessions', 'soSslUserSessions', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('so_ssl_session_nonce'),
            'userId' => $user->ID,
            'currentSession' => $current_session,
            'terminateConfirm' => __('Are you sure you want to terminate this session?', 'so-ssl'),
            'terminateAllConfirm' => __('Are you sure you want to terminate all sessions? You will be logged out.', 'so-ssl'),
            'terminateOthersConfirm' => __('Are you sure you want to terminate all other sessions?', 'so-ssl')
        ));

        ?>
        <h2><?php
        /* translators: %s: Title' */
        esc_html_e('Active Sessions', 'so-ssl');

         ?></h2>
        <table class="form-table">
            <tr>
                <th><?php
                /* translators: %s: Title' */
                esc_html_e('Session Management', 'so-ssl');

                ?></th>
                <td>
                    <p><?php
                    /* translators: %s: view sessions' */
                    esc_html_e('You can view and manage your active login sessions across all devices.', 'so-ssl');

                    ?></p>
                    <?php if (current_user_can('manage_options')): ?>
                    <p><a href="<?php sprintf(admin_url('options-general.php?page=so-ssl#login-limit'));
                     ?>"><?php
                     /* translators: %s: Title' */
                     esc_html_e('Global Sessions Management', 'so-ssl');

                     ?></a></p>
                    <?php endif; ?>

                    <div class="so-ssl-session-actions">
                        <button type="button" id="so_ssl_terminate_other_sessions" class="button"><?php esc_html_e('Terminate All Other Sessions', 'so-ssl'); ?></button>
                        <button type="button" id="so_ssl_terminate_all_sessions" class="button"><?php esc_html_e('Terminate All Sessions', 'so-ssl'); ?></button>
                    </div>

                    <?php if (empty($sessions)): ?>
                        <p><?php esc_html_e('No active sessions found.', 'so-ssl'); ?></p>
                    <?php else: ?>
                        <table class="widefat so-ssl-sessions-table">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Login Time', 'so-ssl'); ?></th>
                                    <th><?php esc_html_e('IP Address', 'so-ssl'); ?></th>
                                    <th><?php esc_html_e('User Agent', 'so-ssl'); ?></th>
                                    <th><?php esc_html_e('Expires', 'so-ssl'); ?></th>
                                    <th><?php esc_html_e('Actions', 'so-ssl'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sessions as $token => $session): ?>
                                    <tr class="<?php echo ($token === $current_session) ? 'so-ssl-current-session' : ''; ?>">
                                        <td><?php echo esc_html(self::format_session_date($session['login'])); ?></td>
                                        <td><?php echo esc_html($session['ip']); ?></td>
                                        <td><?php echo esc_html(self::get_browser_name($session['ua'])); ?></td>
                                        <td><?php echo esc_html(self::format_session_date($session['expiration'])); ?></td>
                                        <td>
                                            <button type="button" class="button button-small so-ssl-terminate-session" data-token="<?php echo esc_attr($token); ?>">
                                                <?php esc_html_e('Terminate', 'so-ssl'); ?>
                                            </button>
                                            <?php if ($token === $current_session): ?>
                                                <span class="so-ssl-current-session-label"><?php esc_html_e('(Current)', 'so-ssl'); ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Add admin menu for global sessions management
     */
    public static function add_sessions_menu() {
        add_submenu_page(
            'options-general.php',
            __('User Sessions', 'so-ssl'),
            __('User Sessions', 'so-ssl'),
            'manage_options',
            'so-ssl-sessions',
            array('So_SSL_User_Sessions', 'display_sessions_page')
        );
    }

    /**
     * Display global sessions management page
     */
    public static function display_sessions_page() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            return;
        }

        wp_enqueue_style('so-ssl-user-sessions', SO_SSL_URL . 'assets/css/so-ssl-user-sessions.css', array(), SO_SSL_VERSION);
        wp_enqueue_script('so-ssl-user-sessions', SO_SSL_URL . 'assets/js/so-ssl-user-sessions.js', array('jquery'), SO_SSL_VERSION, true);
        wp_localize_script('so-ssl-user-sessions', 'soSslUserSessions', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('so_ssl_session_nonce'),
            'terminateConfirm' => __('Are you sure you want to terminate this session?', 'so-ssl'),
            'terminateAllConfirm' => __('Are you sure you want to terminate all sessions for this user?', 'so-ssl')
        ));

        // Get all users with sessions
        $users_with_sessions = self::get_all_users_with_sessions();
        $current_session = wp_get_session_token();
        $current_user_id = get_current_user_id();

        ?>
        <div class="wrap">
            <h1><?php esc_html(get_admin_page_title()); ?></h1>
            <div class="so-ssl-session-settings">
                <h2><?php esc_html_e('Session Settings', 'so-ssl'); ?></h2>
                <form method="post" action="options.php">
                    <?php settings_fields('so_ssl_sessions_options'); ?>
                    <?php do_settings_sections('so-ssl-sessions'); ?>
                    <?php submit_button(); ?>
                </form>
            </div>

            <div class="so-ssl-global-sessions">
                <h2><?php esc_html_e('Active User Sessions', 'so-ssl'); ?></h2>

                <?php if (empty($users_with_sessions)): ?>
                    <p><?php esc_html_e('No active sessions found.', 'so-ssl'); ?></p>
                <?php else: ?>
                    <?php foreach ($users_with_sessions as $user_id => $user_data): ?>
                        <div class="so-ssl-user-sessions-container">
                            <h3>
                                <?php echo esc_html($user_data['name']); ?>
                                <span class="so-ssl-user-email">(<?php echo esc_html($user_data['email']); ?>)</span>
                                <button type="button" class="button button-small so-ssl-terminate-all-user-sessions" data-user="<?php echo esc_attr($user_id); ?>">
                                    <?php esc_html_e('Terminate All Sessions', 'so-ssl'); ?>
                                </button>
                            </h3>

                            <table class="widefat so-ssl-sessions-table">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e('Login Time', 'so-ssl'); ?></th>
                                        <th><?php esc_html_e('IP Address', 'so-ssl'); ?></th>
                                        <th><?php esc_html_e('User Agent', 'so-ssl'); ?></th>
                                        <th><?php esc_html_e('Expires', 'so-ssl'); ?></th>
                                        <th><?php esc_html_e('Actions', 'so-ssl'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($user_data['sessions'] as $token => $session): ?>
                                        <tr class="<?php echo ($token === $current_session && $user_id == $current_user_id) ? 'so-ssl-current-session' : ''; ?>">
                                            <td><?php echo esc_html(self::format_session_date($session['login'])); ?></td>
                                            <td><?php echo esc_html($session['ip']); ?></td>
                                            <td><?php echo esc_html(self::get_browser_name($session['ua'])); ?></td>
                                            <td><?php echo esc_html(self::format_session_date($session['expiration'])); ?></td>
                                            <td>
                                                <button type="button" class="button button-small so-ssl-terminate-session" data-token="<?php echo esc_attr($token); ?>" data-user="<?php echo esc_attr($user_id); ?>">
                                                    <?php esc_html_e('Terminate', 'so-ssl'); ?>
                                                </button>
                                                <?php if ($token === $current_session && $user_id == $current_user_id): ?>
                                                    <span class="so-ssl-current-session-label"><?php esc_html_e('(Current)', 'so-ssl'); ?></span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Record session information on login
     *
     * @param string $user_login The username
     * @param WP_User $user The user object
     */
    public static function record_session_login($user_login, $user) {
        // Get current session token
        $token = wp_get_session_token();
        if (empty($token)) {
            return;
        }

        // Get existing sessions
        $sessions = WP_Session_Tokens::get_instance($user->ID)->get_all();
        if (!isset($sessions[$token])) {
            return;
        }

        // Add custom session data
        $sessions[$token]['ip'] = self::get_client_ip();
        $sessions[$token]['ua'] = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : 'Unknown';
        $sessions[$token]['login'] = time();

        // Update session data
        update_user_meta($user->ID, 'session_tokens', $sessions);
    }

    /**
     * AJAX handler for terminating a single session
     */
    public static function ajax_terminate_session() {
        check_ajax_referer('so_ssl_session_nonce', 'nonce');

        $token = isset($_POST['token']) ? sanitize_text_field(wp_unslash($_POST['token'])) : '';        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : get_current_user_id();

        // Check permissions
        if ($user_id !== get_current_user_id() && !current_user_can('edit_users')) {
            wp_send_json_error(array('message' => __('You do not have permission to terminate this session.', 'so-ssl')));
        }

        if (empty($token)) {
            wp_send_json_error(array('message' => __('Invalid session token.', 'so-ssl')));
        }

        // Destroy the session
        WP_Session_Tokens::get_instance($user_id)->destroy($token);

        wp_send_json_success(array('message' => __('Session terminated successfully.', 'so-ssl')));
    }

    /**
     * AJAX handler for terminating all sessions
     */
    public static function ajax_terminate_all_sessions() {
        check_ajax_referer('so_ssl_session_nonce', 'nonce');

        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : get_current_user_id();

        // Check permissions
        if ($user_id !== get_current_user_id() && !current_user_can('edit_users')) {
            wp_send_json_error(array('message' => __('You do not have permission to terminate sessions for this user.', 'so-ssl')));
        }

        // Destroy all sessions
        WP_Session_Tokens::get_instance($user_id)->destroy_all();

        wp_send_json_success(array('message' => __('All sessions terminated successfully.', 'so-ssl')));
    }

    /**
     * AJAX handler for terminating all other sessions
     */
    public static function ajax_terminate_other_sessions() {
        check_ajax_referer('so_ssl_session_nonce', 'nonce');

        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : get_current_user_id();

        // Check permissions
        if ($user_id !== get_current_user_id() && !current_user_can('edit_users')) {
            wp_send_json_error(array('message' => __('You do not have permission to terminate sessions for this user.', 'so-ssl')));
        }

        // Get current token
        $current_token = wp_get_session_token();

        // Destroy all other sessions
        WP_Session_Tokens::get_instance($user_id)->destroy_others($current_token);

        wp_send_json_success(array('message' => __('All other sessions terminated successfully.', 'so-ssl')));
    }

    /**
     * Automatically clean up expired sessions
     */
    public static function cleanup_expired_sessions() {
        // Use WordPress function to get users with session tokens
        $args = array(
            'meta_key' => 'session_tokens',
            'fields' => 'ID', // Only return user IDs
            'number' => -1 // Get all users that match the criteria
        );

        $users_with_sessions = get_users($args);

        if (empty($users_with_sessions)) {
            return;
        }

        // Check each user's sessions
        foreach ($users_with_sessions as $user_id) {
            $session_tokens = WP_Session_Tokens::get_instance($user_id);

            // This will automatically clean up expired sessions
            $sessions = $session_tokens->get_all();

            // Check for session expiry limit if enabled
            $max_session_duration = get_option('so_ssl_max_session_duration', 0);
            if ($max_session_duration > 0) {
                $now = time();
                $cleaned = false;

                foreach ($sessions as $token => $session) {
                    // If session is older than max duration, destroy it
                    if (isset($session['login']) && ($now - $session['login']) > ($max_session_duration * 3600)) {
                        $session_tokens->destroy($token);
                        $cleaned = true;
                    }
                }

                // Get updated sessions list if any were cleaned
                if ($cleaned) {
                    $sessions = $session_tokens->get_all();
                }
            }
        }
    }

    /**
     * Check session expiry on auth cookie expiration
     */
    public static function check_session_expiry() {
        // Get max sessions per user if enabled
        $max_sessions = get_option('so_ssl_max_sessions_per_user', 0);
        if ($max_sessions <= 0) {
            return;
        }

        $user_id = get_current_user_id();
        if (!$user_id) {
            return;
        }

        $sessions = WP_Session_Tokens::get_instance($user_id)->get_all();

        // If number of sessions exceeds max, remove oldest sessions
        if (count($sessions) > $max_sessions) {
            // Sort sessions by login time (oldest first)
            uasort($sessions, function($a, $b) {
                $a_login = isset($a['login']) ? $a['login'] : 0;
                $b_login = isset($b['login']) ? $b['login'] : 0;
                return $a_login - $b_login;
            });

            // Get current session token
            $current_token = wp_get_session_token();

            // Determine how many sessions to remove
            $to_remove = count($sessions) - $max_sessions;
            $removed = 0;

            foreach ($sessions as $token => $session) {
                // Skip current session
                if ($token === $current_token) {
                    continue;
                }

                // Destroy the session
                WP_Session_Tokens::get_instance($user_id)->destroy($token);
                $removed++;

                // Stop if we've removed enough
                if ($removed >= $to_remove) {
                    break;
                }
            }
        }
    }

    /**
     * Get user sessions with additional information
     *
     * @param int $user_id User ID
     * @return array User sessions with additional data
     */
    public static function get_user_sessions($user_id) {
        $sessions = WP_Session_Tokens::get_instance($user_id)->get_all();

        // Add default values for custom fields if not present
        foreach ($sessions as $token => $session) {
            if (!isset($session['ip'])) {
                $sessions[$token]['ip'] = 'Unknown';
            }
            if (!isset($session['ua'])) {
                $sessions[$token]['ua'] = 'Unknown';
            }
            if (!isset($session['login'])) {
                $sessions[$token]['login'] = isset($session['created']) ? $session['created'] : 0;
            }
        }

        return $sessions;
    }

    /**
     * Get all users with active sessions
     *
     * @return array Users with their sessions
     */
    public static function get_all_users_with_sessions() {
        // Try to get from cache first
        $users_with_sessions_data = wp_cache_get('so_ssl_users_with_sessions', 'so_ssl');

        if (false === $users_with_sessions_data) {
            // Use WordPress function to get users with session tokens
            $args = array(
                'meta_key' => 'session_tokens',
                'fields' => 'ID', // Only return user IDs
                'number' => -1 // Get all users that match the criteria
            );

            $users_with_sessions = get_users($args);

            if (empty($users_with_sessions)) {
                return array();
            }

            $result = array();

            foreach ($users_with_sessions as $user_id) {
                $user = get_userdata($user_id);
                if (!$user) {
                    continue;
                }

                $sessions = self::get_user_sessions($user_id);
                if (empty($sessions)) {
                    continue;
                }

                $result[$user_id] = array(
                    'name' => $user->display_name,
                    'email' => $user->user_email,
                    'sessions' => $sessions
                );
            }

            // Cache the result for 5 minutes (300 seconds)
            wp_cache_set('so_ssl_users_with_sessions', $result, 'so_ssl', 300);

            return $result;
        }

        return $users_with_sessions_data;
    }

    /**
     * Format session date for display
     *
     * @param int $timestamp Unix timestamp
     * @return string Formatted date
     */
    public static function format_session_date($timestamp) {
        if (empty($timestamp)) {
            return __('Unknown', 'so-ssl');
        }

        return human_time_diff($timestamp, time()) . ' ' . __('ago', 'so-ssl');
    }

    /**
     * Get client IP address
     *
     * @return string IP address
     */
    public static function get_client_ip() {
        $ip_keys = array(
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        );

        foreach ($ip_keys as $key) {
            if (isset($_SERVER[$key])) {
                // First, unslash and sanitize the IP
                $ip = sanitize_text_field(wp_unslash($_SERVER[$key]));

                // Then validate the sanitized IP
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip; // Already sanitized above
                }
            }
        }

        return 'Unknown';
    }

        /**
         * Get browser name from user agent
         *
         * @param string $user_agent User agent string
         * @return string Browser name and version
         */
        public static function get_browser_name($user_agent) {
            if (empty($user_agent)) {
                return 'Unknown';
            }

            // Detect device type
            $device = 'Desktop';

            if (preg_match('/(android|webos|iphone|ipad|ipod|blackberry|windows phone)/i', $user_agent)) {
                $device = 'Mobile';

                if (preg_match('/(ipad|tablet)/i', $user_agent)) {
                    $device = 'Tablet';
                }
            }

            // Detect browser
            $browser = 'Unknown';

            if (preg_match('/MSIE/i', $user_agent) || preg_match('/Trident/i', $user_agent)) {
                $browser = 'Internet Explorer';
            } elseif (preg_match('/Edge/i', $user_agent)) {
                $browser = 'Microsoft Edge';
            } elseif (preg_match('/Firefox/i', $user_agent)) {
                $browser = 'Firefox';
            } elseif (preg_match('/Safari/i', $user_agent) && !preg_match('/Chrome/i', $user_agent)) {
                $browser = 'Safari';
            } elseif (preg_match('/Chrome/i', $user_agent) && !preg_match('/Edg/i', $user_agent)) {
                $browser = 'Chrome';
            } elseif (preg_match('/Edg/i', $user_agent)) {
                $browser = 'Edge Chromium';
            } elseif (preg_match('/Opera|OPR/i', $user_agent)) {
                $browser = 'Opera';
            }

            // Get OS
            $os = 'Unknown';

            if (preg_match('/windows|win32|win64/i', $user_agent)) {
                $os = 'Windows';
            } elseif (preg_match('/macintosh|mac os x/i', $user_agent)) {
                $os = 'Mac OS';
            } elseif (preg_match('/android/i', $user_agent)) {
                $os = 'Android';
            } elseif (preg_match('/iphone|ipad|ipod/i', $user_agent)) {
                $os = 'iOS';
            } elseif (preg_match('/linux/i', $user_agent)) {
                $os = 'Linux';
            }

            return "$browser - $os ($device)";
        }

    /**
     * Register session management settings
     */
    public static function register_settings() {
        // Define settings arguments explicitly
        $max_sessions_args = array(
            'type' => 'integer',
            'sanitize_callback' => 'intval',
            'default' => 0,
        );

        $max_duration_args = array(
            'type' => 'integer',
            'sanitize_callback' => 'intval',
            'default' => 0,
        );

        // Session options
        register_setting(
            'so_ssl_sessions_options',
            'so_ssl_max_sessions_per_user',
            $max_sessions_args
        );

        register_setting(
            'so_ssl_sessions_options',
            'so_ssl_max_session_duration',
            $max_duration_args
        );

        // Session Settings Section
        add_settings_section(
            'so_ssl_sessions_section',
            __('Session Management Settings', 'so-ssl'),
            array('So_SSL_User_Sessions', 'sessions_section_callback'),
            'so-ssl-sessions'
        );

        add_settings_field(
            'so_ssl_max_sessions_per_user',
            __('Maximum Sessions Per User', 'so-ssl'),
            array('So_SSL_User_Sessions', 'max_sessions_callback'),
            'so-ssl-sessions',
            'so_ssl_sessions_section'
        );

        add_settings_field(
            'so_ssl_max_session_duration',
            __('Maximum Session Duration (hours)', 'so-ssl'),
            array('So_SSL_User_Sessions', 'max_duration_callback'),
            'so-ssl-sessions',
            'so_ssl_sessions_section'
        );
    }

        /**
         * Sessions section description
         */
        public static function sessions_section_callback() {
            echo '<p>' . esc_html__('Configure how user sessions are managed on your site.', 'so-ssl') . '</p>';
                        echo '<p><a href="' . /* translators: Users Session back to home link */ esc_url(admin_url('options-general.php?page=so-ssl#user-sessions')) . '">' . /* translators: Back button text */ esc_html('Back', 'so-ssl') . '</a></p>';
        }

        /**
         * Maximum sessions per user field callback
         */
        public static function max_sessions_callback() {
            $max_sessions = get_option('so_ssl_max_sessions_per_user', 0);

            echo '<input type="number" id="so_ssl_max_sessions_per_user" name="so_ssl_max_sessions_per_user" value="' . esc_attr($max_sessions) . '" min="0" />';
            echo '<p class="description">' . esc_html__('Maximum number of concurrent sessions allowed per user. Set to 0 for unlimited sessions.', 'so-ssl') . '</p>';
        }

        /**
         * Maximum session duration field callback
         */
        public static function max_duration_callback() {
            $max_duration = get_option('so_ssl_max_session_duration', 0);

            echo '<input type="number" id="so_ssl_max_session_duration" name="so_ssl_max_session_duration" value="' . esc_attr($max_duration) . '" min="0" />';
            echo '<p class="description">' . esc_html__('Maximum session lifetime in hours. Set to 0 for no limit (uses WordPress default).', 'so-ssl') . '</p>';
            echo '<p class="description">' . esc_html__('Note: This will not extend sessions beyond WordPress default expiration, but can shorten them.', 'so-ssl') . '</p>';
        }
    }

    // Register settings
    add_action('admin_init', array('So_SSL_User_Sessions', 'register_settings'));

    // Initialize the class
    So_SSL_User_Sessions::init();
