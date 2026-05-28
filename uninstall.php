<?php
/**
 * Uninstall cleanup for So SSL.
 *
 * This file runs only when WordPress deletes the plugin from the Plugins screen.
 * Deactivation and updates do not run this cleanup, so settings are preserved
 * during normal updates.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// Plugin options and logs.
delete_option('so_ssl_options');
delete_option('so_ssl_2fa_log');

// Temporary transients created by login limiting, 2FA setup, fallback email, notices, and backup-code email flow.
$so_ssl_transient_prefixes = array(
    '_transient_so_ssl_',
    '_transient_timeout_so_ssl_',
);

foreach ($so_ssl_transient_prefixes as $so_ssl_prefix) {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall cleanup must delete plugin transients by prefix.
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            $wpdb->esc_like($so_ssl_prefix) . '%'
        )
    );
}

// User metadata owned by So SSL.
$so_ssl_user_meta_keys = array(
    'so_ssl_privacy_ack',
    'so_ssl_admin_agreement_ack',
    'so_ssl_last_login',
    'so_ssl_2fa_override',
    'so_ssl_2fa_enabled',
    'so_ssl_totp_secret',
    'so_ssl_2fa_backup_codes',
);

foreach ($so_ssl_user_meta_keys as $so_ssl_meta_key) {
    delete_metadata('user', 0, $so_ssl_meta_key, '', true);
}
