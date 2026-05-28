# So SSL

A WordPress security and privacy workflow plugin for SSL enforcement, security headers, two-factor authentication, login protection, session management, and privacy acknowledgment tools.

![So SSL banner](assets/banner-772x250.png)

## Overview

**So SSL** helps WordPress site owners harden their sites by combining common SSL, HTTP security header, login protection, two-factor authentication, and privacy acknowledgment features in one plugin.

The plugin is designed for site administrators who want practical controls inside WordPress without manually editing server configuration for every setting.

> Privacy and compliance features are provided as workflow tools only. They are not legal advice and do not guarantee compliance with any law or regulation.

## Features

### SSL Enforcement

- Force traffic to use HTTPS/SSL.
- Redirect HTTP visitors to HTTPS.
- Support common WordPress themes and plugins.

### Search Engine Indexing Protection

- Optional setting to discourage indexing by search engines.
- Adds robots meta protection when enabled.
- Adds `X-Robots-Tag` header protection when enabled.
- Adds a `robots.txt` disallow rule when enabled.

### Security Headers

- HTTP Strict Transport Security, or HSTS.
- Content Security Policy, or CSP.
- X-Frame-Options clickjacking protection.
- Referrer Policy controls.
- Permissions Policy controls.
- Cross-Origin policy controls.

### Privacy Acknowledgment Tools

- Custom privacy acknowledgment workflow for logged-in users.
- Role-based privacy acknowledgment requirements.
- Optional re-acknowledgment expiration.
- Admin preview tools.
- Modal-based user acknowledgment flow.

### Administrator Agreement

- Require administrators to accept plugin terms before using protected plugin features.
- Customizable agreement text and checkbox label.
- Role-based requirements and exemptions.
- Optional periodic re-acknowledgment.
- Emergency override support for lockout prevention.

### Two-Factor Authentication

- Master toggle for all So SSL 2FA behavior.
- Email verification code option.
- Authenticator app support using TOTP.
- QR code setup for authenticator apps when available.
- Website/domain-aware authenticator app labels.
- Authenticator app link dropdown for common apps.
- Role-based 2FA requirements.
- Per-user 2FA overrides.
- Mandatory setup redirect for required users.
- Backup recovery codes.
- Optional email delivery for newly generated backup codes.
- Optional fallback email code for authenticator users.
- Recent 2FA attempt logging.
- Admin recovery tools.

### Login Protection

- Strong password enforcement.
- Password validation and strength checking.
- Weak password prevention.

### User Session Management

- View active user sessions.
- Terminate sessions for specific users or devices.
- Limit concurrent sessions.
- Configure maximum session duration.

### Login Limiting

- Brute-force protection.
- Custom lockout settings.
- IP whitelist and blacklist controls.
- Email notifications for lockouts.

## Screenshots

The plugin includes WordPress.org-style screenshots in the `assets` directory.

1. `assets/screenshot-1.png` — Main So SSL settings page with admin notice, banner, security score, and tabbed settings.
2. `assets/screenshot-2.png` — Two-factor authentication login screen with authenticator app and fallback email code options.
3. `assets/screenshot-3.png` — Privacy settings tab within the So SSL settings interface.
4. `assets/screenshot-4.png` — User profile authenticator app 2FA setup area with status, manual setup key, and QR code.

## Requirements

- WordPress 6.5 or higher.
- PHP 7.0 or higher.
- A valid SSL certificate is required before enabling forced HTTPS.

## Installation

### From a ZIP file

1. Download the plugin ZIP.
2. In WordPress, go to **Plugins > Add New > Upload Plugin**.
3. Upload the ZIP file.
4. Activate **So SSL**.
5. Open the **So SSL** admin menu and configure the plugin settings.

### Manual installation

1. Upload the `so-ssl` folder to `/wp-content/plugins/`.
2. Activate the plugin from the WordPress **Plugins** screen.
3. Open the **So SSL** admin menu and configure the plugin settings.

## Two-Factor Authentication Notes

