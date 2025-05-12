<?php
/**
 * Two-Factor Authentication Implementation for So SSL Plugin
 *
 * This file implements the two-factor authentication functionality for the So SSL plugin.
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
	die;
}

/**
 * Class to handle Two-Factor Authentication
 */
class So_SSL_Two_Factor {

    /**
     * Initialize the Two-Factor Authentication functionality
     */
    public static function init() {
        // Only proceed if 2FA is enabled
        if (!get_option('so_ssl_enable_2fa', 0)) {
            return;
        }

        // Add user profile fields for 2FA setup
        add_action('show_user_profile', array(__CLASS__, 'add_2fa_user_fields'));
        add_action('edit_user_profile', array(__CLASS__, 'add_2fa_user_fields'));

        // Save user profile fields
        add_action('personal_options_update', array(__CLASS__, 'save_2fa_user_fields'));
        add_action('edit_user_profile_update', array(__CLASS__, 'save_2fa_user_fields'));

        // Hook into the authentication process
        add_filter('authenticate', array(__CLASS__, 'authenticate_2fa'), 999, 3);

        // Add login form fields for 2FA
        add_action('login_form', array(__CLASS__, 'add_2fa_login_fields'));

        // Custom login messages
        add_filter('login_message', array(__CLASS__, 'login_message'));

        // Handle 2FA verification during login
        add_action('wp_login', array(__CLASS__, 'verify_2fa'), 10, 2);

        // Enqueue scripts and styles
        add_action('login_enqueue_scripts', array(__CLASS__, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_scripts'));
    }

    /**
     * Check if 2FA is required for a user
     *
     * @param WP_User $user The user object
     * @return bool Whether 2FA is required
     */
    public static function is_2fa_required_for_user($user) {
        if (!$user || !is_object($user) || !($user instanceof WP_User)) {
            return false;
        }

        // Get selected roles for 2FA
        $required_roles = get_option('so_ssl_2fa_user_roles', array('administrator'));

        // If not an array, convert it
        if (!is_array($required_roles)) {
            $required_roles = array('administrator');
        }

        // Check if user has any of the required roles
        $user_roles = $user->roles;

        foreach ($user_roles as $role) {
            if (in_array($role, $required_roles)) {
                return true;
            }
        }

        return false;
    }

	/**
	 * Add 2FA fields to user profile
	 *
	 * @param WP_User $user The user object
	 */
	public static function add_2fa_user_fields($user) {
		// Only show if 2FA is required for this user
		if (!self::is_2fa_required_for_user($user)) {
			return;
		}

        /// Get current 2FA status for user
		$enabled = get_user_meta($user->ID, 'so_ssl_2fa_enabled', true);
		$method = get_option('so_ssl_2fa_method', 'email');

		?>
        <h2><?php esc_html_e('Two-Factor Authentication', 'so-ssl'); ?></h2>

        <!-- User Instructions Box -->
        <div class="so-ssl-2fa-instructions" style="background: #f8f9fa; border-left: 4px solid #2271b1; padding: 15px; margin-bottom: 20px;">
            <h3 style="margin-top: 0; color: #2271b1;"><?php esc_html_e('Two-Factor Authentication Instructions', 'so-ssl'); ?></h3>

			<?php if ($method === 'authenticator'): ?>
                <h4><?php esc_html_e('Using an Authenticator App', 'so-ssl'); ?></h4>
                <ol>
                    <li><?php esc_html_e('Enable 2FA by checking the box below', 'so-ssl'); ?></li>
                    <li><?php esc_html_e('Install a compatible authenticator app on your mobile device (Google Authenticator, Microsoft Authenticator, or Authy)', 'so-ssl'); ?></li>
                    <li><?php esc_html_e('Scan the QR code or manually enter the secret key in your app', 'so-ssl'); ?></li>
                    <li><?php esc_html_e('Enter the 6-digit verification code from your app to confirm setup', 'so-ssl'); ?></li>
                    <li><?php esc_html_e('Generate and save backup codes for emergency access', 'so-ssl'); ?></li>
                    <li><?php esc_html_e('When logging in, you\'ll need to enter a code from your authenticator app', 'so-ssl'); ?></li>
                </ol>
                <p style="color: #d63638; font-weight: 600;"><?php esc_html_e('Important: Keep your backup codes in a safe place. You\'ll need them if you lose access to your authenticator app.', 'so-ssl'); ?></p>
			<?php else: ?>
                <h4><?php esc_html_e('Using Email Verification', 'so-ssl'); ?></h4>
                <ol>
                    <li><?php esc_html_e('Enable 2FA by checking the box below', 'so-ssl'); ?></li>
                    <li><?php esc_html_e('When logging in, a 6-digit code will be sent to your registered email address', 'so-ssl'); ?></li>
                    <li><?php esc_html_e('Enter the code within 10 minutes to complete login', 'so-ssl'); ?></li>
                    <li><?php esc_html_e('Generate and save backup codes for emergency access', 'so-ssl'); ?></li>
                </ol>
                <p style="color: #d63638; font-weight: 600;"><?php
					/* translators: %s: User email address */
					printf(esc_html__('Important: Make sure you have access to your email address: %s', 'so-ssl'), esc_html($user->user_email));
					?></p>
			<?php endif; ?>
        </div>

        <table class="form-table">
            <tr>
                <th><label for="so_ssl_2fa_enabled"><?php esc_html_e('Enable Two-Factor Authentication', 'so-ssl'); ?></label></th>
                <td>
                    <input type="checkbox" name="so_ssl_2fa_enabled" id="so_ssl_2fa_enabled" value="1" <?php checked($enabled, '1'); ?> />
                    <p class="description"><?php esc_html_e('Enable two-factor authentication for your account.', 'so-ssl'); ?></p>
                </td>
            </tr>

			<?php if ($method === 'authenticator'): ?>
                <tr id="so_ssl_2fa_authenticator_row" style="<?php echo ($enabled !== '1') ? 'display:none;' : ''; ?>">
                    <th><?php esc_html_e('Authenticator App Setup', 'so-ssl'); ?></th>
                    <td>
						<?php self::display_authenticator_setup($user); ?>
                    </td>
                </tr>
			<?php else: ?>
                <tr id="so_ssl_2fa_email_row" style="<?php echo esc_attr(($enabled !== '1') ? 'display:none;' : ''); ?>">
                    <th><?php esc_html_e('Email Authentication', 'so-ssl'); ?></th>
                    <td>
                        <div class="so-ssl-email-2fa-info">
                            <p><strong><?php esc_html_e('How Email Verification Works:', 'so-ssl'); ?></strong></p>
                            <ul style="list-style-type: disc; margin-left: 20px;">
                                <li><?php
									/* translators: %s: User email address */
									printf(esc_html__('A 6-digit code will be sent to: %s', 'so-ssl'), '<strong>' . esc_html($user->user_email) . '</strong>');
									?></li>
                                <li><?php esc_html_e('The code is valid for 10 minutes', 'so-ssl'); ?></li>
                                <li><?php esc_html_e('Check your spam folder if you don\'t receive the email', 'so-ssl'); ?></li>
                                <li><?php esc_html_e('You can use backup codes if email is unavailable', 'so-ssl'); ?></li>
                            </ul>

							<?php if ($user->user_email !== $user->ID): ?>
                                <p class="description" style="margin-top: 10px;">
									<?php
									/* translators: %s: Link to update email */
									printf(esc_html__('To change your email address, visit your %s', 'so-ssl'),
										'<a href="' . admin_url('profile.php') . '">' . esc_html__('profile settings', 'so-ssl') . '</a>'
									);
									?>
                                </p>
							<?php endif; ?>
                        </div>
                    </td>
                </tr>
			<?php endif; ?>

            <tr id="so_ssl_2fa_backup_codes_row" style="<?php echo ($enabled !== '1') ? 'display:none;' : ''; ?>">
                <th><?php esc_html_e('Backup Codes', 'so-ssl'); ?></th>
                <td>
                    <button type="button" id="so_ssl_generate_backup_codes" class="button"><?php esc_html_e('Generate Backup Codes', 'so-ssl'); ?></button>
                    <div id="so_ssl_backup_codes_container" style="display:none; margin-top: 10px;">
                        <p><?php esc_html_e('Save these backup codes in a safe place. They can be used if you lose access to your authentication method.', 'so-ssl'); ?></p>
                        <div id="so_ssl_backup_codes"></div>
                        <div id="so_ssl_backup_codes_actions" style="margin-top: 15px;">
                            <label>
                                <input type="checkbox" id="so_ssl_email_backup_codes" checked="checked" />
						        <?php esc_html_e('Email backup codes to:', 'so-ssl'); ?>
                                <strong><?php echo esc_html($user->user_email); ?></strong>
                            </label>
                            <button type="button" id="so_ssl_send_backup_codes" class="button" style="margin-left: 10px; display: none;">
						        <?php esc_html_e('Send Codes to Email', 'so-ssl'); ?>
                            </button>
                        </div>
                    </div>
                </td>
            </tr>
        </table>

        <script>
            jQuery(document).ready(function($) {
                // Toggle 2FA fields when checkbox changes
                $('#so_ssl_2fa_enabled').on('change', function() {
                    if ($(this).is(':checked')) {
                        $('#so_ssl_2fa_authenticator_row, #so_ssl_2fa_email_row, #so_ssl_2fa_backup_codes_row').show();
                    } else {
                        $('#so_ssl_2fa_authenticator_row, #so_ssl_2fa_email_row, #so_ssl_2fa_backup_codes_row').hide();
                    }
                });

                // Handle backup codes generation
                $('#so_ssl_generate_backup_codes').on('click', function() {
                    var data = {
                        'action': 'so_ssl_generate_backup_codes',
                        'user_id': <?php echo esc_js($user->ID); ?>,
                        'nonce': '<?php echo esc_js(wp_create_nonce('so_ssl_2fa_nonce')); ?>',
                        'send_email': $('#so_ssl_email_backup_codes').is(':checked') ? 1 : 0
                    };

                    $.post(ajaxurl, data, function(response) {
                        if (response.success) {
                            $('#so_ssl_backup_codes').html(response.data.codes_html);
                            $('#so_ssl_backup_codes_container').show();
                            $('#so_ssl_send_backup_codes').show();

                            if (response.data.email_sent) {
                                alert('<?php esc_html_e('Backup codes have been generated and sent to your email.', 'so-ssl'); ?>');
                            }
                        } else {
                            alert(response.data.message);
                        }
                    });
                });

                // Handle sending backup codes separately
                $('#so_ssl_send_backup_codes').on('click', function() {
                    var codes = [];
                    $('#so_ssl_backup_codes code').each(function() {
                        codes.push($(this).text());
                    });

                    var data = {
                        'action': 'so_ssl_email_backup_codes',
                        'user_id': <?php echo esc_js($user->ID); ?>,
                        'codes': codes,
                        'nonce': '<?php echo esc_js(wp_create_nonce('so_ssl_2fa_nonce')); ?>'
                    };

                    $.post(ajaxurl, data, function(response) {
                        if (response.success) {
                            alert(response.data.message);
                        } else {
                            alert(response.data.message);
                        }
                    });
                });
            });
        </script>
        <?php
    }

	/**
	 * Display Authenticator App setup UI with working QR code and code generation
	 *
	 * @param WP_User $user The user object
	 */
	public static function display_authenticator_setup($user) {
		// Get or generate secret key
		$secret = get_user_meta($user->ID, 'so_ssl_2fa_secret', true);
		if (empty($secret)) {
			if (!class_exists('TOTP')) {
				require_once SO_SSL_PATH . 'includes/class-so-ssl-totp.php';
			}

			$secret = TOTP::generateSecret();
			update_user_meta($user->ID, 'so_ssl_2fa_secret', $secret);
		}

		// Generate QR code URL
		$site_name = get_bloginfo('name');
		$user_identifier = $user->user_email;
		$totp_url = "otpauth://totp/" . urlencode($site_name) . ":" . urlencode($user_identifier) . "?secret=" . $secret . "&issuer=" . urlencode($site_name);

		?>
        <div class="so-ssl-authenticator-setup">
            <div class="so-ssl-setup-steps">
                <h3><?php esc_html_e('Setup Two-Factor Authentication', 'so-ssl'); ?></h3>

                <div class="so-ssl-step">
                    <span class="so-ssl-step-number">1</span>
                    <p><?php esc_html_e('Install an authenticator app on your phone:', 'so-ssl'); ?></p>
                    <ul class="so-ssl-app-list">
                        <li>
                            <span class="dashicons dashicons-smartphone"></span>
							<?php esc_html_e('Google Authenticator', 'so-ssl'); ?>
                        </li>
                        <li>
                            <span class="dashicons dashicons-smartphone"></span>
							<?php esc_html_e('Microsoft Authenticator', 'so-ssl'); ?>
                        </li>
                        <li>
                            <span class="dashicons dashicons-smartphone"></span>
							<?php esc_html_e('Authy', 'so-ssl'); ?>
                        </li>
                    </ul>
                </div>

                <div class="so-ssl-step">
                    <span class="so-ssl-step-number">2</span>
                    <p><?php esc_html_e('Scan this QR code with your authenticator app:', 'so-ssl'); ?></p>

                    <div class="so-ssl-qr-code-container">
                        <!-- QR code will be generated here by JavaScript -->
                        <div id="so-ssl-qr-code" data-totp-url="<?php echo esc_attr($totp_url); ?>"></div>
                        <div class="so-ssl-qr-loading">
                            <span class="spinner is-active"></span>
                            <p><?php esc_html_e('Generating QR code...', 'so-ssl'); ?></p>
                        </div>
                    </div>

                    <details class="so-ssl-manual-entry">
                        <summary><?php esc_html_e('Can\'t scan? Enter code manually', 'so-ssl'); ?></summary>
                        <div class="so-ssl-manual-code">
                            <label><?php esc_html_e('Account:', 'so-ssl'); ?></label>
                            <code class="so-ssl-copy-field"><?php echo esc_html($user_identifier); ?></code>
                            <button type="button" class="so-ssl-copy-btn" data-copy="<?php echo esc_attr($user_identifier); ?>">
                                <span class="dashicons dashicons-clipboard"></span>
                            </button>
                        </div>
                        <div class="so-ssl-manual-code">
                            <label><?php esc_html_e('Key:', 'so-ssl'); ?></label>
                            <code class="so-ssl-copy-field"><?php echo esc_html($secret); ?></code>
                            <button type="button" class="so-ssl-copy-btn" data-copy="<?php echo esc_attr($secret); ?>">
                                <span class="dashicons dashicons-clipboard"></span>
                            </button>
                        </div>
                        <div class="so-ssl-manual-code">
                            <label><?php esc_html_e('Type:', 'so-ssl'); ?></label>
                            <code class="so-ssl-copy-field">Time based</code>
                        </div>
                    </details>
                </div>

                <div class="so-ssl-step">
                    <span class="so-ssl-step-number">3</span>
                    <p><?php esc_html_e('Verify setup by entering a code from your app:', 'so-ssl'); ?></p>

                    <div class="so-ssl-verify-code">
                        <input type="text"
                               id="so_ssl_verify_code"
                               name="so_ssl_verify_code"
                               class="so-ssl-code-input"
                               maxlength="6"
                               pattern="\d{6}"
                               inputmode="numeric"
                               placeholder="000000" />

                        <button type="button" id="so_ssl_verify_code_button" class="button button-primary">
							<?php esc_html_e('Verify Code', 'so-ssl'); ?>
                        </button>

                        <div id="so_ssl_verify_result" class="so-ssl-verify-result"></div>
                    </div>
                </div>
            </div>

            <div class="so-ssl-setup-info">
                <h4><?php esc_html_e('Important Information', 'so-ssl'); ?></h4>
                <ul>
                    <li>
                        <span class="dashicons dashicons-info"></span>
						<?php esc_html_e('Save your secret key in a safe place as backup', 'so-ssl'); ?>
                    </li>
                    <li>
                        <span class="dashicons dashicons-info"></span>
						<?php esc_html_e('You\'ll need this code every time you log in', 'so-ssl'); ?>
                    </li>
                    <li>
                        <span class="dashicons dashicons-info"></span>
						<?php esc_html_e('Generate backup codes after setup is complete', 'so-ssl'); ?>
                    </li>
                </ul>
            </div>
        </div>

        <style>
            .so-ssl-authenticator-setup {
                max-width: 800px;
                background: #fff;
                padding: 20px;
                border-radius: 8px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            }

            .so-ssl-setup-steps {
                margin-bottom: 30px;
            }

            .so-ssl-step {
                margin-bottom: 30px;
                padding-left: 40px;
                position: relative;
            }

            .so-ssl-step-number {
                position: absolute;
                left: 0;
                top: 0;
                width: 30px;
                height: 30px;
                background: #2271b1;
                color: #fff;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-weight: bold;
            }

            .so-ssl-app-list {
                list-style: none;
                margin: 10px 0;
                padding: 0;
            }

            .so-ssl-app-list li {
                padding: 5px 0;
            }

            .so-ssl-app-list .dashicons {
                color: #2271b1;
                margin-right: 8px;
            }

            .so-ssl-qr-code-container {
                background: #f8f9fa;
                padding: 20px;
                border-radius: 8px;
                display: inline-block;
                margin: 15px 0;
                border: 2px solid #e5e5e5;
                position: relative;
                min-width: 240px;
                min-height: 240px;
            }

            #so-ssl-qr-code {
                display: none;
            }

            #so-ssl-qr-code canvas {
                display: block;
                margin: 0 auto;
            }

            .so-ssl-qr-loading {
                text-align: center;
                padding: 70px 20px;
            }

            .so-ssl-qr-loading .spinner {
                float: none;
                margin: 0 auto 10px;
            }

            .so-ssl-manual-entry {
                margin-top: 15px;
                background: #f0f6fc;
                padding: 15px;
                border-radius: 4px;
                border: 1px solid #c5d9ed;
            }

            .so-ssl-manual-entry summary {
                cursor: pointer;
                color: #2271b1;
                font-weight: 500;
            }

            .so-ssl-manual-code {
                display: flex;
                align-items: center;
                margin-top: 10px;
                gap: 10px;
            }

            .so-ssl-manual-code label {
                min-width: 70px;
                font-weight: 500;
            }

            .so-ssl-copy-field {
                flex: 1;
                background: #fff;
                padding: 5px 10px;
                border: 1px solid #ddd;
                border-radius: 3px;
                font-family: monospace;
                font-size: 14px;
            }

            .so-ssl-copy-btn {
                background: #fff;
                border: 1px solid #ddd;
                padding: 5px 10px;
                cursor: pointer;
                border-radius: 3px;
                transition: all 0.2s;
            }

            .so-ssl-copy-btn:hover {
                background: #f0f0f0;
            }

            .so-ssl-copy-btn .dashicons {
                font-size: 16px;
                width: 16px;
                height: 16px;
            }

            .so-ssl-code-input {
                font-size: 24px;
                font-family: monospace;
                text-align: center;
                width: 150px;
                padding: 10px;
                border: 2px solid #ddd;
                border-radius: 4px;
                margin-right: 10px;
                letter-spacing: 5px;
            }

            .so-ssl-code-input:focus {
                border-color: #2271b1;
                outline: none;
            }

            .so-ssl-verify-result {
                margin-top: 15px;
                padding: 10px;
                border-radius: 4px;
                display: none;
            }

            .so-ssl-verify-result.success {
                background: #f0f8ee;
                color: #46b450;
                border: 1px solid #46b450;
            }

            .so-ssl-verify-result.error {
                background: #fcf0f1;
                color: #dc3232;
                border: 1px solid #dc3232;
            }

            .so-ssl-setup-info {
                background: #f0f6fc;
                padding: 20px;
                border-radius: 8px;
                border: 1px solid #c5d9ed;
            }

            .so-ssl-setup-info h4 {
                margin-top: 0;
                color: #2271b1;
            }

            .so-ssl-setup-info ul {
                list-style: none;
                margin: 0;
                padding: 0;
            }

            .so-ssl-setup-info li {
                padding: 8px 0;
                display: flex;
                align-items: flex-start;
            }

            .so-ssl-setup-info .dashicons {
                color: #2271b1;
                margin-right: 10px;
                margin-top: 2px;
            }
        </style>

        <!-- Include QRCode.js library -->
        <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

        <script>
            jQuery(document).ready(function($) {
                // Generate QR code
                var qrContainer = document.getElementById('so-ssl-qr-code');
                var totpUrl = qrContainer.getAttribute('data-totp-url');

                if (qrContainer && totpUrl) {
                    try {
                        // Create QR code
                        var qrcode = new QRCode(qrContainer, {
                            text: totpUrl,
                            width: 200,
                            height: 200,
                            colorDark : "#000000",
                            colorLight : "#ffffff",
                            correctLevel : QRCode.CorrectLevel.M
                        });

                        // Hide loading spinner and show QR code
                        $('.so-ssl-qr-loading').hide();
                        $('#so-ssl-qr-code').show();
                    } catch (err) {
                        console.error('QR Code generation failed:', err);
                        // Fallback to Google Charts API
                        $('.so-ssl-qr-loading').html('<p><?php esc_html_e('Failed to generate QR code. Please use manual entry.', 'so-ssl'); ?></p>');
                    }
                }

                // Copy to clipboard functionality
                $('.so-ssl-copy-btn').on('click', function() {
                    var textToCopy = $(this).data('copy');
                    var button = $(this);

                    if (navigator.clipboard) {
                        navigator.clipboard.writeText(textToCopy).then(function() {
                            button.find('.dashicons').removeClass('dashicons-clipboard').addClass('dashicons-yes');
                            setTimeout(function() {
                                button.find('.dashicons').removeClass('dashicons-yes').addClass('dashicons-clipboard');
                            }, 2000);
                        });
                    } else {
                        // Fallback for older browsers
                        var tempInput = $('<input>');
                        $('body').append(tempInput);
                        tempInput.val(textToCopy).select();
                        document.execCommand('copy');
                        tempInput.remove();

                        button.find('.dashicons').removeClass('dashicons-clipboard').addClass('dashicons-yes');
                        setTimeout(function() {
                            button.find('.dashicons').removeClass('dashicons-yes').addClass('dashicons-clipboard');
                        }, 2000);
                    }
                });

                // Auto-format the code input
                $('#so_ssl_verify_code').on('input', function() {
                    var value = $(this).val().replace(/\D/g, '');
                    if (value.length > 6) {
                        value = value.substr(0, 6);
                    }
                    $(this).val(value);
                });

                // Verify code
                $('#so_ssl_verify_code_button').on('click', function() {
                    var code = $('#so_ssl_verify_code').val();
                    var button = $(this);

                    if (code.length !== 6) {
                        $('#so_ssl_verify_result')
                            .removeClass('success')
                            .addClass('error')
                            .html('<span class="dashicons dashicons-no"></span> <?php esc_html_e('Please enter a 6-digit code', 'so-ssl'); ?>')
                            .show();
                        return;
                    }

                    button.prop('disabled', true).text('<?php esc_html_e('Verifying...', 'so-ssl'); ?>');

                    var data = {
                        'action': 'so_ssl_verify_totp_code',
                        'user_id': <?php echo esc_js($user->ID); ?>,
                        'code': code,
                        'nonce': '<?php echo esc_js(wp_create_nonce('so_ssl_2fa_nonce')); ?>'
                    };

                    $.post(ajaxurl, data, function(response) {
                        if (response.success) {
                            $('#so_ssl_verify_result')
                                .removeClass('error')
                                .addClass('success')
                                .html('<span class="dashicons dashicons-yes"></span> ' + response.data.message)
                                .show();
                        } else {
                            $('#so_ssl_verify_result')
                                .removeClass('success')
                                .addClass('error')
                                .html('<span class="dashicons dashicons-no"></span> ' + response.data.message)
                                .show();
                        }

                        button.prop('disabled', false).text('<?php esc_html_e('Verify Code', 'so-ssl'); ?>');
                    }).fail(function() {
                        $('#so_ssl_verify_result')
                            .removeClass('success')
                            .addClass('error')
                            .html('<span class="dashicons dashicons-no"></span> <?php esc_html_e('Verification failed. Please try again.', 'so-ssl'); ?>')
                            .show();

                        button.prop('disabled', false).text('<?php esc_html_e('Verify Code', 'so-ssl'); ?>');
                    });
                });
            });
        </script>
		<?php
	}

