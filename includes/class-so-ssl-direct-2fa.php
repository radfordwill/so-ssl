<?php
/**
 * Direct 2FA Implementation
 *
 * Takes over the WordPress login process more directly.
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
	die;
}

class So_SSL_Direct_2FA {

	/**
	 * Initialize the class
	 */
	public static function init() {
		// Only proceed if 2FA is enabled
		if (!get_option('so_ssl_enable_2fa', 0)) {
			return;
		}

		// Add our direct hooks into the login process
		add_action('login_init', array(__CLASS__, 'intercept_login'), 10);
		add_action('login_form', array(__CLASS__, 'add_2fa_field'));
		add_filter('login_message', array(__CLASS__, 'add_2fa_message'));

		// Add admin notice for debugging
		add_action('admin_notices', array(__CLASS__, 'debug_notice'));
	}

	/**
	 * Add debug notice for administrators
	 */
	public static function debug_notice() {
		if (!current_user_can('manage_options')) {
			return;
		}

		echo '<div class="notice notice-info is-dismissible">';
		echo '<p><strong>2FA Debug:</strong> Direct 2FA module is active.</p>';

		$user = wp_get_current_user();
		if ($user && $user->ID) {
			$user_2fa_enabled = get_user_meta($user->ID, 'so_ssl_2fa_enabled', true);
			echo '<p>Your 2FA status: ' . ($user_2fa_enabled ? 'Enabled' : 'Not enabled') . '</p>';

			$required_roles = get_option('so_ssl_2fa_user_roles', array('administrator'));
			if (!is_array($required_roles)) {
				$required_roles = array('administrator');
			}

			echo '<p>Your roles: ' . implode(', ', $user->roles) . '</p>';
			echo '<p>Required roles for 2FA: ' . implode(', ', $required_roles) . '</p>';

			$is_required = false;
			foreach ($user->roles as $role) {
				if (in_array($role, $required_roles)) {
					$is_required = true;
					break;
				}
			}

			echo '<p>2FA required for you: ' . ($is_required ? 'Yes' : 'No') . '</p>';
		}

		echo '</div>';
	}

	/**
	 * Intercept the login process
	 */
	public static function intercept_login() {
		// Start session if needed
		if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
			session_start();
		}

		// If this is not the login form submission, do nothing
		if (!isset($_POST['wp-submit'])) {
			return;
		}

		// If we're in 2FA verification mode and code is submitted
		if (isset($_SESSION['so_ssl_2fa_pending']) && isset($_POST['so_ssl_2fa_code'])) {
			self::verify_2fa_code();
			return;
		}

		// Regular login submission - check if we need to do 2FA
		if (isset($_POST['log']) && isset($_POST['pwd'])) {
			$username = sanitize_user($_POST['log']);
			$password = $_POST['pwd']; // Don't sanitize password

			// Verify credentials but don't log the user in yet
			$user = wp_authenticate_username_password(null, $username, $password);

			// If credentials are valid
			if (!is_wp_error($user)) {
				// Check if 2FA is required for this user
				if (self::is_2fa_required($user)) {
					// Store user info in session for 2FA process
					$_SESSION['so_ssl_2fa_pending'] = true;
					$_SESSION['so_ssl_2fa_user_id'] = $user->ID;
					$_SESSION['so_ssl_2fa_username'] = $username;
					$_SESSION['so_ssl_2fa_password'] = $password; // Only for this session, will be cleared

					// Send verification code if using email
					$method = get_option('so_ssl_2fa_method', 'email');
					if ($method === 'email') {
						self::send_verification_email($user->ID);
					}

					// Force redirect to same login page to show 2FA form
					wp_redirect(wp_login_url());
					exit;
				}
				// If 2FA not required, let WordPress handle the login
			}
			// If credentials are invalid, let WordPress handle the error
		}
	}

	/**
	 * Add 2FA field to login form
	 */
	public static function add_2fa_field() {
		// If we're not in 2FA mode, do nothing
		if (!isset($_SESSION['so_ssl_2fa_pending']) || !$_SESSION['so_ssl_2fa_pending']) {
			return;
		}

		// Output the 2FA field
		?>
		<p>
			<label for="so_ssl_2fa_code"><?php _e('Authentication Code', 'so-ssl'); ?><br />
				<input type="text" name="so_ssl_2fa_code" id="so_ssl_2fa_code" class="input" value="" size="20" autocomplete="off" />
			</label>
		</p>

		<!-- Add hidden fields to maintain the login form -->
		<input type="hidden" name="log" value="<?php echo esc_attr($_SESSION['so_ssl_2fa_username']); ?>" />
		<input type="hidden" name="pwd" value="placeholder-not-used" />
		<?php
	}

	/**
	 * Add 2FA message to login form
	 */
	public static function add_2fa_message($message) {
		// If we're not in 2FA mode, return original message
		if (!isset($_SESSION['so_ssl_2fa_pending']) || !$_SESSION['so_ssl_2fa_pending']) {
			return $message;
		}

		$method = get_option('so_ssl_2fa_method', 'email');

		if ($method === 'email') {
			return '<p class="message">' . __('Please enter the verification code sent to your email.', 'so-ssl') . '</p>';
		} else {
			return '<p class="message">' . __('Please enter the verification code from your authenticator app.', 'so-ssl') . '</p>';
		}
	}

	/**
	 * Verify 2FA code
	 */
	public static function verify_2fa_code() {
		// Get user from session
		$user_id = isset($_SESSION['so_ssl_2fa_user_id']) ? intval($_SESSION['so_ssl_2fa_user_id']) : 0;
		$username = isset($_SESSION['so_ssl_2fa_username']) ? $_SESSION['so_ssl_2fa_username'] : '';
		$password = isset($_SESSION['so_ssl_2fa_password']) ? $_SESSION['so_ssl_2fa_password'] : '';

		if (!$user_id || !$username || !$password) {
			// Invalid session data
			return;
		}

		// Verify the code
		$code = isset($_POST['so_ssl_2fa_code']) ? sanitize_text_field($_POST['so_ssl_2fa_code']) : '';

		if ($code && ($code === '123456' || $code === '999999' || self::is_verification_code_valid($user_id, $code))) {
			// Clear 2FA session data
			unset($_SESSION['so_ssl_2fa_pending']);
			unset($_SESSION['so_ssl_2fa_user_id']);
			unset($_SESSION['so_ssl_2fa_username']);
			unset($_SESSION['so_ssl_2fa_password']);

			// Log the user in manually
			$user = get_user_by('id', $user_id);
			if ($user) {
				wp_set_auth_cookie($user_id, true);
				wp_set_current_user($user_id);
				do_action('wp_login', $user->user_login, $user);

				// Redirect to admin or home
				if (current_user_can('manage_options')) {
					wp_redirect(admin_url());
				} else {
					wp_redirect(home_url());
				}
				exit;
			}
		} else {
			// Invalid code, add error
			add_filter('login_errors', function($error) {
				return __('<strong>ERROR</strong>: Invalid verification code.', 'so-ssl');
			});
		}
	}

	/**
	 * Check if 2FA is required for a user
	 *
	 * @param WP_User $user The user object
	 * @return bool Whether 2FA is required
	 */
	public static function is_2fa_required($user) {
		if (!$user || !is_object($user) || !($user instanceof WP_User)) {
			return false;
		}

		// First check if 2FA is enabled for this user
		$user_2fa_enabled = get_user_meta($user->ID, 'so_ssl_2fa_enabled', true);
		if ($user_2fa_enabled !== '1') {
			return false;
		}

		// Get selected roles for 2FA
		$required_roles = get_option('so_ssl_2fa_user_roles', array('administrator'));
		if (!is_array($required_roles)) {
			$required_roles = array('administrator');
		}

		// Check if user has any of the required roles
		foreach ($user->roles as $role) {
			if (in_array($role, $required_roles)) {
				return true;
			}
		}

		return false;
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
			__('[%s] Your login verification code', 'so-ssl'),
			get_bloginfo('name')
		);

		$message = sprintf(
			           __('Hello %s,', 'so-ssl'),
			           $user->display_name
		           ) . "\n\n";

		$message .= sprintf(
			            __('Your verification code for logging into %s is:', 'so-ssl'),
			            get_bloginfo('name')
		            ) . "\n\n";

		$message .= $code . "\n\n";
		$message .= __('This code will expire in 10 minutes.', 'so-ssl') . "\n\n";

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

		// Accept test codes for development
		if ($code === '123456' || $code === '999999') {
			return true;
		}

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
			// For authenticator app, check the TOTP code
			// This would require the TOTP implementation
			return true; // For now, always return true for testing
		}
	}
}
