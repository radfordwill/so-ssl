=== So SSL ===
Contributors: willradford
Donate link: https://buy.stripe.com/6oU3co1aodHc5RbgW62go00
Tags: ssl, security, privacy policy, compliance, 2fa
Requires at least: 6.5
Tested up to: 7.0
Stable tag: 1.82
Requires PHP: 7.0
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.txt

A comprehensive security and privacy plugin to enforce SSL/HTTPS, implement advanced security headers, and support privacy acknowledgment workflows.

== Description ==

So SSL is a comprehensive security and privacy plugin for WordPress that allows you to enforce SSL/HTTPS, implement advanced security headers, enable two-factor authentication, and support privacy acknowledgment workflows. It provides tools that may help site owners with privacy notices, but it is not legal advice and does not guarantee compliance with any law or regulation.

= Key Features =

**SSL Enforcement**

* Force all traffic to use HTTPS/SSL
* Automatically redirect visitors from HTTP to HTTPS
* Compatible with all major WordPress themes and plugins

**Search Engine Indexing Protection**

* Optional setting to block web search engines from indexing the site
* Adds robots meta tag protection to public pages
* Adds X-Robots-Tag header protection
* Adds a robots.txt Disallow rule when enabled

**Security Headers**

* HTTP Strict Transport Security (HSTS)
* Content Security Policy (CSP)
* X-Frame-Options protection against clickjacking
* Referrer Policy controls
* Permissions Policy for controlling browser feature access
* Cross-Origin protection with various policies

**Privacy Acknowledgment Tools**

* Customizable privacy acknowledgment workflows for logged-in users
* Customizable privacy acknowledgment page for logged-in users
* Role-based privacy requirements configuration
* Expiry settings for periodic privacy policy re-acknowledgment
* Full preview of privacy page in admin interface
* Modal-based acknowledgment system for better user experience

**Administrator Agreement**

* Require administrators to accept terms before using plugin features
* Customizable agreement text and checkbox labels
* Role-based requirements with exemption options
* Periodic re-acknowledgment with configurable expiry
* Emergency override option for lockout prevention

**Two-Factor Authentication**

* Email verification code option
* Google Authenticator app integration
* Role-based 2FA requirements
* Mandatory authenticator app setup redirect for required users
* Local QR code display for authenticator app setup when supported
* Authenticator app link dropdown for common apps
* Backup codes for emergency access

**Login Protection**

* Strong password enforcement
* Validation and strength checking
* Prevention of weak password usage

**User Session Management**

* View and manage active user sessions
* Terminate sessions on specific devices
* Limit maximum number of concurrent sessions
* Set maximum session duration

**Login Limiting**

* Protection against brute force attacks
* Customizable lockout settings
* IP whitelist and blacklist management
* Email notifications for lockouts

== Installation ==

1. Upload the 'so-ssl' folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure the plugin settings in the 'Settings > So SSL' menu

== Frequently Asked Questions ==

= Will this plugin work if I don't have an SSL certificate? =

While the plugin will activate, forcing SSL without a valid SSL certificate will make your site inaccessible. You need to install an SSL certificate on your web server before enabling the force SSL option.

= How does the privacy compliance feature work? =

When enabled, users will see a modal overlay with your privacy notice after login. They must check the acknowledgment box to access the site. The acknowledgment is stored in user metadata with a timestamp, and you can set an expiry period after which users must re-acknowledge the notice.

= What is the Administrator Agreement feature? =

The Administrator Agreement ensures that administrators acknowledge the security implications and responsibilities of using the plugin. It's displayed as a modal overlay when administrators first access the plugin settings and can be configured to require periodic re-acknowledgment.

= Is Two-Factor Authentication secure? =

Yes, So SSL implements industry-standard TOTP (Time-based One-Time Password) for the authenticator app option and secure email verification. Both methods significantly increase the security of your WordPress login process.