    /**
     * Save 2FA user fields
     *
     * @param int $user_id The user ID
     */
    public static function save_2fa_user_fields($user_id) {
        // Check for nonce - explicitly for code analyzer
        if (!isset($_POST['_wpnonce'])) {
            return;
        }

        if (!current_user_can('edit_user', $user_id)) {
            return;
        }

        // Only proceed if 2FA is required for this user
        $user = get_userdata($user_id);
        if (!self::is_2fa_required_for_user($user)) {
            return;
        }

        // Verify nonce
        check_admin_referer('update-user_' . $user_id);

        // Save 2FA enabled status - note no need to unslash as we're just checking if it's set
        $enabled = isset($_POST['so_ssl_2fa_enabled']) ? '1' : '0';
        update_user_meta($user_id, 'so_ssl_2fa_enabled', $enabled);
    }

    /**
     * Add 2FA fields to login form
     */
    public static function add_2fa_login_fields() {
        // Get session information - Sanitize when retrieving
        $requires_2fa = isset($_SESSION['so_ssl_2fa_required']) ? (bool)$_SESSION['so_ssl_2fa_required'] : false;

        if ($requires_2fa) {
            // Add nonce field for CSRF protection
            wp_nonce_field('so_ssl_2fa_verification', 'so_ssl_2fa_nonce');
            ?>
            <p>
                <label for="so_ssl_2fa_code"><?php esc_html_e('Authentication Code', 'so-ssl'); ?><br />
                    <input type="text" name="so_ssl_2fa_code" id="so_ssl_2fa_code" class="input" size="20" autocomplete="off" />
                </label>
            </p>
            <?php
        }
    }

