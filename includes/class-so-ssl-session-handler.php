<?php
/**
 * Session Handler for Two-Factor Authentication
 *
 * This file implements session handling for WordPress where native sessions
 * might not be available or appropriate. It uses secure cookies instead.
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

class So_SSL_Session_Handler {
	/**
	 * Initialize the session handler
	 */
	public static function init() {
		// Only initialize if 2FA is enabled
		if ( ! get_option( 'so_ssl_enable_2fa', 0 ) ) {
			return;
		}

		// Start session on login page
		add_action( 'login_init', array( __CLASS__, 'start_session' ) );

		// Clean up session on login/logout
		add_action( 'wp_login', array( __CLASS__, 'end_session' ) );
		add_action( 'wp_logout', array( __CLASS__, 'end_session' ) );
	}

	/**
	 * Start a secure session for 2FA
	 */
	public static function start_session() {
		// Only start session if not already started
		if ( session_status() === PHP_SESSION_NONE && ! headers_sent() ) {
			// Set secure session parameters
			$session_name = 'so_ssl_2fa_session';
			$secure       = is_ssl();
			$httponly     = true;

			// Set session cookie parameters
			session_set_cookie_params( [
				'lifetime' => 900, // 15 minutes
				'path'     => COOKIEPATH,
				'domain'   => COOKIE_DOMAIN,
				'secure'   => $secure,
				'httponly' => $httponly,
				'samesite' => 'Strict'
			] );

			session_name( $session_name );
			session_start();
		}
	}

	/**
	 * Store a session value
	 *
	 * @param string $key The session key
	 * @param mixed $value The value to store
	 */
	public static function set( $key, $value ) {
		self::start_session();
		$_SESSION[ $key ] = $value;
	}

	/**
	 * Get a session value
	 *
	 * @param string $key The session key
	 * @param mixed $default The default value if key doesn't exist
	 *
	 * @return mixed The session value or default
	 */
	public static function get( $key, $default = null ) {
		self::start_session();

		// Ensure $key is a string and sanitize it
		$sanitized_key = is_string( $key ) ? sanitize_key( $key ) : '';

		// Get the value and sanitize it before returning
		if ( isset( $_SESSION[ $sanitized_key ] ) ) {
			$value = sanitize_key( $_SESSION[ $sanitized_key ] );

			// Sanitize based on the value type
			if ( is_string( $value ) ) {
				return sanitize_text_field( $value );
			} elseif ( is_array( $value ) ) {
				return array_map( 'sanitize_text_field', $value );
			} else {
				return $value; // Numeric or boolean values
			}
		}

		return $default;
	}

	/**
	 * Remove a session value
	 *
	 * @param string $key The session key
	 */
	public static function remove( $key ) {
		self::start_session();
		if ( isset( $_SESSION[ $key ] ) ) {
			unset( $_SESSION[ $key ] );
		}
	}

	/**
	 * Check if a session value exists
	 *
	 * @param string $key The session key
	 *
	 * @return bool Whether the key exists
	 */
	public static function has( $key ) {
		self::start_session();

		return isset( $_SESSION[ $key ] );
	}

	/**
	 * End the session and clean up
	 */
	public static function end_session() {
		if ( session_status() === PHP_SESSION_ACTIVE ) {
			session_destroy();
		}

		// Clear the session cookie
		if ( isset( $_COOKIE[ session_name() ] ) ) {
			$params = session_get_cookie_params();
			setcookie(
				session_name(),
				'',
				time() - 42000,
				$params['path'],
				$params['domain'],
				$params['secure'],
				$params['httponly']
			);
		}
	}

	/**
	 * Alternative cookie-based session for environments where PHP sessions don't work well
	 *
	 * @param string $key The session key
	 * @param mixed $value The value to store (null to get value)
	 * @param bool $remove Whether to remove the value
	 *
	 * @return mixed The session value or null
	 */
	public static function cookie_session( $key, $value = null, $remove = false ) {
		$session_name = 'so_ssl_2fa_' . $key;

		// Get value
		if ( $value === null && ! $remove ) {
			return isset( $_COOKIE[ $session_name ] ) ?
				sanitize_text_field( wp_unslash( $_COOKIE[ $session_name ] ) ) : null;
		}

		// Remove value
		if ( $remove ) {
			setcookie(
				$session_name,
				'',
				time() - 3600,
				COOKIEPATH,
				COOKIE_DOMAIN,
				is_ssl(),
				true
			);

			return null;
		}

		// Set value
		setcookie(
			$session_name,
			$value,
			time() + 900, // 15 minutes
			COOKIEPATH,
			COOKIE_DOMAIN,
			is_ssl(),
			true
		);

		return $value;
	}

}

// Initialize the session handler
So_SSL_Session_Handler::init();
