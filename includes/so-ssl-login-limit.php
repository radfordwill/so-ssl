<?php
/**
 * Login Attempts Limiter for So SSL Plugin
 *
 * This file implements login attempt limiting functionality for the So SSL plugin.
 */

class So_SSL_Login_Limit {

    /**
     * Initialize login limiting functionality
     */
    public static function init() {
        // Only proceed if login limiting is enabled
        if (!get_option('so_ssl_enable_login_limit', 0)) {
            return;
        }

        // Add hooks for login attempt limiting
        add_filter('authenticate', array(__CLASS__, 'check_login_attempts'), 30, 3);
        add_action('wp_login_failed', array(__CLASS__, 'log_failed_login'), 10, 1);
        add_action('wp_login', array(__CLASS__, 'clear_login_attempts'), 10, 2);

        // Add admin menu for login limiting statistics
        add_action('admin_menu', array(__CLASS__, 'add_login_limit_menu'), 90);

        // Add AJAX handler for whitelist/blacklist management
        add_action('wp_ajax_so_ssl_whitelist_ip', array(__CLASS__, 'ajax_whitelist_ip'));
        add_action('wp_ajax_so_ssl_blacklist_ip', array(__CLASS__, 'ajax_blacklist_ip'));
        add_action('wp_ajax_so_ssl_remove_from_list', array(__CLASS__, 'ajax_remove_from_list'));
        add_action('wp_ajax_so_ssl_reset_attempts', array(__CLASS__, 'ajax_reset_attempts'));

        // Add lockout check to login form
        add_filter('wp_authenticate_user', array(__CLASS__, 'pre_auth_lockout_check'), 99, 2);

        // Add IP check on init for site-wide lockout
        add_action('init', array(__CLASS__, 'check_ip_lockout'), 1);

        // Schedule cleanup of old records
        if (!wp_next_scheduled('so_ssl_cleanup_login_attempts')) {
            wp_schedule_event(time(), 'daily', 'so_ssl_cleanup_login_attempts');
        }
        add_action('so_ssl_cleanup_login_attempts', array(__CLASS__, 'cleanup_old_attempts'));
    }

    /**
     * Check if the current IP is locked out site-wide
     */
    public static function check_ip_lockout() {
        // Skip for admin users
        if (current_user_can('manage_options')) {
            return;
        }

        // Skip for AJAX and admin requests
        if (wp_doing_ajax() || is_admin()) {
            return;
        }

        $ip = self::get_client_ip();
        $is_blacklisted = self::is_ip_blacklisted($ip);

        if ($is_blacklisted) {
            // Check if we should show a message or just block silently
            $block_type = get_option('so_ssl_block_type', 'message');

            if ($block_type === 'message') {
                wp_die(
                    sprintf(__('Access from your IP address (%s) has been blocked due to too many failed login attempts.', 'so-ssl'), esc_html($ip)),
                    __('Access Blocked', 'so-ssl'),
                    array('response' => 403)
                );
            } else {
                // Silent block - return 403 with no explanation
                header('HTTP/1.1 403 Forbidden');
                exit;
            }
        }

        // Check for temporary lockout
        $lockout_info = self::get_lockout_info($ip);
        if ($lockout_info['is_locked']) {
            // Only display lockout message if not on login page (avoid duplicate messages)
            if (!strpos($_SERVER['PHP_SELF'], 'wp-login.php')) {
                wp_die(
                    sprintf(__('Too many failed login attempts from your IP address (%s). Please try again in %s.', 'so-ssl'),
                            esc_html($ip),
                            human_time_diff(time(), $lockout_info['release_time'])),
                    __('Temporarily Blocked', 'so-ssl'),
                    array('response' => 403)
                );
            }
        }
    }

    /**
     * Check for lockout before authentication
     *
     * @param WP_User|WP_Error $user The user object or error
     * @param string $password The password
     * @return WP_User|WP_Error The user object or error
     */
    public static function pre_auth_lockout_check($user, $password) {
        // If already errored, return the error
        if (is_wp_error($user)) {
            return $user;
        }

        $ip = self::get_client_ip();

        // Check if IP is in whitelist
        if (self::is_ip_whitelisted($ip)) {
            return $user;
        }

        // Check if IP is blacklisted
        if (self::is_ip_blacklisted($ip)) {
            return new WP_Error('ip_blacklisted', __('<strong>ERROR</strong>: Your IP address has been blocked due to too many failed login attempts.', 'so-ssl'));
        }

        // Check for temporary lockout
        $lockout_info = self::get_lockout_info($ip);
        if ($lockout_info['is_locked']) {
            $time_remaining = human_time_diff(time(), $lockout_info['release_time']);
            return new WP_Error('ip_temporarily_blocked',
                sprintf(__('<strong>ERROR</strong>: Too many failed login attempts. Please try again in %s or contact an administrator.', 'so-ssl'), $time_remaining)
            );
        }

        return $user;
    }

    /**
     * Check login attempts before processing authentication
     *
     * @param WP_User|WP_Error|null $user User object, WP_Error, or null
     * @param string $username The username
     * @param string $password The password
     * @return WP_User|WP_Error User object or error
     */
    public static function check_login_attempts($user, $username, $password) {
        // If already errored, return the error
        if (is_wp_error($user)) {
            return $user;
        }

        // If not a user object, return as is
        if (!is_a($user, 'WP_User')) {
            return $user;
        }

        // At this point, we know we have a valid user
        // but we'll let the next step handle the actual login process
        return $user;
    }