= Can I customize security headers for my specific site needs? =

Yes, all security headers can be customized with various options. The Content Security Policy (CSP) settings are particularly flexible, allowing you to control exactly which sources are allowed for different content types.

== Plugin Assets ==

* Includes WordPress.org-style banner asset: `assets/banner-772x250.png`
* Includes WordPress.org-style banner and icon assets: `assets/banner-772x250.png`, `assets/icon.svg`, and PNG icon fallbacks.
* Includes `assets/banner.svg` as a source/design copy; WordPress.org uses the fixed-size PNG banner for directory display.

== Uninstall ==

When the plugin is deleted from the WordPress Plugins screen, So SSL removes its plugin options, 2FA logs, login lockout transients, temporary 2FA transients, and So SSL user metadata. Deactivating or updating the plugin does not delete settings.

== Screenshots ==

1. Main So SSL settings page with admin notice, banner, security score, and tabbed settings. Corresponds to `assets/screenshot-1.png`.
2. Two-factor authentication login screen with authenticator app and fallback email code options. Corresponds to `assets/screenshot-2.png`.
3. Privacy settings tab within the So SSL settings interface. Corresponds to `assets/screenshot-3.png`.
4. User profile authenticator app 2FA setup area with status, manual setup key, and QR code. Corresponds to `assets/screenshot-4.png`.

== License ==

This plugin is licensed under GPLv3 or later. See `LICENSE.txt` for the full GNU General Public License version 3 text.

== Changelog ==

= 1.82 =
* Updated the plugin header Plugin URI to use a plugin-specific page distinct from the Author URI for WordPress.org Plugin Check compliance.

= 1.81 =
* Added targeted Plugin Check inline documentation for nonce-verified profile and 2FA login form handling.
* Clarified that profile update fields are processed only after WordPress profile nonces are verified.
* Preserved existing 2FA, backup code, and settings behavior while resolving remaining Plugin Check reports.

= 1.80 =
* Corrected WordPress Plugin Check findings for public release readiness.
* Added nonce checks, safer superglobal handling, and escaped admin output.
* Removed runtime ini_set usage while keeping X-Powered-By header cleanup.
* Updated Tested up to value to match current Plugin Check expectations.

= 1.79 =
* Replaced the WordPress.org banner and icon assets with the latest supplied files.
* Added `assets/banner.svg` as a source/design asset while keeping `assets/banner-772x250.png` as the WordPress.org display banner.
* Added square PNG icon fallbacks for the SVG icon.

= 1.78 =
* Added plugin screenshots to the assets directory using WordPress.org screenshot naming conventions.
* Updated the readme screenshots section so each ordered description corresponds to the matching screenshot file.

= 1.77 =
* Public release cleanup with uninstall cleanup and clearer Security Score descriptions.

= 1.76 =
* Reorganized the settings page tabs through WordPress Settings API sections and fields.
* Kept the first tab as the default tab.
* Preserved current-tab memory using WordPress user settings with browser fallback.

= 1.75 =
* Added an optional email action for newly generated 2FA backup recovery codes.
* The email option appears only immediately after backup codes are generated during new setup or manual backup-code regeneration.
* Backup codes are sent to the account registered email address and the email option expires shortly after generation.

= 1.74 =
* Organized the main So SSL settings screen into WordPress-style admin tabs by section.
* Added tab memory using WordPress user settings so the last selected settings tab reopens for the current admin user.
* Kept the first tab as the default for new users or when no previous tab is stored.

= 1.73 =
* Moved the Enable role-based 2FA checks option directly above Roles requiring 2FA.
* Made Enable role-based 2FA checks bold and changed Roles requiring 2FA to a normal legend in the 2FA settings flow.

= 1.71 =
* Added a master toggle to enable or disable all So SSL two-factor authentication features.
* Disabled the 2FA settings controls when the master 2FA system is turned off while preserving saved values.
* Added per-user profile 2FA overrides so an administrator can force-enable authenticator app 2FA or force-disable 2FA for a user regardless of role-based defaults.
* Preserved legacy profile-enabled 2FA users until an explicit profile override is chosen.

