=== So SSL ===
Contributors: radfordwill
Tags: ssl, security, headers, https, two-factor, 2fa, authentication
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 1.2.1
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Activate and enforce SSL on your WordPress site with additional security headers and two-factor authentication.

== Description ==

So SSL provides a comprehensive set of tools to enhance the security of your WordPress website through SSL/HTTPS enforcement, various security headers, and two-factor authentication.

= Features =

* **SSL Enforcement**: Automatically redirect all traffic to HTTPS
* **HTTP Strict Transport Security (HSTS)**: Instruct browsers to only access your site over HTTPS
* **Security Headers**:
  * X-Frame-Options: Protect against clickjacking attacks
  * Content Security Policy: Control which resources can be loaded
  * Referrer Policy: Control how much referrer information is shared
  * Permissions Policy: Limit browser features and APIs
  * Cross-Origin Policies: Control how your site interacts with other sites
* **Two-Factor Authentication**:
  * Enhance login security with 2FA
  * Multiple authentication methods (Email, Authenticator App)
  * Role-based implementation
  * Backup codes for account recovery

== Installation ==

1. Upload the `so-ssl` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure the plugin settings under 'Settings > So SSL'

== Usage ==

= SSL Settings =

Configure SSL enforcement and HSTS settings to ensure your site is always accessed securely.

= Security Headers =

Customize various security headers to protect your site from common web vulnerabilities:

* **Content Security Policy**: Control which resources can be loaded on your site
* **X-Frame-Options**: Prevent your site from being embedded in iframes on other sites
* **Referrer Policy**: Control how much referrer information is shared
* **Permissions Policy**: Limit access to browser features and APIs
* **Cross-Origin Policies**: Control cross-origin resource sharing

= Two-Factor Authentication =

Add an extra layer of security to your WordPress login process:

1. **Enable 2FA**: Go to the "Two-Factor Auth" tab and enable the feature
2. **Select User Roles**: Choose which user roles require 2FA
3. **Choose Authentication Method**: Select either Email or Authenticator App
4. **User Setup**: Users can configure 2FA in their profile settings
5. **Backup Codes**: Generate and store backup codes for emergency access

== Frequently Asked Questions ==

= Is this plugin compatible with multisite installations? =

Yes, So SSL works with WordPress multisite installations.

= Will enabling SSL break my site? =

The plugin includes warnings and recommendations to ensure you don't enable SSL without having a valid SSL certificate installed. Always make sure you have a valid SSL certificate before enforcing SSL.

= How do I troubleshoot login issues with Two-Factor Authentication? =

If you're unable to log in, you can use your backup codes for emergency access. If you've lost your backup codes, you may need to temporarily disable 2FA by editing your database or contacting your site administrator.

= Which authenticator apps are supported? =

The two-factor authentication feature works with any TOTP-compatible authenticator app, including Google Authenticator, Authy, Microsoft Authenticator, and others.

= Can I make 2FA mandatory for certain user roles? =

Yes, you can select which user roles are required to use two-factor authentication from the plugin settings.

== Screenshots ==

1. SSL Settings
2. Security Headers Configuration
3. Two-Factor Authentication Settings
4. User Profile 2FA Setup
5. Login Screen with 2FA Prompt

== Changelog ==

= 1.2.1 =
* Added Two-Factor Authentication for enhanced login security
* Added support for Email and Authenticator App verification methods
* Added backup codes for account recovery
* Added role-based 2FA implementation

= 1.2.0 =
* Bugfix

= 1.1.0 =
* Added Content Security Policy support
* Added Permissions Policy support
* Added Cross-Origin Policy headers
* Improved settings page with tabbed interface

= 1.0.2 =
* Added X-Frame-Options header
* Added HSTS support
* Improved SSL redirection

= 1.0.1 =
* Bug fixes and performance improvements

= 1.0.0 =
* Initial release with basic SSL enforcement

== Upgrade Notice ==

= 1.2.0 =
This version adds Two-Factor Authentication to enhance login security. After upgrading, visit the new "Two-Factor Auth" tab in the plugin settings to configure this feature.

= 1.1.0 =
This version adds new security headers and an improved settings interface.

= 1.0.2 =
This version adds X-Frame-Options and HSTS support for improved security.
