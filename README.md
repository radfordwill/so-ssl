
=== So SSL ===

Contributors: willrad


Tags: ssl, security, https, hsts, frame-options


Requires at least: 5.0


Tested up to: 6.5


Stable tag: 1.1.0


License: GPLv3 or later


License URI: http://www.gnu.org/licenses/gpl-3.0.html




A plugin to activate and enforce SSL on your WordPress site with additional security headers.

== Description ==
So SSL helps you secure your WordPress site by enforcing HTTPS connections and adding important security headers.

Features include:
* Force SSL redirection
* HTTP Strict Transport Security (HSTS) implementation
* X-Frame-Options header to prevent clickjacking
* Content Security Policy (CSP): frame-ancestors
* Full Content Security Policy implementation with report-only mode
* Referrer Policy control
* Permissions Policy to restrict browser features
* Cross-Origin policies (Embedder, Opener, and Resource)

== Admin Interface ==
So SSL provides a user-friendly admin interface with intuitive organization:

* **SSL Settings** - Basic SSL and HSTS settings
* **Content Security** - CSP and Referrer Policy settings
* **Browser Features** - Permissions Policy settings
* **Cross-Origin** - X-Frame-Options, CSP frame-ancestors, and Cross-Origin policies

== Installation ==
1. Upload the plugin files to the `/wp-content/plugins/so-ssl` directory, or install the plugin through the WordPress plugins screen.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Use the Settings->So SSL screen to configure the plugin.

== Changelog ==
= 1.1.0 =
* Improved admin interface with tabbed navigation
* Organized security headers into logical groups:
  * SSL Settings - Basic SSL and HSTS settings
  * Content Security - CSP and Referrer Policy settings
  * Browser Features - Permissions Policy settings
  * Cross-Origin - X-Frame-Options, CSP frame-ancestors, and Cross-Origin policies
* Enhanced JavaScript interactivity for settings page
* Added responsive design to admin interface

= 1.0.2 =
* Code restructuring for improved maintainability
* Proper implementation of OOP principles
* Fixed version number discrepancies
* Added comprehensive security headers:
  * Full Content Security Policy (CSP) implementation
  * Referrer Policy
  * Permissions Policy (Feature Policy)
  * Cross-Origin Policy controls (COEP, COOP, CORP)

= 1.0.1 =
* Initial release and small updates
