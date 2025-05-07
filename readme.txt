=== So SSL ===
Contributors: radfordwill
Tags: ssl, https, security, headers, privacy, gdpr, compliance, two-factor, 2fa
Requires at least: 5.0
Tested up to: 6.3
Stable tag: 1.4.5
Requires PHP: 7.0
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html

A comprehensive security and privacy plugin to enforce SSL/HTTPS, implement advanced security headers, and ensure privacy compliance.

== Description ==

So SSL is a comprehensive security and privacy plugin for WordPress that allows you to easily enforce SSL/HTTPS on your website, implement advanced security headers, enable two-factor authentication, and ensure privacy compliance with GDPR and US regulations.

= Key Features =

**SSL Enforcement**

* Force all traffic to use HTTPS/SSL
* Automatically redirect visitors from HTTP to HTTPS
* Compatible with all major WordPress themes and plugins

**Security Headers**

* HTTP Strict Transport Security (HSTS)
* Content Security Policy (CSP)
* X-Frame-Options protection against clickjacking
* Referrer Policy controls
* Permissions Policy for controlling browser feature access
* Cross-Origin protection with various policies

**Privacy Compliance**

* GDPR and US privacy regulations compliance
* Customizable privacy acknowledgment page for logged-in users
* Role-based privacy requirements configuration
* Expiry settings for periodic privacy policy re-acknowledgment
* Full preview of privacy page in admin interface

**Two-Factor Authentication**

* Email verification code option
* Google Authenticator app integration
* Role-based 2FA requirements
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

When enabled, users will be redirected to a customizable privacy acknowledgment page after login. They must check the acknowledgment box to access the site. The acknowledgment is stored in user metadata with a timestamp, and you can set an expiry period after which users must re-acknowledge the notice.

= Is Two-Factor Authentication secure? =

Yes, So SSL implements industry-standard TOTP (Time-based One-Time Password) for the authenticator app option and secure email verification. Both methods significantly increase the security of your WordPress login process.

= Can I customize security headers for my specific site needs? =

Yes, all security headers can be customized with various options. The Content Security Policy (CSP) settings are particularly flexible, allowing you to control exactly which sources are allowed for different content types.

== Screenshots ==

1. Main plugin settings page with security score
2. SSL configuration options
3. Security headers settings
4. Two-Factor Authentication settings
5. Login Protection configuration
6. Privacy Compliance settings with preview
7. User Sessions Management

== Changelog ==

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

= 1.4.5 =
This update adds privacy compliance features for GDPR and US privacy regulations, with a customizable acknowledgment page for users.

= 1.4.4 =
This update improves CSP header handling and fixes a compatibility issue with WooCommerce checkout.

= 1.4.0 =
Major update with Content Security Policy and Permissions Policy settings.

== Credits ==

* This plugin uses the TOTP library for two-factor authentication
* Icons by Dashicons