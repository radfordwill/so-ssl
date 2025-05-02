# So SSL

A WordPress plugin to activate and enforce SSL on your site with additional security headers, two-factor authentication, and login protection.

## Description

So SSL provides a comprehensive set of tools to enhance the security of your WordPress website through SSL/HTTPS enforcement, various security headers, two-factor authentication, and login protection features. The plugin now features an improved user interface design for better usability and a more intuitive experience.

## Features

* **Improved UI Design**: Enhanced user interface for better usability and navigation
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
* **Login Protection**:
  * Enforce strong passwords for all users
  * Disable "confirm use of weak password" checkbox
  * Prevent users from configuring weak passwords during registration or password change
* **User Sessions Management**:
  * View and manage all active user sessions
  * Terminate individual or all sessions
  * Set maximum sessions per user
  * Limit session duration
  * Manage sessions across multiple devices
* **Login Attempt Limiting**:
  * Protect against brute force attacks
  * Customize maximum login attempts
  * Configurable lockout duration
  * IP whitelist and blacklist
  * Detailed login statistics
  * Email notifications for lockouts

## Installation

1. Upload the `so-ssl` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure the plugin settings under 'Settings > So SSL'

## Usage

### SSL Settings

Configure SSL enforcement and HSTS settings to ensure your site is always accessed securely.

### Security Headers

Customize various security headers to protect your site from common web vulnerabilities:

* **Content Security Policy**: Control which resources can be loaded on your site
* **X-Frame-Options**: Prevent your site from being embedded in iframes on other sites
* **Referrer Policy**: Control how much referrer information is shared
* **Permissions Policy**: Limit access to browser features and APIs
* **Cross-Origin Policies**: Control cross-origin resource sharing

### Two-Factor Authentication

Add an extra layer of security to your WordPress login process:

1. **Enable 2FA**: Go to the "Two-Factor Auth" tab and enable the feature
2. **Select User Roles**: Choose which user roles require 2FA
3. **Choose Authentication Method**: Select either Email or Authenticator App
4. **User Setup**: Users can configure 2FA in their profile settings
5. **Backup Codes**: Generate and store backup codes for emergency access

### Login Protection

Strengthen your site's login security by enforcing strong passwords:

1. **Enable Strong Passwords**: Go to the "Login Protection" tab and enable the feature
2. **Automatic Enforcement**: The plugin will automatically:
  * Hide the "confirm use of weak password" checkbox
  * Prevent users from setting weak passwords during registration
  * Enforce strong passwords during password changes
  * Display helpful error messages guiding users to create stronger passwords

### User Sessions Management

Monitor and control active user sessions across devices:

1. **Enable Session Management**: Go to the "User Sessions" tab and enable the feature
2. **View Active Sessions**: See all active sessions for each user
3. **Session Details**: View login time, IP address, browser, expiration time
4. **Terminate Sessions**: End individual sessions or all sessions for a user
5. **Session Limits**: Set maximum number of concurrent sessions per user
6. **Duration Limits**: Set maximum session lifetime

### Login Attempt Limiting

Protect against brute force attacks by limiting login attempts:

1. **Enable Login Limiting**: Go to the "Login Limiting" tab and enable the feature
2. **Configure Settings**: Set maximum attempts, lockout duration, and other parameters
3. **IP Management**: Whitelist and blacklist IPs as needed
4. **View Statistics**: Monitor login attempts and lockouts
5. **Notifications**: Receive email alerts for suspicious activity

## Frequently Asked Questions

### Is this plugin compatible with multisite installations?

Yes, So SSL works with WordPress multisite installations.

### Will enabling SSL break my site?

The plugin includes warnings and recommendations to ensure you don't enable SSL without having a valid SSL certificate installed. Always make sure you have a valid SSL certificate before enforcing SSL.

### How do I troubleshoot login issues with Two-Factor Authentication?

If you're unable to log in, you can use your backup codes for emergency access. If you've lost your backup codes, you may need to temporarily disable 2FA by editing your database or contacting your site administrator.

### What makes a password "strong" according to the plugin?

The plugin considers a password strong when it:
* Is at least 8 characters long
* Does not contain the username
* Includes a mix of uppercase letters, lowercase letters, numbers, and special characters

### Will enforcing strong passwords affect existing user accounts?

Enforcing strong passwords only affects new password creations and changes. Existing users won't be forced to change their passwords immediately, but will need to create a strong password when they next update it.

## Changelog

### 1.4.4
* Added improved user interface design for better usability
* Enhanced navigation between settings pages
* Improved visual styling of settings tabs and options
* Better responsive design for various screen sizes

### 1.4.3
* Fix all errors found and most warnings generated by the WordPress Plugin Check plugin
* Submit to WordPress.org for approval and addition to the plugins directory

### 1.4.2
* Menu fixes
* Add translation file and add image files for plugin

### 1.4.1
* Fixes for missing js and css files

### 1.4.0
* Added User Sessions Management feature
* Added Login Attempt Limiting feature
* Added IP whitelisting and blacklisting
* Added detailed statistics for login attempts

### 1.3.1
* Minor fixes
* Better login Protection

### 1.3.0
* Added Login Protection feature to enforce strong passwords
* Added option to disable the "confirm use of weak password" checkbox
* Added prevention of weak password creation during registration or password change

### 1.2.0
* Added Two-Factor Authentication for enhanced login security
* Added support for Email and Authenticator App verification methods
* Added backup codes for account recovery
* Added role-based 2FA implementation

### 1.1.0
* Added Content Security Policy support
* Added Permissions Policy support
* Added Cross-Origin Policy headers
* Improved settings page with tabbed interface

### 1.0.2
* Added X-Frame-Options header
* Added HSTS support
* Improved SSL redirection

### 1.0.1
* Bug fixes and performance improvements

### 1.0.0
* Initial release with basic SSL enforcement

## License

This plugin is licensed under the GPL v3 or later.