    /**
     * Filter for login message
     *
     * @param string $message The login message
     * @return string Modified login message
     */
    public static function login_message($message) {
        // Get session information - Sanitize when retrieving
        $requires_2fa = isset($_SESSION['so_ssl_2fa_required']) ? (bool)$_SESSION['so_ssl_2fa_required'] : false;

        if ($requires_2fa) {
            $user_id = isset($_SESSION['so_ssl_2fa_user_id']) ? absint($_SESSION['so_ssl_2fa_user_id']) : 0;
            $method = get_option('so_ssl_2fa_method', 'email');

            if ($method === 'email') {
                // Send verification code via email
                self::send_verification_email($user_id);

                $message .= '<p class="message">' . __('Please enter the verification code sent to your email.', 'so-ssl') . '</p>';
            } else {
                $message .= '<p class="message">' . __('Please enter the verification code from your authenticator app.', 'so-ssl') . '</p>';
            }
        }

        return $message;
    }

    /**
     * Modify authentication logic to include 2FA
     *
     * @param WP_User|WP_Error|null $user User object, WP_Error, or null
     * @param string $username The username
     * @param string $password The password
     * @return WP_User|WP_Error User object or error
     */
    public static function authenticate_2fa($user, $username, $password) {
        // If already errored, return the error
        if (is_wp_error($user)) {
            return $user;
        }

        // If not a user object, return as is
        if (!is_a($user, 'WP_User')) {
            return $user;
        }

        // Check if 2FA is enabled and required for this user
        $user_2fa_enabled = get_user_meta($user->ID, 'so_ssl_2fa_enabled', true);

        if (!self::is_2fa_required_for_user($user) || $user_2fa_enabled !== '1') {
            return $user;
        }

        // Start session for 2FA
        if (!session_id()) {
            session_start();
        }

        // Check if this is a 2FA verification attempt
        if (isset($_POST['so_ssl_2fa_code'])) {
            // Verify nonce before processing the 2FA code
            // Unslash and sanitize the nonce
            if (!isset($_POST['so_ssl_2fa_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['so_ssl_2fa_nonce'])), 'so_ssl_2fa_verification')) {
                return new WP_Error('invalid_nonce', __('<strong>ERROR</strong>: Security verification failed. Please try again.', 'so-ssl'));
            }

            // Unslash before sanitizing
            $code = sanitize_text_field(wp_unslash($_POST['so_ssl_2fa_code']));

            // Verify the code
            if (self::is_verification_code_valid($user->ID, $code) || self::is_backup_code_valid($user->ID, $code)) {
                // Code is valid, clear session data
                unset($_SESSION['so_ssl_2fa_required']);
                unset($_SESSION['so_ssl_2fa_user_id']);

                // Allow login
                return $user;
            } else {
                // Invalid code
                return new WP_Error('invalid_2fa_code', __('<strong>ERROR</strong>: Invalid verification code.', 'so-ssl'));
            }
        } else {
            // Set session data for 2FA verification - Sanitize when setting
            $_SESSION['so_ssl_2fa_required'] = true; // Boolean value is inherently safe
            $_SESSION['so_ssl_2fa_user_id'] = absint($user->ID); // Use absint to ensure it's a positive integer

            // Prevent login until 2FA is verified
            return new WP_Error('2fa_required', __('<strong>INFO</strong>: Please enter your two-factor authentication code.', 'so-ssl'));
        }
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
        /* translators: %s: Site name */
            __('[%s] Your login verification code', 'so-ssl'),
            get_bloginfo('name')
        );

        $message = sprintf(
            /* translators: %s: User's display name */
                __('Hello %s,', 'so-ssl'),
                $user->display_name
            ) . "\n\n";

        $message .= sprintf(
            /* translators: %s: Site name */
                __('Your verification code for logging into %s is:', 'so-ssl'),
                get_bloginfo('name')
            ) . "\n\n";

        $message .= $code . "\n\n";
        $message .= __('This code will expire in 10 minutes.', 'so-ssl') . "\n\n";

        $message .= sprintf(
        /* translators: %s: Site name */
            __('If you did not attempt to log in to %s, please contact your site administrator immediately.', 'so-ssl'),
            get_bloginfo('name')
        );

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
            // Verify TOTP code
            if (!class_exists('TOTP')) {
                require_once SO_SSL_PATH . 'includes/totp.php';
            }

            $secret = get_user_meta($user_id, 'so_ssl_2fa_secret', true);
            if (empty($secret)) {
                return false;
            }

            return TOTP::verifyCode($secret, $code);
        }
    }