= 1.70 =
* Limited the second administrator recommendation notice to the original administrator account only.
* Made the original administrator recommendation notice dismissible without permanently hiding it.

= 1.69 =
* Hid the So SSL Authenticator App 2FA profile section from non-admin users when 2FA is not enabled and setup is not required for that account.
* Kept mandatory setup visible for users whose role requires authenticator app 2FA.

= 1.68 =
* Backup recovery codes are now part of the user setup flow.
* Automatically generates and shows one-time backup codes after a user completes mandatory authenticator app setup.
* Added a user profile option for enabled non-admin users to regenerate their own backup recovery codes without exposing setup management controls.

= 1.67 =
* Improved fallback email 2FA login instructions so the page clearly shows that authenticator app codes still work after a fallback code is requested.
* Updated the verification field label to accept authenticator app, backup, or fallback email codes.

= 1.66 =
* Fixed authenticator app email fallback button so the code is only stored after WordPress confirms the fallback email was sent.
* Updated the 2FA login page to switch the prompt and field label to fallback email code after a fallback code is requested.
* Added failure messaging and 2FA log entries when WordPress cannot send the fallback email.

= 1.65 =
* Fixed required role-based authenticator setup so non-admin users can complete mandatory setup without seeing administrator-only setup management controls.
* Updated the profile status message to show when 2FA is required but setup is incomplete.
* Kept Verify setup, Reset setup key, backup code generation, and recovery management restricted to administrator-level users.

= 1.64 =
* Hid the non-admin Setup management profile row so non-administrators no longer see that section.
* Completed staged 2FA improvements with role enforcement controls, administrator recovery tools, backup recovery codes, optional email fallback for authenticator app logins, and recent 2FA attempt logging.
* Added So SSL 2FA Recovery and 2FA Logs submenu pages.

= 1.63 =
* Restricted authenticator app Verify setup and Reset setup key controls to administrators only.
* Added server-side enforcement so non-administrators cannot submit 2FA setup or reset changes through profile form posts.

= 1.62 =
* Added spam/junk folder reminder to the emailed two-factor code login instructions.

= 1.61 =
* Updated authenticator app QR and manual URI generation to use the website domain as the issuer when available
* Improved 2FA setup descriptions so users know the authenticator app entry should identify the site when supported

= 1.60 =
* Added method-specific guidance to the two-factor login code screen for email and authenticator app verification.
* Updated the login code label to match the active two-factor method.

= 1.59 =
* Added local QR code display to the user authenticator app setup area
* Added an authenticator app links dropdown for common TOTP-compatible apps
* Added an admin settings notice encouraging a second administrator account for safer recovery practices
* Added bundled local QR code generation asset for setup without sending the TOTP secret to a third-party QR service


= 1.58 =
* Added mandatory authenticator app setup redirect for role-required users who have not completed TOTP setup.
* Updated profile setup messaging so users know how to complete required 2FA enrollment.
* Preserved emergency bypass support with SO_SSL_DISABLE_2FA.

= 1.57 =
* Stage 1 authenticator app 2FA improvements
* Added verified per-user authenticator app setup before enabling 2FA
* Added setup key reset option for lost or exposed authenticator app secrets
* Hardened 2FA login flow so the second-factor screen requires a valid password-authenticated session
* Added emergency bypass support with SO_SSL_DISABLE_2FA for lockout recovery

= 1.56 =
* Added the So SSL banner image to the top of the plugin settings page for improved admin page presentation.

= 1.55 =
* Rebuilt from version 1.53 and added only PHP version header disclosure reduction.
* Removes the X-Powered-By header when WordPress/PHP can control response headers.
* Attempts to set expose_php off at runtime without changing CSP behavior.

