(function ($) {
    'use strict';

    // Function to initialize tooltips
    function initializeTooltips() {
        if (typeof $.fn.tooltip !== 'undefined') {
            $('.so-ssl-tooltip').tooltip();
        }
    }

    // Function to copy text to clipboard
    function copyToClipboard(text) {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
    }

    // Initialize on document ready
    $(document).ready(function () {
        initializeTooltips();

        // Copy backup code when clicked
        $(document).on('click', '.so-ssl-backup-codes code', function () {
            const code = $(this).text();
            copyToClipboard(code);

            // Show feedback
            const original = $(this).text();
            $(this).text('Copied!');

            setTimeout(function () {
                $(this).text(original);
            }.bind(this), 1000);
        });

        // Format verification code input
        $(document).on('input', '#so_ssl_verify_code, #so_ssl_2fa_code', function () {
            // Remove non-numeric characters
            let value = $(this).val().replace(/[^0-9]/g, '');

            // Limit to 6 digits
            if (value.length > 6) {
                value = value.substring(0, 6);
            }

            $(this).val(value);
        });
    });

})(jQuery);