    /**
     * Check for backup code
     *
     * @param int $user_id The user ID
     * @param string $code The backup code
     * @return bool Whether the backup code is valid
     */
    public static function is_backup_code_valid($user_id, $code) {
        $backup_codes = get_user_meta($user_id, 'so_ssl_2fa_backup_codes', true);

        if (!is_array($backup_codes)) {
            return false;
        }

        $code = trim($code);

        foreach ($backup_codes as $key => $backup_code) {
            if ($backup_code === $code) {
                // Remove the used backup code
                unset($backup_codes[$key]);
                update_user_meta($user_id, 'so_ssl_2fa_backup_codes', $backup_codes);
                return true;
            }
        }

        return false;
    }

    /**
     * Verify 2FA during login
     *
     * @param string $user_login The username
     * @param WP_User $user The user object
     */
    public static function verify_2fa($user_login, $user) {
        // This function can be extended if needed for additional verification steps
    }

    /**
     * Enqueue scripts and styles
     */
    public static function enqueue_scripts() {
        wp_enqueue_style('so-ssl-2fa', SO_SSL_URL . 'assets/css/so-ssl-2fa.css', array(), SO_SSL_VERSION);
        wp_enqueue_script('so-ssl-2fa', SO_SSL_URL . 'assets/js/so-ssl-2fa.js', array('jquery'), SO_SSL_VERSION, true);
    }