= 1.53 =
* Added info icons to the Manage User Sessions button and User Sessions page controls.
* Added WordPress.org-style banner and SVG icon assets to the plugin package.
* Added LICENSE.txt with the GNU General Public License version 3 text.
* Added explicit GPLv3-or-later licensing entries to the main PHP file and readme.

= 1.52 =
* Added reusable info icons beside plugin setting labels
* Added concise feature descriptions for current settings form options
* Prepared the settings field helper system so future features can include the same info icon pattern

= 1.51 =
* Added a CSP worker-src fallback allowing self and blob workers for WordPress core scripts.
* Updated the default CSP policy to prevent wp-emoji-loader blob worker blocking errors.

= 1.50 =
* Added an automatic CSP font-src fallback for web fonts, including data: and HTTPS font sources.
* Updated the default CSP policy to prevent base64 WOFF/WOFF2 font loading errors.

= 1.49 =
* Moved So SSL from the Settings menu into its own top-level WordPress admin menu.
* Added Sessions as a submenu under the new So SSL admin menu.
* Removed the web search engine indexing blocker from the security score calculation.

= 1.48 =
* Added option to block web search engines from indexing the site.
* Added robots meta tag, X-Robots-Tag header, and robots.txt Disallow protection when indexing block is enabled.
* Updated Requires at least to 6.5 and Tested up to to 7.4.
* Added contributors metadata to the main PHP plugin file.

= 1.47 =
* Fixed sessions admin page fatal error caused by invalid abs_attr() call.

= 1.46 =
* Rebuilt duplicate plugin package starting at version 1.46
* Enhanced privacy compliance modal system for better cross-environment compatibility
* Improved Administrator Agreement feature with modal-based acknowledgment
* Added modal controller for managing multiple overlay priorities
* Fixed redirect loop issues in privacy compliance on production domains
* Added AJAX fallback methods for better reliability on various hosting environments
* Improved error handling and debugging capabilities for modal displays

= 1.4.5 =
* Added privacy compliance feature for GDPR and US privacy regulations
* Implemented customizable privacy acknowledgment page
* Added role-based privacy requirements configuration
* Added privacy page preview in admin interface
* Added link to view the actual privacy page

= 1.4.4 =
* Fixed compatibility issue with WooCommerce checkout
* Improved CSP header handling for common third-party scripts
* Added support for custom domains in frame-ancestors directive

= 1.4.3 =
* Added Cross-Origin Policy headers
* Improved security header documentation
* Fixed minor CSS issues in admin interface

= 1.4.2 =
* Added login limiting feature
* Implementation of IP whitelisting and blacklisting
* Added lockout settings for brute force protection

= 1.4.1 =
* Added Two-Factor Authentication
* Support for email verification and authenticator apps
* Implementation of backup codes for emergency access

= 1.4.0 =
* Added Content Security Policy (CSP) controls
* Implemented Permissions Policy settings
* Advanced Referrer Policy options

= 1.3.0 =
* Added strong password enforcement
* User session management functionality
* UI improvements for settings page

= 1.2.0 =
* Added HSTS preload list support
* Improved X-Frame-Options controls
* Admin UI enhancements

= 1.1.0 =
* Added HSTS (HTTP Strict Transport Security) support
* Added X-Frame-Options header support
* Improved admin interface with security scoring

= 1.0.0 =
* Initial release
* Basic SSL forcing functionality

== Upgrade Notice ==

= 1.82 =
Plugin header URI cleanup for WordPress.org Plugin Check compliance.

= 1.81 =
Plugin Check cleanup for nonce-verified profile and 2FA login form handling.

= 1.80 =
Plugin Check cleanup for public release readiness, including nonce handling, output escaping, and safer server/header handling.

= 1.79 =
Updates the plugin assets folder with the latest WordPress.org banner and icon files.

= 1.78 =
Adds public plugin screenshots in the assets directory and updates the readme screenshot descriptions to match their order.

