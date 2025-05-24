<?php
	/**
	 * Uninstall functionality for So SSL plugin
	 *
	 * This file runs when the plugin is uninstalled via the Plugins screen.
	 */

	// Exit if accessed directly
	if (!defined('WP_UNINSTALL_PLUGIN')) {
		exit;
	}

	// Remove all plugin options
	function so_ssl_delete_plugin_options()
	{
		// SSL options
		delete_option('so_ssl_force_ssl');

		// HSTS options
		delete_option('so_ssl_enable_hsts');
		delete_option('so_ssl_hsts_max_age');
		delete_option('so_ssl_hsts_subdomains');
		delete_option('so_ssl_hsts_preload');

		// X-Frame-Options
		delete_option('so_ssl_enable_xframe');
		delete_option('so_ssl_xframe_option');
		delete_option('so_ssl_xframe_allow_from');

		// CSP Frame-Ancestors
		delete_option('so_ssl_enable_csp_frame_ancestors');
		delete_option('so_ssl_csp_frame_ancestors_option');
		delete_option('so_ssl_csp_include_self');
		delete_option('so_ssl_csp_frame_ancestors_domains');

		// Referrer Policy
		delete_option('so_ssl_enable_referrer_policy');
		delete_option('so_ssl_referrer_policy_option');

		// Content Security Policy
		delete_option('so_ssl_enable_csp');
		delete_option('so_ssl_csp_mode');
		delete_option('so_ssl_csp_default_src');
		delete_option('so_ssl_csp_script_src');
		delete_option('so_ssl_csp_style_src');
		delete_option('so_ssl_csp_img_src');
		delete_option('so_ssl_csp_connect_src');
		delete_option('so_ssl_csp_font_src');
		delete_option('so_ssl_csp_object_src');
		delete_option('so_ssl_csp_media_src');
		delete_option('so_ssl_csp_frame_src');
		delete_option('so_ssl_csp_base_uri');
		delete_option('so_ssl_csp_form_action');
		delete_option('so_ssl_csp_upgrade_insecure_requests');

		// Permissions Policy
		delete_option('so_ssl_enable_permissions_policy');

		// Remove all permission policy options
		$permissions = array(
			'accelerometer',
			'ambient-light-sensor',
			'autoplay',
			'battery',
			'camera',
			'display-capture',
			'document-domain',
			'encrypted-media',
			'execution-while-not-rendered',
			'execution-while-out-of-viewport',
			'fullscreen',
			'geolocation',
			'gyroscope',
			'microphone',
			'midi',
			'navigation-override',
			'payment',
			'picture-in-picture',
			'publickey-credentials-get',
			'screen-wake-lock',
			'sync-xhr',
			'usb',
			'web-share',
			'xr-spatial-tracking'
		);

		foreach ($permissions as $permission) {
			$option_name = 'so_ssl_permissions_policy_' . str_replace('-', '_', $permission);
			delete_option($option_name);
		}

		// Admin excluded from privacy compliance
		delete_option('so_ssl_privacy_exempt_original_admin');

		// Two-Factor Authentication
		delete_option('so_ssl_enable_2fa');
		delete_option('so_ssl_2fa_user_roles');
		delete_option('so_ssl_2fa_method');

		// Login Protection
		delete_option('so_ssl_disable_weak_passwords');

		// Cross-Origin Policies
		delete_option('so_ssl_enable_cross_origin_embedder_policy');
		delete_option('so_ssl_cross_origin_embedder_policy_value');
		delete_option('so_ssl_enable_cross_origin_opener_policy');
		delete_option('so_ssl_cross_origin_opener_policy_value');
		delete_option('so_ssl_enable_cross_origin_resource_policy');
		delete_option('so_ssl_cross_origin_resource_policy_value');

		// User Sessions Management
		delete_option('so_ssl_enable_user_sessions');
		delete_option('so_ssl_max_sessions_per_user');
		delete_option('so_ssl_max_session_duration');

		// Login Limiting
		delete_option('so_ssl_enable_login_limit');
		delete_option('so_ssl_max_login_attempts');
		delete_option('so_ssl_lockout_duration');
		delete_option('so_ssl_long_lockout_count');
		delete_option('so_ssl_long_lockout_duration');
		delete_option('so_ssl_auto_blacklist');
		delete_option('so_ssl_lockout_notify');
		delete_option('so_ssl_block_type');
		delete_option('so_ssl_login_attempts');
		delete_option('so_ssl_login_history');
		delete_option('so_ssl_ip_whitelist');
		delete_option('so_ssl_ip_blacklist');

		// Delete all privacy compliance options
		delete_option('so_ssl_enable_privacy_compliance');
		delete_option('so_ssl_privacy_page_title');
		delete_option('so_ssl_privacy_page_slug');
		delete_option('so_ssl_privacy_notice_text');
		delete_option('so_ssl_privacy_checkbox_text');
		delete_option('so_ssl_privacy_expiry_days');
		delete_option('so_ssl_flush_rewrite_rules');

		// Delete all user meta related to privacy acknowledgments
		so_ssl_delete_all_privacy_acknowledgments();

		// Delete role-specific options
		delete_option('so_ssl_privacy_required_roles');
		delete_option('so_ssl_privacy_exempt_admins');

		// Admin Agreement options
		delete_option('so_ssl_enable_admin_agreement');
		delete_option('so_ssl_admin_agreement_title');
		delete_option('so_ssl_admin_agreement_text');
		delete_option('so_ssl_admin_agreement_checkbox_text');
		delete_option('so_ssl_admin_agreement_expiry_days');
		delete_option('so_ssl_admin_agreement_required_roles');
		delete_option('so_ssl_admin_agreement_exempt_original_admin');

		// Disable xml-rpc and rest-api option
		delete_option('so_ssl_disable_xmlrpc');
		delete_option('so_ssl_disable_rest_api');
	}

	// Remove all user meta related to 2FA
	function so_ssl_delete_user_meta()
	{
		global $wpdb;

		// Option 1: Use WordPress API (preferred)
		$users = get_users();
		foreach ($users as $user) {
			// Get all user meta keys
			$user_meta_keys = get_user_meta($user->ID);

			// Delete any keys that match our pattern
			foreach ($user_meta_keys as $meta_key => $meta_value) {
				if (strpos($meta_key, 'so_ssl_2fa_') === 0) {
					delete_user_meta($user->ID, $meta_key);
				}
			}
		}

		// Option 2: If performance is an issue with many users, use a safer direct query
		// $wpdb->query($wpdb->prepare(
		//     "DELETE FROM $wpdb->usermeta WHERE meta_key LIKE %s",
		//     'so_ssl_2fa_%'
		// ));
	}

	// Delete cron jobs
	function so_ssl_delete_cron_jobs()
	{
		wp_clear_scheduled_hook('so_ssl_cleanup_login_attempts');
		wp_clear_scheduled_hook('so_ssl_cleanup_expired_sessions');
	}

	// Delete user meta with key 'so_ssl_privacy_acknowledged' for all users using WordPress API instead of direct database query
	function so_ssl_delete_all_privacy_acknowledgments()
	{
		// The delete_metadata function is perfect for this use case
		// Parameters:
		// 1. 'user' - the meta type (user, post, comment, or term)
		// 2. 0 - user ID (not relevant when deleting for all users)
		// 3. 'so_ssl_privacy_acknowledged' - the meta key to delete
		// 4. '' - meta value (not relevant when deleting all entries)
		// 5. true - delete for all users
		delete_metadata('user', 0, 'so_ssl_privacy_acknowledged', '', true);

		return true;
	}

	// Run cleanup functions
	so_ssl_delete_plugin_options();
	so_ssl_delete_user_meta();
	so_ssl_delete_cron_jobs();