    /**
     * Log failed login attempts
     *
     * @param string $username The attempted username
     */
    public static function log_failed_login($username) {
        $ip = self::get_client_ip();

        // Skip if IP is whitelisted
        if (self::is_ip_whitelisted($ip)) {
            return;
        }

        // Get current attempts
        $attempts = get_option('so_ssl_login_attempts', array());

        // Initialize if not exists
        if (!isset($attempts[$ip])) {
            $attempts[$ip] = array(
                'count' => 0,
                'lockout_count' => 0,
                'usernames' => array(),
                'last_attempt' => 0,
                'lockout_until' => 0
            );
        }

        // Update attempts data
        $attempts[$ip]['count']++;
        $attempts[$ip]['last_attempt'] = time();

        // Track username attempts
        if (!isset($attempts[$ip]['usernames'][$username])) {
            $attempts[$ip]['usernames'][$username] = 0;
        }
        $attempts[$ip]['usernames'][$username]++;

        // Handle lockout if needed
        $max_attempts = get_option('so_ssl_max_login_attempts', 5);
        $lockout_duration = get_option('so_ssl_lockout_duration', 15) * 60; // Convert to seconds
        $long_lockout_count = get_option('so_ssl_long_lockout_count', 3);
        $long_lockout_duration = get_option('so_ssl_long_lockout_duration', 24) * 3600; // Convert to seconds

        if ($attempts[$ip]['count'] >= $max_attempts) {
            // Determine lockout duration based on number of previous lockouts
            $lockout_time = time();
            $attempts[$ip]['lockout_count']++;

            if ($attempts[$ip]['lockout_count'] >= $long_lockout_count) {
                // Long lockout
                $lockout_time += $long_lockout_duration;

                // Auto-blacklist if enabled
                if (get_option('so_ssl_auto_blacklist', 0)) {
                    self::add_to_blacklist($ip);
                }

                // Notify admin if enabled
                if (get_option('so_ssl_lockout_notify', 0)) {
                    $subject = sprintf(__('[%s] IP Blacklisted Due to Failed Login Attempts', 'so-ssl'), get_bloginfo('name'));
                    $message = sprintf(__('IP: %s has been blacklisted after %d failed login attempts.', 'so-ssl'),
                                $ip, $attempts[$ip]['count']) . "\n\n";
                    $message .= sprintf(__('Attempted usernames: %s', 'so-ssl'), implode(', ', array_keys($attempts[$ip]['usernames']))) . "\n\n";
                    $message .= admin_url('options-general.php?page=so-ssl-login-limit');

                    wp_mail(get_option('admin_email'), $subject, $message);
                }
            } else {
                // Normal lockout
                $lockout_time += $lockout_duration;

                // Notify admin if enabled and this is not the first lockout
                if ($attempts[$ip]['lockout_count'] > 1 && get_option('so_ssl_lockout_notify', 0)) {
                    $subject = sprintf(__('[%s] Multiple Failed Login Attempts', 'so-ssl'), get_bloginfo('name'));
                    $message = sprintf(__('IP: %s has had %d failed login attempts and is temporarily locked out.', 'so-ssl'),
                                $ip, $attempts[$ip]['count']) . "\n\n";
                    $message .= sprintf(__('Attempted usernames: %s', 'so-ssl'), implode(', ', array_keys($attempts[$ip]['usernames']))) . "\n\n";
                    $message .= admin_url('options-general.php?page=so-ssl-login-limit');

                    wp_mail(get_option('admin_email'), $subject, $message);
                }
            }

            $attempts[$ip]['lockout_until'] = $lockout_time;
            $attempts[$ip]['count'] = 0; // Reset counter after lockout
        }

        // Save updated attempts
        update_option('so_ssl_login_attempts', $attempts);

        // Log this attempt in the history
        self::log_attempt_to_history($ip, $username, false);
    }

    /**
     * Record successful login attempt and clear failed attempts for the IP
     *
     * @param string $username The username
     * @param WP_User $user The user object
     */
    public static function clear_login_attempts($username, $user) {
        $ip = self::get_client_ip();

        // Get current attempts
        $attempts = get_option('so_ssl_login_attempts', array());

        // Remove this IP from attempts
        if (isset($attempts[$ip])) {
            unset($attempts[$ip]);
            update_option('so_ssl_login_attempts', $attempts);
        }

        // Log successful login
        self::log_attempt_to_history($ip, $username, true);
    }

    /**
     * Log attempt to history
     *
     * @param string $ip The IP address
     * @param string $username The username
     * @param bool $success Whether the attempt was successful
     */
    public static function log_attempt_to_history($ip, $username, $success) {
        // Get history
        $history = get_option('so_ssl_login_history', array());

        // Limit history size
        if (count($history) >= 1000) {
            array_shift($history); // Remove oldest entry
        }

        // Add new entry
        $history[] = array(
            'ip' => $ip,
            'username' => $username,
            'time' => time(),
            'success' => $success,
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'Unknown'
        );

        update_option('so_ssl_login_history', $history);
    }