= 1.77 =
* Public release cleanup with uninstall cleanup and clearer Security Score descriptions.

= 1.76 =
This update rebuilds the So SSL settings tabs through the WordPress Settings API while preserving the current tab memory behavior.

= 1.75 =
This update lets users email newly generated 2FA backup recovery codes to their registered account email immediately after generation.

= 1.74 =
This update shortens the main settings screen by grouping each So SSL section into WordPress-style tabs and remembers the current administrator’s last-used tab.

= 1.73 =
This update clarifies the role-based 2FA settings layout by placing the enabling checkbox directly above the affected roles and emphasizing the checkbox label.

= 1.71 =
This update adds a master 2FA toggle and per-user profile overrides for authenticator app 2FA, so profile settings can enable or disable 2FA independently of role-based defaults.

= 1.70 =
This update shows the second-administrator recommendation only to the original administrator account and makes the notice dismissible for the current page/session.

= 1.69 =
This update hides the profile 2FA section for non-admin users unless 2FA is enabled or required for setup.

= 1.68 =
Backup recovery codes now appear immediately after required authenticator setup and can be regenerated by the logged-in user from their own profile.

= 1.66 =
Fixes the email fallback code flow for authenticator app logins and improves the login instructions after requesting a fallback code.

= 1.65 =
This update fixes mandatory role-based authenticator setup for users who were required to use 2FA but could not enable it from their profile after administrator-only setup management was added.

= 1.64 =
Adds staged 2FA recovery, fallback, and logging improvements while hiding non-admin setup management messaging from user profiles.

= 1.63 =
Restricts authenticator setup verification and reset controls to administrator-level users.

= 1.62 =
Adds a clearer reminder for emailed verification code users to check spam or junk folders if the login code does not appear.

= 1.61 =
Updates authenticator app setup so the QR/manual URI includes the website domain when supported by the authenticator app.

= 1.60 =
Adds clearer instructions to the two-factor code entry screen for email and authenticator app login flows.

= 1.59 =
Adds QR-based authenticator app setup, app link dropdowns, and an administrator recovery best-practice notice.


= 1.58 =
Users in roles requiring authenticator app 2FA will be redirected to their profile setup screen until setup is completed.

= 1.57 =
Stage 1 improves authenticator app 2FA setup and hardens the second-factor login screen.

= 1.55 =
Reverts to the stable 1.53 feature set and only adds PHP version header disclosure reduction.

= 1.53 =
This update improves admin help text around user sessions, adds plugin banner/icon assets, and includes GPLv3 licensing files and metadata.

= 1.52 =
Adds info icons with concise descriptions beside So SSL settings fields.

= 1.51 =
Fixes CSP worker blocking errors caused by script-src fallback blocking WordPress blob workers.

= 1.50 =
Fixes CSP font loading errors caused by default-src fallback blocking embedded/base64 web fonts.

= 1.49 =
Moves So SSL to a top-level admin menu, nests Sessions under it, and keeps the search indexing blocker outside the security score.

= 1.48 =
Adds search engine indexing protection and updates WordPress compatibility metadata.

= 1.47 =
Fixes the sessions page fatal error in the WordPress admin.

= 1.46 =
This update improves privacy compliance and administrator agreement features with better modal handling, fixes redirect loop issues on production domains, and adds fallback methods for improved reliability across different hosting environments.

= 1.4.5 =
This update adds privacy compliance features for GDPR and US privacy regulations, with a customizable acknowledgment page for users.

= 1.4.4 =
This update improves CSP header handling and fixes a compatibility issue with WooCommerce checkout.

= 1.4.0 =
Major update with Content Security Policy and Permissions Policy settings.

== Credits ==

* This plugin uses the TOTP library for two-factor authentication
* Icons by Dashicons
* This plugin uses the QRCodejs. QRCode.js is a JavaScript library used for generating QR codes