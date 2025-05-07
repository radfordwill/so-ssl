<?php
/**
 * Two-Factor Authentication Implementation for So SSL Plugin
 *
 * This file implements the two-factor authentication functionality for the So SSL plugin.
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
	die;
}

/**
 * Class to handle Two-Factor Authentication
 */
class So_SSL_Two_Factor {

    /**
     * Initialize the Two-Factor Authentication functionality
     */
    public static function init() {
        // Only proceed if 2FA is enabled
        if (!get_option('so_ssl_enable_2fa', 0)) {
            return;
        }

        // Add user profile fields for 2FA setup
        add_action('show_user_profile', array(__CLASS__, 'add_2fa_user_fields'));
        add_action('edit_user_profile', array(__CLASS__, 'add_2fa_user_fields'));

        // Save user profile fields
        add_action('personal_options_update', array(__CLASS__, 'save_2fa_user_fields'));
        add_action('edit_user_profile_update', array(__CLASS__, 'save_2fa_user_fields'));

        // Hook into the authentication process
        add_filter('authenticate', array(__CLASS__, 'authenticate_2fa'), 999, 3);

        // Add login form fields for 2FA
        add_action('login_form', array(__CLASS__, 'add_2fa_login_fields'));

        // Custom login messages
        add_filter('login_message', array(__CLASS__, 'login_message'));

        // Handle 2FA verification during login
        add_action('wp_login', array(__CLASS__, 'verify_2fa'), 10, 2);

        // Enqueue scripts and styles
        add_action('login_enqueue_scripts', array(__CLASS__, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_scripts'));
    }

    /**
     * Check if 2FA is required for a user
     *
     * @param WP_User $user The user object
     * @return bool Whether 2FA is required
     */
    public static function is_2fa_required_for_user($user) {
        if (!$user || !is_object($user) || !($user instanceof WP_User)) {
            return false;
        }

        // Get selected roles for 2FA
        $required_roles = get_option('so_ssl_2fa_user_roles', array('administrator'));

        // If not an array, convert it
        if (!is_array($required_roles)) {
            $required_roles = array('administrator');
        }

        // Check if user has any of the required roles
        $user_roles = $user->roles;

        foreach ($user_roles as $role) {
            if (in_array($role, $required_roles)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Add 2FA fields to user profile
     *
     * @param WP_User $user The user object
     */
    public static function add_2fa_user_fields($user) {
        // Only show if 2FA is required for this user
        if (!self::is_2fa_required_for_user($user)) {
            return;
        }

        // Get current 2FA status for user
        $enabled = get_user_meta($user->ID, 'so_ssl_2fa_enabled', true);
        $method = get_option('so_ssl_2fa_method', 'email');

        ?>
        <h2><?php esc_html_e('Two-Factor Authentication', 'so-ssl'); ?></h2>        <table class="form-table">
            <tr>
                <th><label for="so_ssl_2fa_enabled"><?php esc_html_e('Enable Two-Factor Authentication', 'so-ssl'); ?></label></th>
                <td>
                    <input type="checkbox" name="so_ssl_2fa_enabled" id="so_ssl_2fa_enabled" value="1" <?php checked($enabled, '1'); ?> />
                    <p class="description"><?php esc_html_e('Enable two-factor authentication for your account.', 'so-ssl'); ?></p>
                </td>
            </tr>

            <?php if ($method === 'authenticator'): ?>
                <tr id="so_ssl_2fa_authenticator_row" style="<?php echo ($enabled !== '1') ? 'display:none;' : ''; ?>">
                    <th><?php esc_html_e('Authenticator App Setup', 'so-ssl'); ?></th>
                    <td>
                        <?php self::display_authenticator_setup($user); ?>
                    </td>
                </tr>
            <?php else: ?>
                <tr id="so_ssl_2fa_email_row" style="<?php echo esc_attr(($enabled !== '1') ? 'display:none;' : ''); ?>"
                <th><?php esc_html_e('Email Authentication', 'so-ssl'); ?></th>
                <td>
                    <p><?php
                        /* translators: %s: User email address */
                        printf(esc_html__('Verification codes will be sent to your email address: %s', 'so-ssl'), esc_html($user->user_email));
                        ?>
                    </p>
                </td>
                </tr>
            <?php endif; ?>

            <tr id="so_ssl_2fa_backup_codes_row" style="<?php echo ($enabled !== '1') ? 'display:none;' : ''; ?>">
                <th><?php esc_html_e('Backup Codes', 'so-ssl'); ?></th>
                <td>
                    <button type="button" id="so_ssl_generate_backup_codes" class="button"><?php esc_html_e('Generate Backup Codes', 'so-ssl'); ?></button>
                    <div id="so_ssl_backup_codes_container" style="display:none; margin-top: 10px;">
                        <p><?php esc_html_e('Save these backup codes in a safe place. They can be used if you lose access to your authentication method.', 'so-ssl'); ?></p>
                        <div id="so_ssl_backup_codes"></div>
                    </div>
                </td>
            </tr>
        </table>

        <script>
            jQuery(document).ready(function($) {
                // Toggle 2FA fields when checkbox changes
                $('#so_ssl_2fa_enabled').on('change', function() {
                    if ($(this).is(':checked')) {
                        $('#so_ssl_2fa_authenticator_row, #so_ssl_2fa_email_row, #so_ssl_2fa_backup_codes_row').show();
                    } else {
                        $('#so_ssl_2fa_authenticator_row, #so_ssl_2fa_email_row, #so_ssl_2fa_backup_codes_row').hide();
                    }
                });

                // Handle backup codes generation
                $('#so_ssl_generate_backup_codes').on('click', function() {
                    var data = {
                        'action': 'so_ssl_generate_backup_codes',
                        'user_id': <?php echo esc_js($user->ID); ?>,
                        'nonce': '<?php echo esc_js(wp_create_nonce('so_ssl_2fa_nonce')); ?>'
                    };

                    $.post(ajaxurl, data, function(response) {
                        if (response.success) {
                            $('#so_ssl_backup_codes').html(response.data.codes_html);
                            $('#so_ssl_backup_codes_container').show();
                        } else {
                            alert(response.data.message);
                        }
                    });
                });
            });
        </script>
        <?php
    }

    /**
     * Display Authenticator App setup UI
     *
     * @param WP_User $user The user object
     */
    public static function display_authenticator_setup($user) {
        // Get or generate secret key
        $secret = get_user_meta($user->ID, 'so_ssl_2fa_secret', true);
        if (empty($secret)) {
            // Include the necessary library for TOTP
            if (!class_exists('TOTP')) {
                require_once SO_SSL_PATH . 'includes/class-so-ssl-totp.php';
            }

            $secret = TOTP::generateSecret();
            update_user_meta($user->ID, 'so_ssl_2fa_secret', $secret);
        }

        // Generate QR code URL
        $site_name = get_bloginfo('name');
        $user_identifier = $user->user_email;
        $totp_url = "otpauth://totp/" . urlencode($site_name) . ":" . urlencode($user_identifier) . "?secret=" . $secret . "&issuer=" . urlencode($site_name);

        // Generate QR code
        $qr_code_url = "https://chart.googleapis.com/chart?chs=200x200&chld=M|0&cht=qr&chl=" . urlencode($totp_url);

        ?>
        <div class="so-ssl-authenticator-setup">
            <p><?php esc_html_e('Scan this QR code with your authenticator app (like Google Authenticator, Authy, or Microsoft Authenticator).', 'so-ssl'); ?></p>
            <div class="so-ssl-qr-code">
                <img src="<?php echo esc_url($qr_code_url); ?>" alt="<?php esc_html_e('QR Code', 'so-ssl'); ?>" />
            </div>
            <p><?php esc_html_e('Or manually enter this code into your app:', 'so-ssl'); ?> <code><?php echo esc_html($secret); ?></code></p>

            <div class="so-ssl-verify-code">
                <p><?php esc_html_e('Verify that your authenticator app is working by entering a code below:', 'so-ssl'); ?></p>
                <input type="text" id="so_ssl_verify_code" name="so_ssl_verify_code" class="regular-text" />
                <button type="button" id="so_ssl_verify_code_button" class="button"><?php esc_html_e('Verify Code', 'so-ssl'); ?></button>
                <div id="so_ssl_verify_result"></div>
            </div>
        </div>

        <script>
            jQuery(document).ready(function($) {
                $('#so_ssl_verify_code_button').on('click', function() {
                    var code = $('#so_ssl_verify_code').val();
                    var data = {
                        'action': 'so_ssl_verify_totp_code',
                        'user_id': <?php echo esc_js($user->ID); ?>,
                        'code': code,
                        'nonce': '<?php echo esc_js(wp_create_nonce('so_ssl_2fa_nonce')); ?>'
                    };

                    $.post(ajaxurl, data, function(response) {
                        if (response.success) {
                            $('#so_ssl_verify_result').html('<span style="color:green;">' + response.data.message + '</span>');
                        } else {
                            $('#so_ssl_verify_result').html('<span style="color:red;">' + response.data.message + '</span>');
                        }
                    });
                });
            });
        </script>
        <?php
    }

    /**
     * Save 2FA user fields
     *
     * @param int $user_id The user ID
     */
    public static function save_2fa_user_fields($user_id) {
        // Check for nonce - explicitly for code analyzer
        if (!isset($_POST['_wpnonce'])) {
            return;
        }

        if (!current_user_can('edit_user', $user_id)) {
            return;
        }

        // Only proceed if 2FA is required for this user
        $user = get_userdata($user_id);
        if (!self::is_2fa_required_for_user($user)) {
            return;
        }

        // Verify nonce
        check_admin_referer('update-user_' . $user_id);

        // Save 2FA enabled status - note no need to unslash as we're just checking if it's set
        $enabled = isset($_POST['so_ssl_2fa_enabled']) ? '1' : '0';
        update_user_meta($user_id, 'so_ssl_2fa_enabled', $enabled);
    }

    /**
     * Add 2FA fields to login form
     */
    public static function add_2fa_login_fields() {
        // Get session information - Sanitize when retrieving
        $requires_2fa = isset($_SESSION['so_ssl_2fa_required']) ? (bool)$_SESSION['so_ssl_2fa_required'] : false;

        if ($requires_2fa) {
            // Add nonce field for CSRF protection
            wp_nonce_field('so_ssl_2fa_verification', 'so_ssl_2fa_nonce');
            ?>
            <p>
                <label for="so_ssl_2fa_code"><?php esc_html_e('Authentication Code', 'so-ssl'); ?><br />
                    <input type="text" name="so_ssl_2fa_code" id="so_ssl_2fa_code" class="input" size="20" autocomplete="off" />
                </label>
            </p>
            <?php
        }
    }

    /**
     * Filter for login message
     *
     * @param string $message The login message
     * @return string Modified login message
     */
    public static function login_message($message) {
        // Get session information - Sanitize when retrieving
        $requires_2fa = isset($_SESSION['so_ssl_2fa_required']) ? (bool)$_SESSION['so_ssl_2fa_required'] : false;

        if ($requires_2fa) {
            $user_id = isset($_SESSION['so_ssl_2fa_user_id']) ? absint($_SESSION['so_ssl_2fa_user_id']) : 0;
            $method = get_option('so_ssl_2fa_method', 'email');

            if ($method === 'email') {
                // Send verification code via email
                self::send_verification_email($user_id);

                $message .= '<p class="message">' . __('Please enter the verification code sent to your email.', 'so-ssl') . '</p>';
            } else {
                $message .= '<p class="message">' . __('Please enter the verification code from your authenticator app.', 'so-ssl') . '</p>';
            }
        }

        return $message;
    }

    /**
     * Modify authentication logic to include 2FA
     *
     * @param WP_User|WP_Error|null $user User object, WP_Error, or null
     * @param string $username The username
     * @param string $password The password
     * @return WP_User|WP_Error User object or error
     */
    public static function authenticate_2fa($user, $username, $password) {
        // If already errored, return the error
        if (is_wp_error($user)) {
            return $user;
        }

        // If not a user object, return as is
        if (!is_a($user, 'WP_User')) {
            return $user;
        }

        // Check if 2FA is enabled and required for this user
        $user_2fa_enabled = get_user_meta($user->ID, 'so_ssl_2fa_enabled', true);

        if (!self::is_2fa_required_for_user($user) || $user_2fa_enabled !== '1') {
            return $user;
        }

        // Start session for 2FA
        if (!session_id()) {
            session_start();
        }

        // Check if this is a 2FA verification attempt
        if (isset($_POST['so_ssl_2fa_code'])) {
            // Verify nonce before processing the 2FA code
            // Unslash and sanitize the nonce
            if (!isset($_POST['so_ssl_2fa_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['so_ssl_2fa_nonce'])), 'so_ssl_2fa_verification')) {
                return new WP_Error('invalid_nonce', __('<strong>ERROR</strong>: Security verification failed. Please try again.', 'so-ssl'));
            }

            // Unslash before sanitizing
            $code = sanitize_text_field(wp_unslash($_POST['so_ssl_2fa_code']));

            // Verify the code
            if (self::is_verification_code_valid($user->ID, $code) || self::is_backup_code_valid($user->ID, $code)) {
                // Code is valid, clear session data
                unset($_SESSION['so_ssl_2fa_required']);
                unset($_SESSION['so_ssl_2fa_user_id']);

                // Allow login
                return $user;
            } else {
                // Invalid code
                return new WP_Error('invalid_2fa_code', __('<strong>ERROR</strong>: Invalid verification code.', 'so-ssl'));
            }
        } else {
            // Set session data for 2FA verification - Sanitize when setting
            $_SESSION['so_ssl_2fa_required'] = true; // Boolean value is inherently safe
            $_SESSION['so_ssl_2fa_user_id'] = absint($user->ID); // Use absint to ensure it's a positive integer

            // Prevent login until 2FA is verified
            return new WP_Error('2fa_required', __('<strong>INFO</strong>: Please enter your two-factor authentication code.', 'so-ssl'));
        }
    }

    /**
     * Send verification code via email
     *
     * @param int $user_id The user ID
     * @return bool Success or failure
     */
    public static function send_verification_email($user_id) {
        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }

        // Generate a code
        $code = wp_rand(100000, 999999);

        // Store code in user meta with expiration
        update_user_meta($user_id, 'so_ssl_2fa_email_code', $code);
        update_user_meta($user_id, 'so_ssl_2fa_email_code_time', time());

        // Prepare email
        $subject = sprintf(
        /* translators: %s: Site name */
            __('[%s] Your login verification code', 'so-ssl'),
            get_bloginfo('name')
        );

        $message = sprintf(
            /* translators: %s: User's display name */
                __('Hello %s,', 'so-ssl'),
                $user->display_name
            ) . "\n\n";

        $message .= sprintf(
            /* translators: %s: Site name */
                __('Your verification code for logging into %s is:', 'so-ssl'),
                get_bloginfo('name')
            ) . "\n\n";

        $message .= $code . "\n\n";
        $message .= __('This code will expire in 10 minutes.', 'so-ssl') . "\n\n";

        $message .= sprintf(
        /* translators: %s: Site name */
            __('If you did not attempt to log in to %s, please contact your site administrator immediately.', 'so-ssl'),
            get_bloginfo('name')
        );

        // Send email
        return wp_mail($user->user_email, $subject, $message);
    }

    /**
     * Check if the provided verification code is valid
     *
     * @param int $user_id The user ID
     * @param string $code The verification code
     * @return bool Whether the code is valid
     */
    public static function is_verification_code_valid($user_id, $code) {
        $method = get_option('so_ssl_2fa_method', 'email');

        if ($method === 'email') {
            // Verify email code
            $stored_code = get_user_meta($user_id, 'so_ssl_2fa_email_code', true);
            $stored_time = get_user_meta($user_id, 'so_ssl_2fa_email_code_time', true);

            // Check if code is expired (10 minutes)
            if (time() - intval($stored_time) > 600) {
                return false;
            }

            // Check if code matches
            return $code == $stored_code;
        } else {
            // Verify TOTP code
            if (!class_exists('TOTP')) {
                require_once SO_SSL_PATH . 'includes/totp.php';
            }

            $secret = get_user_meta($user_id, 'so_ssl_2fa_secret', true);
            if (empty($secret)) {
                return false;
            }

            return TOTP::verifyCode($secret, $code);
        }
    }

    /**
     * Check for backup code
     *
     * @param int $user_id The user ID
     * @param string $code The backup code
     * @return bool Whether the backup code is valid
     */
    public static function is_backup_code_valid($user_id, $code) {
        $backup_codes = get_user_meta($user_id, 'so_ssl_2fa_backup_codes', true);

        if (!is_array($backup_codes)) {
            return false;
        }

        $code = trim($code);

        foreach ($backup_codes as $key => $backup_code) {
            if ($backup_code === $code) {
                // Remove the used backup code
                unset($backup_codes[$key]);
                update_user_meta($user_id, 'so_ssl_2fa_backup_codes', $backup_codes);
                return true;
            }
        }

        return false;
    }

    /**
     * Verify 2FA during login
     *
     * @param string $user_login The username
     * @param WP_User $user The user object
     */
    public static function verify_2fa($user_login, $user) {
        // This function can be extended if needed for additional verification steps
    }

    /**
     * Enqueue scripts and styles
     */
    public static function enqueue_scripts() {
        wp_enqueue_style('so-ssl-2fa', SO_SSL_URL . 'assets/css/so-ssl-2fa.css', array(), SO_SSL_VERSION);
        wp_enqueue_script('so-ssl-2fa', SO_SSL_URL . 'assets/js/so-ssl-2fa.js', array('jquery'), SO_SSL_VERSION, true);
    }

    /**
     * AJAX handler for generating backup codes
     */
    public static function generate_backup_codes() {
        check_ajax_referer('so_ssl_2fa_nonce', 'nonce');

        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

        if (!current_user_can('edit_user', $user_id)) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'so-ssl')));
        }

        // Generate 10 backup codes
        $backup_codes = array();
        for ($i = 0; $i < 10; $i++) {
            $backup_codes[] = self::generate_backup_code();
        }

        // Save to user meta
        update_user_meta($user_id, 'so_ssl_2fa_backup_codes', $backup_codes);

        // Prepare HTML for display
        $codes_html = '<ul class="so-ssl-backup-codes">';
        foreach ($backup_codes as $code) {
            $codes_html .= '<li><code>' . esc_html($code) . '</code></li>';
        }
        $codes_html .= '</ul>';

        wp_send_json_success(array('codes_html' => $codes_html));
    }

    /**
     * Generate a random backup code
     *
     * @return string The backup code
     */
    public static function generate_backup_code() {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $code = '';

        for ($i = 0; $i < 10; $i++) {
            $code .= $chars[wp_rand(0, strlen($chars) - 1)];
        }

        return $code;
    }

    /**
     * AJAX handler for verifying TOTP code
     */
    public static function verify_totp_code() {
        check_ajax_referer('so_ssl_2fa_nonce', 'nonce');

        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        // Unslash before sanitizing
        $code = isset($_POST['code']) ? sanitize_text_field(wp_unslash($_POST['code'])) : '';

        if (!current_user_can('edit_user', $user_id)) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'so-ssl')));
        }

        if (empty($code)) {
            wp_send_json_error(array('message' => __('Please enter a verification code.', 'so-ssl')));
        }

        // Verify the code
        if (!class_exists('TOTP')) {
            require_once SO_SSL_PATH . 'includes/totp.php';
        }

        $secret = get_user_meta($user_id, 'so_ssl_2fa_secret', true);
        if (empty($secret)) {
            wp_send_json_error(array('message' => __('Secret key not found.', 'so-ssl')));
        }

        if (TOTP::verifyCode($secret, $code)) {
            // Code is valid
            update_user_meta($user_id, 'so_ssl_2fa_verified', 1);
            wp_send_json_success(array('message' => __('Code verified successfully!', 'so-ssl')));
        } else {
            wp_send_json_error(array('message' => __('Invalid code. Please try again.', 'so-ssl')));
        }
    }
}

// Initialize the class
So_SSL_Two_Factor::init();

// Register AJAX handlers
add_action('wp_ajax_so_ssl_generate_backup_codes', array('So_SSL_Two_Factor', 'generate_backup_codes'));
add_action('wp_ajax_so_ssl_verify_totp_code', array('So_SSL_Two_Factor', 'verify_totp_code'));