    /**
     * Clean up old login attempts
     */
    public static function cleanup_old_attempts() {
        // Clean up history older than 30 days
        $history = get_option('so_ssl_login_history', array());
        $thirty_days_ago = time() - (30 * DAY_IN_SECONDS);

        foreach ($history as $key => $entry) {
            if ($entry['time'] < $thirty_days_ago) {
                unset($history[$key]);
            }
        }

        // Reindex array
        $history = array_values($history);
        update_option('so_ssl_login_history', $history);

        // Clean up expired lockouts
        $attempts = get_option('so_ssl_login_attempts', array());
        $now = time();

        foreach ($attempts as $ip => $data) {
            // Remove if lockout has expired
            if ($data['lockout_until'] > 0 && $data['lockout_until'] < $now) {
                // Reset lockout but keep the count history
                $attempts[$ip]['lockout_until'] = 0;
                $attempts[$ip]['count'] = 0;
            }

            // Remove entries older than 60 days
            if ($data['last_attempt'] < $now - (60 * DAY_IN_SECONDS)) {
                unset($attempts[$ip]);
            }
        }

        update_option('so_ssl_login_attempts', $attempts);
    }

    /**
     * Get lockout information for an IP
     *
     * @param string $ip The IP address
     * @return array Lockout information
     */
    public static function get_lockout_info($ip) {
        $attempts = get_option('so_ssl_login_attempts', array());
        $is_locked = false;
        $release_time = 0;

        if (isset($attempts[$ip]) && $attempts[$ip]['lockout_until'] > time()) {
            $is_locked = true;
            $release_time = $attempts[$ip]['lockout_until'];
        }

        return array(
            'is_locked' => $is_locked,
            'release_time' => $release_time
        );
    }

    /**
     * Add admin menu for login limiting
     */
    public static function add_login_limit_menu() {
        add_submenu_page(
            'options-general.php',
            __('Login Security', 'so-ssl'),
            __('Login Security', 'so-ssl'),
            'manage_options',
            'so-ssl-login-limit',
            array('So_SSL_Login_Limit', 'display_login_limit_page')
        );
    }