    /**
     * AJAX handler for generating backup codes
     */
	public static function generate_backup_codes() {
		check_ajax_referer('so_ssl_2fa_nonce', 'nonce');

		$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
		$send_email = isset($_POST['send_email']) ? intval($_POST['send_email']) : 0;

		if (!current_user_can('edit_user', $user_id)) {
			wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'so-ssl')));
		}

		// Generate 10 backup codes
		$backup_codes = array();
		for ($i = 0; $i < 10; $i++) {
			$backup_codes[] = self::generate_backup_code();
		}

		// Save to user meta
		update_user_meta($user_id, 'so_ssl_2fa_backup_codes', $backup_codes);

		// Prepare HTML for display
		$codes_html = '<ul class="so-ssl-backup-codes">';
		foreach ($backup_codes as $code) {
			$codes_html .= '<li><code>' . esc_html($code) . '</code></li>';
		}
		$codes_html .= '</ul>';

		$email_sent = false;

		// Send email if requested
		if ($send_email) {
			$email_sent = self::send_backup_codes_email($user_id, $backup_codes);
		}

		wp_send_json_success(array(
			'codes_html' => $codes_html,
			'email_sent' => $email_sent
		));
	}

    /**
     * Generate a random backup code
     *
     * @return string The backup code
     */
    public static function generate_backup_code() {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $code = '';

        for ($i = 0; $i < 10; $i++) {
            $code .= $chars[wp_rand(0, strlen($chars) - 1)];
        }

        return $code;
    }

    /**
     * AJAX handler for verifying TOTP code
     */
    public static function verify_totp_code() {
        check_ajax_referer('so_ssl_2fa_nonce', 'nonce');

        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        // Unslash before sanitizing
        $code = isset($_POST['code']) ? sanitize_text_field(wp_unslash($_POST['code'])) : '';

        if (!current_user_can('edit_user', $user_id)) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'so-ssl')));
        }

        if (empty($code)) {
            wp_send_json_error(array('message' => __('Please enter a verification code.', 'so-ssl')));
        }

        // Verify the code
        if (!class_exists('TOTP')) {
            require_once SO_SSL_PATH . 'includes/totp.php';
        }

        $secret = get_user_meta($user_id, 'so_ssl_2fa_secret', true);
        if (empty($secret)) {
            wp_send_json_error(array('message' => __('Secret key not found.', 'so-ssl')));
        }

        if (TOTP::verifyCode($secret, $code)) {
            // Code is valid
            update_user_meta($user_id, 'so_ssl_2fa_verified', 1);
            wp_send_json_success(array('message' => __('Code verified successfully!', 'so-ssl')));
        } else {
            wp_send_json_error(array('message' => __('Invalid code. Please try again.', 'so-ssl')));
        }
    }

	/**
	 * Send backup codes via email
	 *
	 * @param int $user_id The user ID
	 * @param array $backup_codes The backup codes
	 * @return bool Success or failure
	 */
	public static function send_backup_codes_email($user_id, $backup_codes) {
		$user = get_userdata($user_id);
		if (!$user) {
			return false;
		}

		// Prepare email
		$subject = sprintf(
		/* translators: %s: Site name */
			__('[%s] Your Two-Factor Authentication Backup Codes', 'so-ssl'),
			get_bloginfo('name')
		);

		$message = sprintf(
		           /* translators: %s: User's display name */
			           __('Hello %s,', 'so-ssl'),
			           $user->display_name
		           ) . "\n\n";

		$message .= __('Here are your Two-Factor Authentication backup codes. Store them in a safe place.', 'so-ssl') . "\n\n";
		$message .= __('Each code can only be used once:', 'so-ssl') . "\n\n";

		foreach ($backup_codes as $code) {
			$message .= $code . "\n";
		}

		$message .= "\n" . __('Important:', 'so-ssl') . "\n";
		$message .= __('- Keep these codes secure and do not share them with anyone', 'so-ssl') . "\n";
		$message .= __('- Each code can only be used once', 'so-ssl') . "\n";
		$message .= __('- Generate new codes if you run out', 'so-ssl') . "\n\n";

		$message .= sprintf(
		/* translators: %s: Site name */
			__('If you did not request these codes, please contact your site administrator immediately.', 'so-ssl'),
			get_bloginfo('name')
		);

		// Send email
		return wp_mail($user->user_email, $subject, $message);
	}

	/**
	 * AJAX handler for emailing backup codes separately
	 */
	public static function ajax_email_backup_codes() {
		check_ajax_referer('so_ssl_2fa_nonce', 'nonce');

		$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
		$codes = isset($_POST['codes']) ? array_map('sanitize_text_field', $_POST['codes']) : array();

		if (!current_user_can('edit_user', $user_id)) {
			wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'so-ssl')));
		}

		if (empty($codes)) {
			wp_send_json_error(array('message' => __('No backup codes to send.', 'so-ssl')));
		}

		if (self::send_backup_codes_email($user_id, $codes)) {
			wp_send_json_success(array('message' => __('Backup codes have been sent to your email.', 'so-ssl')));
		} else {
			wp_send_json_error(array('message' => __('Failed to send backup codes. Please try again.', 'so-ssl')));
		}
	}
}

// Initialize the class
So_SSL_Two_Factor::init();

// Register AJAX handlers
add_action('wp_ajax_so_ssl_generate_backup_codes', array('So_SSL_Two_Factor', 'generate_backup_codes'));
add_action('wp_ajax_so_ssl_verify_totp_code', array('So_SSL_Two_Factor', 'verify_totp_code'));
// Register AJAX handlers
add_action('wp_ajax_so_ssl_generate_backup_codes', array('So_SSL_Two_Factor', 'generate_backup_codes'));
add_action('wp_ajax_so_ssl_verify_totp_code', array('So_SSL_Two_Factor', 'verify_totp_code'));
add_action('wp_ajax_so_ssl_email_backup_codes', array('So_SSL_Two_Factor', 'ajax_email_backup_codes')); // Add this line