So SSL supports authenticator app codes using standard TOTP codes. When setting up an authenticator app, the plugin generates a QR code and a manual setup key.

The authenticator app label uses the site domain when possible, for example:

```text
otpauth://totp/example.com:username?secret=...&issuer=example.com
```

If a user loses access to their authenticator app, backup recovery codes or the optional fallback email code feature can be used when configured.

### Emergency 2FA bypass

If 2FA causes a lockout during testing or deployment, add this constant to `wp-config.php`:

```php
define('SO_SSL_DISABLE_2FA', true);
```

Remove the constant after recovering access.

## Security Score Notes

Some settings contribute to the plugin's Security Score. Settings that count toward the score are marked in the settings page info icons.

The search-engine indexing blocker does not count toward the Security Score because it is a privacy/content visibility control, not a direct security hardening feature.

## Uninstall Behavior

So SSL preserves settings during plugin updates and deactivation.

When the plugin is deleted from the WordPress Plugins screen, `uninstall.php` removes So SSL plugin data, including:

- Plugin options.
- 2FA logs.
- Login lockout transients.
- Temporary 2FA transients.
- So SSL user metadata.

## Plugin Assets

The plugin includes WordPress.org-style assets:

- `assets/banner-772x250.png`
- `assets/icon.svg`
- `assets/icon-256x256.png`
- `assets/icon-128x128.png`
- `assets/screenshot-1.png`
- `assets/screenshot-2.png`
- `assets/screenshot-3.png`
- `assets/screenshot-4.png`

`assets/banner.svg` is included as a source/design copy. The fixed-size PNG banner is the primary WordPress.org directory banner asset.

## Development

Recommended local checks before release:

```bash
php -l so-ssl.php
php -l includes/class-so-ssl.php
php -l includes/class-so-ssl-totp.php
php -l uninstall.php
```

Recommended WordPress checks:

- Test activation and deactivation.
- Test uninstall cleanup only by deleting the plugin, not by deactivating it.
- Run the official WordPress Plugin Check plugin.
- Test forced HTTPS only on a site with a working SSL certificate.
- Test 2FA on a secondary administrator account before requiring it for the original administrator account.

## Changelog

### 1.85

- Removed the unregistered `user-settings` admin script dependency that caused a WordPress debug notice.
- Kept settings tab memory through the server-side tab parameter and browser fallback.

### 1.84

- Fixed a fatal settings page error from an incomplete `wp_kses()` call.

### 1.81

- Added targeted Plugin Check inline documentation for nonce-verified profile and 2FA login form handling.
- Clarified that profile update fields are processed only after WordPress profile nonces are verified.
- Preserved existing 2FA, backup code, and settings behavior while resolving remaining Plugin Check reports.

### 1.80

- Corrected WordPress Plugin Check findings for public release readiness.
- Added nonce checks, safer superglobal handling, and escaped admin output.
- Removed runtime `ini_set()` usage while keeping `X-Powered-By` header cleanup.
- Updated compatibility metadata for Plugin Check expectations.

### 1.79

- Replaced the WordPress.org banner and icon assets with the latest supplied files.
- Added `assets/banner.svg` as a source/design asset while keeping `assets/banner-772x250.png` as the WordPress.org display banner.
- Added square PNG icon fallbacks for the SVG icon.

### 1.78

- Added plugin screenshots to the assets directory using WordPress.org screenshot naming conventions.
- Updated the readme screenshots section so each ordered description corresponds to the matching screenshot file.

### Earlier versions

Earlier versions added Settings API tabs, 2FA stages, backup codes, fallback email codes, profile overrides, admin notices, search-engine indexing controls, license files, screenshots, and public-release cleanup.

## License

This plugin is licensed under the GNU General Public License version 3 or later.

See [`LICENSE.txt`](LICENSE.txt) for the full license text.

## Author

Created by **Will Radford**.

- Website: <https://willradford.com>
- Plugin page: <https://willradford.com/so-ssl/>
