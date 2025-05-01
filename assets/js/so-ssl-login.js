/**
 * So SSL Login JavaScript
 *
 * Enhances the login page when two-factor authentication is active.
 */

(function($) {
    'use strict';

    // Format verification code input
    function formatVerificationCode() {
        $('#so_ssl_2fa_code').on('input', function() {
            // Remove non-numeric characters
            let value = $(this).val().replace(/[^0-9]/g, '');
            
            // Limit to 6 digits
            if (value.length > 6) {
                value = value.substring(0, 6);
            }
            
            $(this).val(value);
        });
    }

    // Add shield icon to 2FA form
    function addShieldIcon() {
        if ($('.login-form-2fa-code').length > 0) {
            $('.login-form-2fa-code').before(
                '<div class="so-ssl-2fa-shield">' +
                '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">' +
                '<path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 4c1.86 0 3.41 1.28 3.86 3H8.14c.45-1.72 2-3 3.86-3zm-4 5h8v2h-8V10zm8 3v1H8v-1h8z" ' +
                'fill="#2271b1"/>' +
                '</svg>' +
                '</div>'
            );
        }
    }

    // Add backup code link
    function addBackupCodeLink() {
        if ($('.login-form-2fa-code').length > 0) {
            $('.login-form-2fa-code').after(
                '<div class="so-ssl-2fa-info">' +
                'Check your email or authenticator app for your verification code.' +
                '<br>' +
                '<a href="#" class="so-ssl-2fa-backup-link" id="use-backup-code">Use a backup code instead</a>' +
                '</div>'
            );

            // Handle backup code link click
            $('#use-backup-code').on('click', function(e) {
                e.preventDefault();
                
                // Change label text
                $('.login-form-2fa-code label').text('Backup Code:');
                
                // Change button text
                $('.login-form-2fa-code').next('p').find('.button-primary').val('Use Backup Code');
                
                // Update helper text
                $('.so-ssl-2fa-info').html(
                    'Enter one of your backup codes.' +
                    '<br>' +
                    '<a href="#" class="so-ssl-2fa-backup-link" id="use-2fa-code">Use verification code instead</a>'
                );
                
                // Handle return to verification code
                $('#use-2fa-code').on('click', function(e) {
                    e.preventDefault();
                    
                    // Reset to original state
                    $('.login-form-2fa-code label').text('Authentication Code:');
                    $('.login-form-2fa-code').next('p').find('.button-primary').val('Log In');
                    
                    addBackupCodeLink();
                });
            });
        }
    }

    // Focus on 2FA input
    function focusOnInput() {
        $('#so_ssl_2fa_code').focus();
    }

    // Initialize when document is ready
    $(document).ready(function() {
        formatVerificationCode();
        addShieldIcon();
        addBackupCodeLink();
        focusOnInput();
    });

})(jQuery);