    /**
     * Display login limiting admin page
     */
    public static function display_login_limit_page() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            return;
        }

        wp_enqueue_style('so-ssl-login-limit', SO_SSL_URL . 'assets/css/so-ssl-login-limit.css', array(), SO_SSL_VERSION);
        wp_enqueue_script('so-ssl-login-limit', SO_SSL_URL . 'assets/js/so-ssl-login-limit.js', array('jquery'), SO_SSL_VERSION, true);
        wp_localize_script('so-ssl-login-limit', 'soSslLoginLimit', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('so_ssl_login_limit_nonce'),
            'whitelistConfirm' => __('Are you sure you want to whitelist this IP?', 'so-ssl'),
            'blacklistConfirm' => __('Are you sure you want to blacklist this IP?', 'so-ssl'),
            'removeConfirm' => __('Are you sure you want to remove this IP from the list?', 'so-ssl'),
            'resetConfirm' => __('Are you sure you want to reset the attempt count for this IP?', 'so-ssl')
        ));

        // Get data for display
        $attempts = get_option('so_ssl_login_attempts', array());
        $history = get_option('so_ssl_login_history', array());
        $whitelist = get_option('so_ssl_ip_whitelist', array());
        $blacklist = get_option('so_ssl_ip_blacklist', array());

        // Sort attempts by lockout status and then by count
        uasort($attempts, function($a, $b) {
            // First sort by lockout status
            if ($a['lockout_until'] > time() && $b['lockout_until'] <= time()) {
                return -1;
            }
            if ($a['lockout_until'] <= time() && $b['lockout_until'] > time()) {
                return 1;
            }

            // Then by count
            return $b['count'] - $a['count'];
        });

        // Sort history by time (newest first)
        usort($history, function($a, $b) {
            return $b['time'] - $a['time'];
        });

        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <div class="so-ssl-login-limit-tabs">
                <div class="nav-tab-wrapper">
                    <a href="#settings" class="nav-tab nav-tab-active"><?php _e('Settings', 'so-ssl'); ?></a>
                    <a href="#current-lockouts" class="nav-tab"><?php _e('Current Lockouts', 'so-ssl'); ?></a>
                    <a href="#statistics" class="nav-tab"><?php _e('Statistics', 'so-ssl'); ?></a>
                    <a href="#whitelist" class="nav-tab"><?php _e('Whitelist', 'so-ssl'); ?></a>
                    <a href="#blacklist" class="nav-tab"><?php _e('Blacklist', 'so-ssl'); ?></a>
                    <a href="#login-history" class="nav-tab"><?php _e('Login History', 'so-ssl'); ?></a>
                </div>

                <div id="settings" class="tab-content active">
                    <h2><?php _e('Login Limiting Settings', 'so-ssl'); ?></h2>
                    <h1><p><a href="<?php echo admin_url('options-general.php?page=so-ssl#login-limit'); ?>"><?php _e('Back', 'so-ssl'); ?></a></p></h1>
                    <form method="post" action="options.php">
                        <?php settings_fields('so_ssl_login_limit_options'); ?>
                        <?php do_settings_sections('so-ssl-login-limit'); ?>
                        <?php submit_button(); ?>
                    </form>
                </div>

                <div id="current-lockouts" class="tab-content">
                    <h2><?php _e('Current Lockouts', 'so-ssl'); ?></h2>
                    <?php if (empty($attempts)): ?>
                        <p><?php _e('No login attempts recorded.', 'so-ssl'); ?></p>
                    <?php else: ?>
                        <table class="widefat striped">
                            <thead>
                                <tr>
                                    <th><?php _e('IP Address', 'so-ssl'); ?></th>
                                    <th><?php _e('Failed Attempts', 'so-ssl'); ?></th>
                                    <th><?php _e('Lockouts', 'so-ssl'); ?></th>
                                    <th><?php _e('Attempted Usernames', 'so-ssl'); ?></th>
                                    <th><?php _e('Last Attempt', 'so-ssl'); ?></th>
                                    <th><?php _e('Status', 'so-ssl'); ?></th>
                                    <th><?php _e('Actions', 'so-ssl'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($attempts as $ip => $data): ?>
                                    <tr>
                                        <td><?php echo esc_html($ip); ?></td>
                                        <td><?php echo esc_html($data['count']); ?></td>
                                        <td><?php echo esc_html($data['lockout_count']); ?></td>
                                        <td>
                                            <?php
                                            if (!empty($data['usernames'])) {
                                                $username_list = array();
                                                foreach ($data['usernames'] as $username => $count) {
                                                    $username_list[] = "$username ($count)";
                                                }
                                                echo esc_html(implode(', ', $username_list));
                                            } else {
                                                _e('None', 'so-ssl');
                                            }
                                            ?>
                                        </td>
                                        <td><?php echo esc_html(human_time_diff($data['last_attempt'], time()) . ' ago'); ?></td>
                                        <td>
                                            <?php
                                            if ($data['lockout_until'] > time()) {
                                                echo '<span class="so-ssl-status-locked">' . sprintf(__('Locked until %s', 'so-ssl'), date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $data['lockout_until'])) . '</span>';
                                            } else {
                                                echo '<span class="so-ssl-status-ok">' . __('OK', 'so-ssl') . '</span>';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <button type="button" class="button so-ssl-whitelist-ip" data-ip="<?php echo esc_attr($ip); ?>"><?php _e('Whitelist', 'so-ssl'); ?></button>
                                            <button type="button" class="button so-ssl-blacklist-ip" data-ip="<?php echo esc_attr($ip); ?>"><?php _e('Blacklist', 'so-ssl'); ?></button>
                                            <button type="button" class="button so-ssl-reset-attempts" data-ip="<?php echo esc_attr($ip); ?>"><?php _e('Reset', 'so-ssl'); ?></button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <div id="statistics" class="tab-content">
                    <h2><?php _e('Login Attempt Statistics', 'so-ssl'); ?></h2>
                    <?php
                    // Calculate statistics
                    $total_failed = 0;
                    $total_success = 0;
                    $total_lockouts = 0;
                    $unique_ips = array();
                    $targeted_usernames = array();

                    foreach ($history as $entry) {
                        if ($entry['success']) {
                            $total_success++;
                        } else {
                            $total_failed++;
                            $unique_ips[$entry['ip']] = true;

                            if (!isset($targeted_usernames[$entry['username']])) {
                                $targeted_usernames[$entry['username']] = 0;
                            }
                            $targeted_usernames[$entry['username']]++;
                        }
                    }

                    foreach ($attempts as $ip => $data) {
                        $total_lockouts += $data['lockout_count'];
                    }

                    // Sort usernames by attempts (most targeted first)
                    arsort($targeted_usernames);
                    ?>

                    <div class="so-ssl-stats-grid">
                        <div class="so-ssl-stat-box">
                            <h3><?php _e('Failed Attempts', 'so-ssl'); ?></h3>
                            <div class="so-ssl-stat-number"><?php echo esc_html($total_failed); ?></div>
                        </div>

                        <div class="so-ssl-stat-box">
                            <h3><?php _e('Successful Logins', 'so-ssl'); ?></h3>
                            <div class="so-ssl-stat-number"><?php echo esc_html($total_success); ?></div>
                        </div>

                        <div class="so-ssl-stat-box">
                            <h3><?php _e('Total Lockouts', 'so-ssl'); ?></h3>
                            <div class="so-ssl-stat-number"><?php echo esc_html($total_lockouts); ?></div>
                        </div>

                        <div class="so-ssl-stat-box">
                            <h3><?php _e('Unique IPs', 'so-ssl'); ?></h3>
                            <div class="so-ssl-stat-number"><?php echo esc_html(count($unique_ips)); ?></div>
                        </div>
                    </div>
                    <div class="so-ssl-stats-tables">
                        <div class="so-ssl-stats-table">
                            <h3><?php _e('Most Targeted Usernames', 'so-ssl'); ?></h3>
                            <?php if (empty($targeted_usernames)): ?>
                                <p><?php _e('No data available', 'so-ssl'); ?></p>
                            <?php else: ?>
                                <table class="widefat striped">
                                    <thead>
                                        <tr>
                                            <th><?php _e('Username', 'so-ssl'); ?></th>
                                            <th><?php _e('Failed Attempts', 'so-ssl'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $count = 0;
                                        foreach ($targeted_usernames as $username => $attempts):
                                            if ($count++ > 10) break; // Show only top 10
                                        ?>
                                            <tr>
                                                <td><?php echo esc_html($username); ?></td>
                                                <td><?php echo esc_html($attempts); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div id="whitelist" class="tab-content">
                    <h2><?php _e('IP Whitelist', 'so-ssl'); ?></h2>
                    <p><?php _e('Whitelisted IPs are exempt from login limiting.', 'so-ssl'); ?></p>

                    <div class="so-ssl-add-ip-form">
                        <h3><?php _e('Add IP to Whitelist', 'so-ssl'); ?></h3>
                        <form id="so-ssl-add-whitelist-form">
                            <input type="text" id="so-ssl-new-whitelist-ip" placeholder="<?php esc_attr_e('Enter IP address', 'so-ssl'); ?>" />
                            <button type="submit" class="button"><?php _e('Add to Whitelist', 'so-ssl'); ?></button>
                        </form>
                    </div>

                    <?php if (empty($whitelist)): ?>
                        <p><?php _e('No IPs are currently whitelisted.', 'so-ssl'); ?></p>
                    <?php else: ?>
                        <table class="widefat striped">
                            <thead>
                                <tr>
                                    <th><?php _e('IP Address', 'so-ssl'); ?></th>
                                    <th><?php _e('Added', 'so-ssl'); ?></th>
                                    <th><?php _e('Actions', 'so-ssl'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($whitelist as $ip => $data): ?>
                                    <tr>
                                        <td><?php echo esc_html($ip); ?></td>
                                        <td><?php echo isset($data['added']) ? esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $data['added'])) : ''; ?></td>
                                        <td>
                                            <button type="button" class="button so-ssl-remove-from-list" data-ip="<?php echo esc_attr($ip); ?>" data-list="whitelist"><?php _e('Remove', 'so-ssl'); ?></button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <div id="blacklist" class="tab-content">
                    <h2><?php _e('IP Blacklist', 'so-ssl'); ?></h2>
                    <p><?php _e('Blacklisted IPs are permanently blocked from logging in.', 'so-ssl'); ?></p>

                    <div class="so-ssl-add-ip-form">
                        <h3><?php _e('Add IP to Blacklist', 'so-ssl'); ?></h3>
                        <form id="so-ssl-add-blacklist-form">
                            <input type="text" id="so-ssl-new-blacklist-ip" placeholder="<?php esc_attr_e('Enter IP address', 'so-ssl'); ?>" />
                            <button type="submit" class="button"><?php _e('Add to Blacklist', 'so-ssl'); ?></button>
                        </form>
                    </div>

                    <?php if (empty($blacklist)): ?>
                        <p><?php _e('No IPs are currently blacklisted.', 'so-ssl'); ?></p>
                    <?php else: ?>
                        <table class="widefat striped">
                            <thead>
                                <tr>
                                    <th><?php _e('IP Address', 'so-ssl'); ?></th>
                                    <th><?php _e('Added', 'so-ssl'); ?></th>
                                    <th><?php _e('Reason', 'so-ssl'); ?></th>
                                    <th><?php _e('Actions', 'so-ssl'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($blacklist as $ip => $data): ?>
                                    <tr>
                                        <td><?php echo esc_html($ip); ?></td>
                                        <td><?php echo isset($data['added']) ? esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $data['added'])) : ''; ?></td>
                                        <td><?php echo isset($data['reason']) ? esc_html($data['reason']) : __('Manual', 'so-ssl'); ?></td>
                                        <td>
                                            <button type="button" class="button so-ssl-remove-from-list" data-ip="<?php echo esc_attr($ip); ?>" data-list="blacklist"><?php _e('Remove', 'so-ssl'); ?></button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <div id="login-history" class="tab-content">
                    <h2><?php _e('Login History', 'so-ssl'); ?></h2>

                    <?php if (empty($history)): ?>
                        <p><?php _e('No login history recorded.', 'so-ssl'); ?></p>
                    <?php else: ?>
                        <table class="widefat striped">
                            <thead>
                                <tr>
                                    <th><?php _e('Time', 'so-ssl'); ?></th>
                                    <th><?php _e('IP Address', 'so-ssl'); ?></th>
                                    <th><?php _e('Username', 'so-ssl'); ?></th>
                                    <th><?php _e('Status', 'so-ssl'); ?></th>
                                    <th><?php _e('Browser', 'so-ssl'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $count = 0;
                                foreach ($history as $entry):
                                    if ($count++ > 100) break; // Show only 100 most recent entries
                                ?>
                                    <tr>
                                        <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $entry['time'])); ?></td>
                                        <td><?php echo esc_html($entry['ip']); ?></td>
                                        <td><?php echo esc_html($entry['username']); ?></td>
                                        <td>
                                            <?php if ($entry['success']): ?>
                                                <span class="so-ssl-status-success"><?php _e('Success', 'so-ssl'); ?></span>
                                            <?php else: ?>
                                                <span class="so-ssl-status-failed"><?php _e('Failed', 'so-ssl'); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo esc_html(self::get_browser_name($entry['user_agent'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <script>
            jQuery(document).ready(function($) {
                // Tab navigation
                $('.so-ssl-login-limit-tabs .nav-tab').on('click', function(e) {
                    e.preventDefault();

                    // Remove active class
                    $('.so-ssl-login-limit-tabs .nav-tab').removeClass('nav-tab-active');
                    $('.so-ssl-login-limit-tabs .tab-content').removeClass('active');

                    // Add active class to clicked tab
                    $(this).addClass('nav-tab-active');
                    $($(this).attr('href')).addClass('active');
                });

                // Handle URL hash for tabs
                if (window.location.hash) {
                    const hash = window.location.hash;
                    if ($(hash).length) {
                        $('.so-ssl-login-limit-tabs .nav-tab').removeClass('nav-tab-active');
                        $('.so-ssl-login-limit-tabs .tab-content').removeClass('active');

                        $('.so-ssl-login-limit-tabs .nav-tab[href="' + hash + '"]').addClass('nav-tab-active');
                        $(hash).addClass('active');
                    }
                }
            });
        </script>
        <?php
    }

    /**
     * Add to IP whitelist
     *
     * @param string $ip The IP address
     * @return bool Success
     */
    public static function add_to_whitelist($ip) {
        if (empty($ip) || !self::is_valid_ip($ip)) {
            return false;
        }

        $whitelist = get_option('so_ssl_ip_whitelist', array());

        // Add to whitelist if not already present
        if (!isset($whitelist[$ip])) {
            $whitelist[$ip] = array(
                'added' => time()
            );
            update_option('so_ssl_ip_whitelist', $whitelist);

            // Remove from blacklist if present
            self::remove_from_blacklist($ip);

            // Remove from failed attempts
            $attempts = get_option('so_ssl_login_attempts', array());
            if (isset($attempts[$ip])) {
                unset($attempts[$ip]);
                update_option('so_ssl_login_attempts', $attempts);
            }

            return true;
        }

        return false;
    }

    /**
     * Add to IP blacklist
     *
     * @param string $ip The IP address
     * @param string $reason The reason for blacklisting
     * @return bool Success
     */
    public static function add_to_blacklist($ip, $reason = 'Too many failed login attempts') {
        if (empty($ip) || !self::is_valid_ip($ip)) {
            return false;
        }

        $blacklist = get_option('so_ssl_ip_blacklist', array());

        // Add to blacklist if not already present
        if (!isset($blacklist[$ip])) {
            $blacklist[$ip] = array(
                'added' => time(),
                'reason' => $reason
            );
            update_option('so_ssl_ip_blacklist', $blacklist);

            // Remove from whitelist if present
            self::remove_from_whitelist($ip);

            return true;
        }

        return false;
    }

    /**
     * Remove from IP whitelist
     *
     * @param string $ip The IP address
     * @return bool Success
     */
    public static function remove_from_whitelist($ip) {
        $whitelist = get_option('so_ssl_ip_whitelist', array());

        if (isset($whitelist[$ip])) {
            unset($whitelist[$ip]);
            update_option('so_ssl_ip_whitelist', $whitelist);
            return true;
        }

        return false;
    }

    /**
     * Remove from IP blacklist
     *
     * @param string $ip The IP address
     * @return bool Success
     */
    public static function remove_from_blacklist($ip) {
        $blacklist = get_option('so_ssl_ip_blacklist', array());

        if (isset($blacklist[$ip])) {
            unset($blacklist[$ip]);
            update_option('so_ssl_ip_blacklist', $blacklist);
            return true;
        }

        return false;
    }

    /**
     * Reset login attempts for an IP
     *
     * @param string $ip The IP address
     * @return bool Success
     */
    public static function reset_login_attempts($ip) {
        $attempts = get_option('so_ssl_login_attempts', array());

        if (isset($attempts[$ip])) {
            unset($attempts[$ip]);
            update_option('so_ssl_login_attempts', $attempts);
            return true;
        }

        return false;
    }

    /**
     * Check if an IP is in the whitelist
     *
     * @param string $ip The IP address
     * @return bool Whether the IP is whitelisted
     */
    public static function is_ip_whitelisted($ip) {
        $whitelist = get_option('so_ssl_ip_whitelist', array());
        return isset($whitelist[$ip]);
    }

    /**
     * Check if an IP is in the blacklist
     *
     * @param string $ip The IP address
     * @return bool Whether the IP is blacklisted
     */
    public static function is_ip_blacklisted($ip) {
        $blacklist = get_option('so_ssl_ip_blacklist', array());
        return isset($blacklist[$ip]);
    }

    /**
     * AJAX handler for whitelisting an IP
     */
    public static function ajax_whitelist_ip() {
        check_ajax_referer('so_ssl_login_limit_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'so-ssl')));
        }

        $ip = isset($_POST['ip']) ? sanitize_text_field($_POST['ip']) : '';

        if (empty($ip)) {
            wp_send_json_error(array('message' => __('Invalid IP address.', 'so-ssl')));
        }

        if (self::add_to_whitelist($ip)) {
            wp_send_json_success(array('message' => sprintf(__('IP %s has been whitelisted.', 'so-ssl'), $ip)));
        } else {
            wp_send_json_error(array('message' => sprintf(__('IP %s is already whitelisted.', 'so-ssl'), $ip)));
        }
    }

    /**
     * AJAX handler for blacklisting an IP
     */
    public static function ajax_blacklist_ip() {
        check_ajax_referer('so_ssl_login_limit_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'so-ssl')));
        }

        $ip = isset($_POST['ip']) ? sanitize_text_field($_POST['ip']) : '';
        $reason = isset($_POST['reason']) ? sanitize_text_field($_POST['reason']) : __('Manually blacklisted', 'so-ssl');

        if (empty($ip)) {
            wp_send_json_error(array('message' => __('Invalid IP address.', 'so-ssl')));
        }

        if (self::add_to_blacklist($ip, $reason)) {
            wp_send_json_success(array('message' => sprintf(__('IP %s has been blacklisted.', 'so-ssl'), $ip)));
        } else {
            wp_send_json_error(array('message' => sprintf(__('IP %s is already blacklisted.', 'so-ssl'), $ip)));
        }
    }

    /**
     * AJAX handler for removing an IP from a list
     */
    public static function ajax_remove_from_list() {
        check_ajax_referer('so_ssl_login_limit_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'so-ssl')));
        }

        $ip = isset($_POST['ip']) ? sanitize_text_field($_POST['ip']) : '';
        $list = isset($_POST['list']) ? sanitize_text_field($_POST['list']) : '';

        if (empty($ip) || !in_array($list, array('whitelist', 'blacklist'))) {
            wp_send_json_error(array('message' => __('Invalid parameters.', 'so-ssl')));
        }

        if ($list === 'whitelist') {
            if (self::remove_from_whitelist($ip)) {
                wp_send_json_success(array('message' => sprintf(__('IP %s has been removed from the whitelist.', 'so-ssl'), $ip)));
            } else {
                wp_send_json_error(array('message' => sprintf(__('IP %s is not in the whitelist.', 'so-ssl'), $ip)));
            }
        } else {
            if (self::remove_from_blacklist($ip)) {
                wp_send_json_success(array('message' => sprintf(__('IP %s has been removed from the blacklist.', 'so-ssl'), $ip)));
            } else {
                wp_send_json_error(array('message' => sprintf(__('IP %s is not in the blacklist.', 'so-ssl'), $ip)));
            }
        }
    }

    /**
     * AJAX handler for resetting login attempts
     */
    public static function ajax_reset_attempts() {
        check_ajax_referer('so_ssl_login_limit_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'so-ssl')));
        }

        $ip = isset($_POST['ip']) ? sanitize_text_field($_POST['ip']) : '';

        if (empty($ip)) {
            wp_send_json_error(array('message' => __('Invalid IP address.', 'so-ssl')));
        }

        if (self::reset_login_attempts($ip)) {
            wp_send_json_success(array('message' => sprintf(__('Login attempts for IP %s have been reset.', 'so-ssl'), $ip)));
        } else {
            wp_send_json_error(array('message' => sprintf(__('No login attempts found for IP %s.', 'so-ssl'), $ip)));
        }
    }

    /**
     * Register login limit settings
     */
    public static function register_settings() {
        // Login limit options
        register_setting(
            'so_ssl_login_limit_options',
            'so_ssl_enable_login_limit',
            array(
                'type' => 'boolean',
                'sanitize_callback' => 'intval',
                'default' => 0,
            )
        );

        register_setting(
            'so_ssl_login_limit_options',
            'so_ssl_max_login_attempts',
            array(
                'type' => 'integer',
                'sanitize_callback' => 'intval',
                'default' => 5,
            )
        );

        register_setting(
            'so_ssl_login_limit_options',
            'so_ssl_lockout_duration',
            array(
                'type' => 'integer',
                'sanitize_callback' => 'intval',
                'default' => 15,
            )
        );

        register_setting(
            'so_ssl_login_limit_options',
            'so_ssl_long_lockout_count',
            array(
                'type' => 'integer',
                'sanitize_callback' => 'intval',
                'default' => 3,
            )
        );

        register_setting(
            'so_ssl_login_limit_options',
            'so_ssl_long_lockout_duration',
            array(
                'type' => 'integer',
                'sanitize_callback' => 'intval',
                'default' => 24,
            )
        );

        register_setting(
            'so_ssl_login_limit_options',
            'so_ssl_auto_blacklist',
            array(
                'type' => 'boolean',
                'sanitize_callback' => 'intval',
                'default' => 0,
            )
        );

        register_setting(
            'so_ssl_login_limit_options',
            'so_ssl_lockout_notify',
            array(
                'type' => 'boolean',
                'sanitize_callback' => 'intval',
                'default' => 0,
            )
        );

        register_setting(
            'so_ssl_login_limit_options',
            'so_ssl_block_type',
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => 'message',
            )
        );

        // Login Limit Settings Section
        add_settings_section(
            'so_ssl_login_limit_section',
            __('Login Attempt Limiting', 'so-ssl'),
            array(__CLASS__, 'login_limit_section_callback'),
            'so-ssl-login-limit'
        );

        add_settings_field(
            'so_ssl_enable_login_limit',
            __('Enable Login Limiting', 'so-ssl'),
            array(__CLASS__, 'enable_login_limit_callback'),
            'so-ssl-login-limit',
            'so_ssl_login_limit_section'
        );

        add_settings_field(
            'so_ssl_max_login_attempts',
            __('Maximum Login Attempts', 'so-ssl'),
            array(__CLASS__, 'max_login_attempts_callback'),
            'so-ssl-login-limit',
            'so_ssl_login_limit_section'
        );

        add_settings_field(
            'so_ssl_lockout_duration',
            __('Lockout Duration (minutes)', 'so-ssl'),
            array(__CLASS__, 'lockout_duration_callback'),
            'so-ssl-login-limit',
            'so_ssl_login_limit_section'
        );

        add_settings_field(
            'so_ssl_long_lockout_settings',
            __('Extended Lockout Settings', 'so-ssl'),
            array(__CLASS__, 'long_lockout_settings_callback'),
            'so-ssl-login-limit',
            'so_ssl_login_limit_section'
        );

        add_settings_field(
            'so_ssl_auto_blacklist',
            __('Auto-Blacklist IPs', 'so-ssl'),
            array(__CLASS__, 'auto_blacklist_callback'),
            'so-ssl-login-limit',
            'so_ssl_login_limit_section'
        );

        add_settings_field(
            'so_ssl_lockout_notify',
            __('Email Notifications', 'so-ssl'),
            array(__CLASS__, 'lockout_notify_callback'),
            'so-ssl-login-limit',
            'so_ssl_login_limit_section'
        );

        add_settings_field(
            'so_ssl_block_type',
            __('Block Type', 'so-ssl'),
            array(__CLASS__, 'block_type_callback'),
            'so-ssl-login-limit',
            'so_ssl_login_limit_section'
        );
    }

    /**
     * Login limit section description
     */
    public static function login_limit_section_callback() {
        echo '<p>' . __('Configure login attempt limiting to protect your site from brute force attacks.', 'so-ssl') . '</p>';
    }

    /**
     * Enable login limit field callback
     */
    public static function enable_login_limit_callback() {
        $enable_login_limit = get_option('so_ssl_enable_login_limit', 0);

        echo '<label for="so_ssl_enable_login_limit">';
        echo '<input type="checkbox" id="so_ssl_enable_login_limit" name="so_ssl_enable_login_limit" value="1" ' . checked(1, $enable_login_limit, false) . '/>';
        echo __('Enable login attempt limiting', 'so-ssl');
        echo '</label>';
        echo '<p class="description">' . __('Limits the number of failed login attempts allowed per IP address.', 'so-ssl') . '</p>';
    }

    /**
     * Maximum login attempts field callback
     */
    public static function max_login_attempts_callback() {
        $max_attempts = get_option('so_ssl_max_login_attempts', 5);

        echo '<input type="number" id="so_ssl_max_login_attempts" name="so_ssl_max_login_attempts" value="' . esc_attr($max_attempts) . '" min="1" />';
        echo '<p class="description">' . __('Number of failed login attempts allowed before locking out an IP address.', 'so-ssl') . '</p>';
    }

    /**
     * Lockout duration field callback
     */
    public static function lockout_duration_callback() {
        $lockout_duration = get_option('so_ssl_lockout_duration', 15);

        echo '<input type="number" id="so_ssl_lockout_duration" name="so_ssl_lockout_duration" value="' . esc_attr($lockout_duration) . '" min="1" />';
        echo '<p class="description">' . __('How long (in minutes) an IP should be locked out after exceeding the maximum login attempts.', 'so-ssl') . '</p>';
    }

    /**
     * Long lockout settings field callback
     */
    public static function long_lockout_settings_callback() {
        $long_lockout_count = get_option('so_ssl_long_lockout_count', 3);
        $long_lockout_duration = get_option('so_ssl_long_lockout_duration', 24);

        echo '<div class="so-ssl-field-group">';
        echo '<label for="so_ssl_long_lockout_count">' . __('Lockout threshold:', 'so-ssl') . '</label> ';
        echo '<input type="number" id="so_ssl_long_lockout_count" name="so_ssl_long_lockout_count" value="' . esc_attr($long_lockout_count) . '" min="2" style="width: 60px;" /> ';
        echo __('lockouts', 'so-ssl');
        echo '</div>';

        echo '<div class="so-ssl-field-group">';
        echo '<label for="so_ssl_long_lockout_duration">' . __('Extended lockout duration:', 'so-ssl') . '</label> ';
        echo '<input type="number" id="so_ssl_long_lockout_duration" name="so_ssl_long_lockout_duration" value="' . esc_attr($long_lockout_duration) . '" min="1" style="width: 60px;" /> ';
        echo __('hours', 'so-ssl');
        echo '</div>';

        echo '<p class="description">' . __('After an IP has been locked out multiple times, enforce a longer lockout period.', 'so-ssl') . '</p>';
    }

    /**
     * Auto-blacklist field callback
     */
    public static function auto_blacklist_callback() {
        $auto_blacklist = get_option('so_ssl_auto_blacklist', 0);

        echo '<label for="so_ssl_auto_blacklist">';
        echo '<input type="checkbox" id="so_ssl_auto_blacklist" name="so_ssl_auto_blacklist" value="1" ' . checked(1, $auto_blacklist, false) . '/>';
        echo __('Automatically blacklist IPs after extended lockout', 'so-ssl');
        echo '</label>';
        echo '<p class="description">' . __('Permanently block IPs that trigger the extended lockout threshold.', 'so-ssl') . '</p>';
    }

    /**
     * Lockout notify field callback
     */
    public static function lockout_notify_callback() {
        $lockout_notify = get_option('so_ssl_lockout_notify', 0);

        echo '<label for="so_ssl_lockout_notify">';
        echo '<input type="checkbox" id="so_ssl_lockout_notify" name="so_ssl_lockout_notify" value="1" ' . checked(1, $lockout_notify, false) . '/>';
        echo __('Email admin when an IP is locked out or blacklisted', 'so-ssl');
        echo '</label>';
        echo '<p class="description">' . __('Sends an email notification when IPs trigger extended lockouts or are blacklisted.', 'so-ssl') . '</p>';
    }

    /**
     * Block type field callback
     */
    public static function block_type_callback() {
        $block_type = get_option('so_ssl_block_type', 'message');

        echo '<select id="so_ssl_block_type" name="so_ssl_block_type">';
        echo '<option value="message" ' . selected('message', $block_type, false) . '>' . __('Show message - Display explanation to blocked users', 'so-ssl') . '</option>';
        echo '<option value="silent" ' . selected('silent', $block_type, false) . '>' . __('Silent block - Return 403 error without explanation', 'so-ssl') . '</option>';
        echo '</select>';
        echo '<p class="description">' . __('How to handle blocked or locked out IPs.', 'so-ssl') . '</p>';
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
            if (isset($_SERVER[$key]) && filter_var($_SERVER[$key], FILTER_VALIDATE_IP)) {
                return $_SERVER[$key];
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
     * Check if an IP address is valid
     *
     * @param string $ip The IP address
     * @return bool Whether the IP is valid
     */
    public static function is_valid_ip($ip) {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }
}

// Register settings
add_action('admin_init', array('So_SSL_Login_Limit', 'register_settings'));

// Initialize the class
So_SSL_Login_Limit::init();
