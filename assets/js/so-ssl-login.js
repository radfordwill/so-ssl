/**
 * So SSL Admin JavaScript
 *
 * Main JavaScript file for the So SSL plugin admin interface.
 * Added unsaved changes warning to prevent accidental navigation away from the page.
 */

(function ($) {
    'use strict';

    // Flag to track if form has been modified
    let formModified = false;

    // Initialize tooltips if available
    function initializeTooltips() {
        if (typeof $.fn.tooltip !== 'undefined') {
            $('.so-ssl-tooltip').tooltip();
        }
    }

    // Toggle sections based on checkbox state
    function toggleSections() {
        // HSTS fields
        $('#so_ssl_enable_hsts').on('change', function () {
            const $hstsFields = $('.so-ssl-hsts-field').closest('tr');
            if ($(this).is(':checked')) {
                $hstsFields.fadeIn(300);
            } else {
                $hstsFields.fadeOut(200);
            }
            formModified = true;
        });

        // X-Frame-Options fields
        $('#so_ssl_enable_xframe').on('change', function () {
            const $xframeFields = $('.so-ssl-xframe-field').closest('tr');
            if ($(this).is(':checked')) {
                $xframeFields.fadeIn(300);
            } else {
                $xframeFields.fadeOut(200);
            }
            formModified = true;
        });

        // CSP Frame-Ancestors fields
        $('#so_ssl_enable_csp_frame_ancestors').on('change', function () {
            const $cspFields = $('.so-ssl-csp-field').closest('tr');
            if ($(this).is(':checked')) {
                $cspFields.fadeIn(300);
            } else {
                $cspFields.fadeOut(200);
            }
            formModified = true;
        });

        // Content Security Policy fields
        $('#so_ssl_enable_csp').on('change', function () {
            const $cspFields = $('.so-ssl-csp-directive-field').closest('tr');
            const $cspMode = $('#so_ssl_csp_mode').closest('tr');
            if ($(this).is(':checked')) {
                $cspMode.fadeIn(300);
                $cspFields.fadeIn(300);
            } else {
                $cspMode.fadeOut(200);
                $cspFields.fadeOut(200);
            }
            formModified = true;
        });

        // Permissions Policy fields
        $('#so_ssl_enable_permissions_policy').on('change', function () {
            const $permissionsFields = $('.so-ssl-permissions-policy-field').closest('tr');
            if ($(this).is(':checked')) {
                $permissionsFields.fadeIn(300);
            } else {
                $permissionsFields.fadeOut(200);
            }
            formModified = true;
        });

        // Referrer Policy fields
        $('#so_ssl_enable_referrer_policy').on('change', function () {
            const $referrerFields = $('.so-ssl-referrer-field').closest('tr');
            if ($(this).is(':checked')) {
                $referrerFields.fadeIn(300);
            } else {
                $referrerFields.fadeOut(200);
            }
            formModified = true;
        });

        // Two-Factor Authentication fields
        $('#so_ssl_enable_2fa').on('change', function () {
            const $twoFactorFields = $('.so-ssl-2fa-field').closest('tr');
            if ($(this).is(':checked')) {
                $twoFactorFields.fadeIn(300);
            } else {
                $twoFactorFields.fadeOut(200);
            }
            formModified = true;
        });

        // User Sessions fields
        $('#so_ssl_enable_user_sessions').on('change', function () {
            const $sessionsFields = $('.so-ssl-sessions-field').closest('tr');
            if ($(this).is(':checked')) {
                $sessionsFields.fadeIn(300);
            } else {
                $sessionsFields.fadeOut(200);
            }
            formModified = true;
        });

        // Login Limiting fields
        $('#so_ssl_enable_login_limit').on('change', function () {
            const $loginLimitFields = $('.so-ssl-login-limit-field').closest('tr');
            if ($(this).is(':checked')) {
                $loginLimitFields.fadeIn(300);
            } else {
                $loginLimitFields.fadeOut(200);
            }
            formModified = true;
        });
    }

    // Dynamic CSP field handling
    function handleCSPFields() {
        // CSP frame ancestors option toggle
        $('#so_ssl_csp_frame_ancestors_option').on('change', function () {
            const value = $(this).val();
            const $customFields = $('.so-ssl-csp-custom-field').closest('tr');

            if (value === 'custom') {
                $customFields.fadeIn(300);
            } else {
                $customFields.fadeOut(200);
            }
            formModified = true;
        });
    }

    // X-Frame-Options field handling
    function handleXFrameFields() {
        // X-Frame-Options toggle
        $('#so_ssl_xframe_option').on('change', function () {
            const value = $(this).val();
            const $allowFromField = $('.so_ssl_allow_from_field').closest('tr');

            if (value === 'allowfrom') {
                $allowFromField.fadeIn(300);
            } else {
                $allowFromField.fadeOut(200);
            }
            formModified = true;
        });
    }

    // Copy backup codes to clipboard
    function handleBackupCodes() {
        // Copy backup code when clicked
        $(document).on('click', '.so-ssl-backup-codes li', function () {
            const code = $(this).text();
            navigator.clipboard.writeText(code).then(function () {
                const $element = $(this);
                const originalText = $element.text();

                $element.text('Copied!');

                setTimeout(function () {
                    $element.text(originalText);
                }, 1000);
            }.bind(this));
        });
    }

    // Format verification code input
    function formatVerificationCode() {
        $(document).on('input', '#so_ssl_verify_code, #so_ssl_2fa_code', function () {
            // Remove non-numeric characters
            let value = $(this).val().replace(/[^0-9]/g, '');

            // Limit to 6 digits
            if (value.length > 6) {
                value = value.substring(0, 6);
            }

            // Add a space after the 3rd digit for readability
            if (value.length > 3) {
                value = value.substring(0, 3) + ' ' + value.substring(3);
            }

            $(this).val(value);
            formModified = true;
        });
    }

    // Calculate and display security score
    function calculateSecurityScore() {
        if ($('.so-ssl-security-score').length > 0) {
            let score = 0;
            let total = 0;

            // Check SSL enabled
            if ($('#so_ssl_force_ssl').is(':checked')) {
                score += 15;
            }
            total += 15;

            // Check HSTS enabled
            if ($('#so_ssl_enable_hsts').is(':checked')) {
                score += 10;
            }
            total += 10;

            // Check X-Frame-Options enabled
            if ($('#so_ssl_enable_xframe').is(':checked')) {
                score += 5;
            }
            total += 5;

            // Check CSP enabled
            if ($('#so_ssl_enable_csp').is(':checked')) {
                score += 15;
            }
            total += 15;

            // Check 2FA enabled
            if ($('#so_ssl_enable_2fa').is(':checked')) {
                score += 20;
            }
            total += 20;

            // Check strong passwords enabled
            if ($('#so_ssl_disable_weak_passwords').is(':checked')) {
                score += 15;
            }
            total += 15;

            // Check session management enabled
            if ($('#so_ssl_enable_user_sessions').is(':checked')) {
                score += 10;
            }
            total += 10;

            // Check login limiting enabled
            if ($('#so_ssl_enable_login_limit').is(':checked')) {
                score += 10;
            }
            total += 10;

            // Calculate percentage
            const percentage = Math.round((score / total) * 100);

            // Update UI
            $('.so-ssl-score-fill').css('height', percentage + '%');
            $('.so-ssl-score-text').text(percentage + '%');

            // Set color based on score
            let color = '#dc3232'; // Red for low scores

            if (percentage >= 70) {
                color = '#46b450'; // Green for high scores
            } else if (percentage >= 40) {
                color = '#ffb900'; // Yellow for medium scores
            }

            $('.so-ssl-score-fill').css('background-color', color);
        }
    }

    // Handle tab navigation
    function handleTabs() {
        $('.nav-tab').on('click', function (e) {
            e.preventDefault();

            // Check for unsaved changes before switching tabs
            if (formModified && !confirm(soSslAdmin.unsavedChangesWarning)) {
                return false;
            }

            // Remove active class from all tabs
            $('.nav-tab').removeClass('nav-tab-active');
            $('.settings-tab').removeClass('active');

            // Add active class to clicked tab
            $(this).addClass('nav-tab-active');

            // Show corresponding tab content
            const tabId = $(this).attr('href').substring(1);
            $('#' + tabId).addClass('active');

            // Update hidden input
            $('#active_tab').val(tabId);

            // Save to localStorage
            localStorage.setItem('so_ssl_active_tab', tabId);

            // Update URL hash without scrolling
            if (history.pushState) {
                history.pushState(null, null, '#' + tabId);
            } else {
                window.location.hash = tabId;
            }
        });

        // Initialize active tab
        function initActiveTab() {
            // First try to get from URL hash
            let activeTab = window.location.hash.substring(1);

            // If no hash, try localStorage
            if (!activeTab) {
                activeTab = localStorage.getItem('so_ssl_active_tab');
            }

            // If still no activeTab, use default
            if (!activeTab || !$('#' + activeTab).length) {
                activeTab = 'ssl-settings';
            }

            // Activate the tab
            $('.nav-tab[href="#' + activeTab + '"]').trigger('click');
        }

        // Initialize active tab
        initActiveTab();
    }

    /**
     * Updated function to properly convert checkboxes to toggle switches
     * This function should replace the convertCheckboxesToSwitches function in assets/js/so-ssl-admin.js
     */
    function convertCheckboxesToSwitches() {
        $('input[type="checkbox"]').each(function () {
            const $checkbox = $(this);

            // Skip if already converted or has the skip class
            if ($checkbox.hasClass('so-ssl-switch-converted') || $checkbox.hasClass('so-ssl-no-switch')) {
                return;
            }

            const id = $checkbox.attr('id');
            const isChecked = $checkbox.is(':checked');

            // Mark the checkbox as converted
            $checkbox.addClass('so-ssl-switch-converted');

            // Create toggle switch with proper checked state
            const $switch = $('<label class="so-ssl-switch" for="' + id + '"></label>');
            const $slider = $('<span class="so-ssl-slider"></span>');

            // Add the slider to the switch
            $switch.append($slider);

            // Insert the switch after the checkbox
            $checkbox.after($switch);

            // Set initial state visually if needed
            if (isChecked) {
                $slider.addClass('checked');
            }

            // Add event listener to animate the slider when checkbox state changes
            $checkbox.on('change', function () {
                if ($(this).is(':checked')) {
                    $slider.addClass('checked');
                } else {
                    $slider.removeClass('checked');
                }
                formModified = true;
            });
        });

        // Hide all converted checkboxes with CSS
        $('head').append('<style>.so-ssl-switch-converted { position: absolute !important; left: -9999px !important; opacity: 0 !important; }</style>');
    }

    /**
     * Alternative improved implementation for complete toggle switch functionality
     */
    function improvedConvertCheckboxesToSwitches() {
        $('input[type="checkbox"]').each(function () {
            const $checkbox = $(this);

            // Skip if already converted or has the skip class
            if ($checkbox.hasClass('so-ssl-switch-converted') || $checkbox.hasClass('so-ssl-no-switch')) {
                return;
            }

            const id = $checkbox.attr('id');
            const name = $checkbox.attr('name');
            const value = $checkbox.attr('value') || '1';
            const isChecked = $checkbox.is(':checked');

            // Hide the original checkbox but keep it in the DOM for form submission
            $checkbox.addClass('so-ssl-switch-converted');

            // Create toggle switch
            const $switch = $('<label class="so-ssl-switch" for="' + id + '"></label>');
            const $slider = $('<span class="so-ssl-slider"></span>');

            // Add the slider to the switch
            $switch.append($slider);

            // Insert the switch after the checkbox
            $checkbox.after($switch);

            // Set initial visual state
            if (isChecked) {
                $slider.addClass('checked');
            }

            // When the checkbox changes (through label click), update the slider
            $checkbox.on('change', function () {
                if ($(this).is(':checked')) {
                    $slider.addClass('checked').css('background-color', '#2271b1');
                    $slider.find('before').css('transform', 'translateX(24px)');
                } else {
                    $slider.removeClass('checked').css('background-color', '#ccc');
                    $slider.find('before').css('transform', 'translateX(0)');
                }
                formModified = true;
            });
        });

        // Add dynamic styles for the animation
        $('head').append(`
            <style>
                .so-ssl-switch-converted {
                    position: absolute !important;
                    left: -9999px !important;
                    opacity: 0 !important;
                }
                .so-ssl-slider.checked {
                    background-color: #2271b1 !important;
                }
                .so-ssl-slider.checked:before {
                    transform: translateX(24px) !important;
                }
            </style>
        `);
    }

    // Handle form submission
    function handleFormSubmission() {
        $('form').on('submit', function () {
            // Clear the formModified flag since we're saving
            formModified = false;
        });
    }

    // Track form changes
    function trackFormChanges() {
        // Mark form as modified when inputs change
        $('input, select, textarea').on('change', function () {
            formModified = true;
        });

        // Also track typing in text fields
        $('input[type="text"], input[type="number"], textarea').on('input', function () {
            formModified = true;
        });
    }

    // Set up beforeunload event for unsaved changes warning
    function setupUnsavedChangesWarning() {
        $(window).on('beforeunload', function (e) {
            if (formModified) {
                // The message set here isn't actually used by modern browsers,
                // but we need to return something for the confirmation dialog to appear
                const message = soSslAdmin.unsavedChangesWarning;
                e.preventDefault();
                e.returnValue = message;
                return message;
            }
        });
    }

    // Initialize when document is ready
    $(document).ready(function () {
        // Initialize tooltips
        initializeTooltips();

        // Initialize toggle sections
        toggleSections();

        // Handle CSP fields
        handleCSPFields();

        // Handle X-Frame-Options fields
        handleXFrameFields();

        // Handle backup codes
        handleBackupCodes();

        // Format verification code input
        formatVerificationCode();

        // Calculate security score
        calculateSecurityScore();

        // Handle tabs
        handleTabs();

        // Convert checkboxes to toggle switches
        convertCheckboxesToSwitches();

        // Initial setup - hide fields for disabled features
        $('input[type="checkbox"]').each(function () {
            $(this).trigger('change');
        });

        // Trigger change for select fields
        $('#so_ssl_csp_frame_ancestors_option, #so_ssl_xframe_option').trigger('change');

        // Handle form submission
        handleFormSubmission();

        // Track form changes
        trackFormChanges();

        // Set up unsaved changes warning
        setupUnsavedChangesWarning();
    });

    jQuery(document).ready(function ($) {
        // Find the SVG logo and increase its attributes
        $('.so-ssl-icon').attr({
            'width': '48',
            'height': '48'
        });

        // Optional: Enhance the SVG itself for better clarity at larger sizes
        $('.so-ssl-icon path').attr({
            'stroke-width': '6'  // Reduce from 8 to make it look cleaner at larger size
        });

        // Optional: Add a subtle animation on hover
        $('.so-ssl-icon').closest('h1').hover(
            function () {
                // On mouse enter
                $('.so-ssl-icon').css({
                    'transform': 'scale(1.1)',
                    'transition': 'transform 0.3s ease'
                });
            },
            function () {
                // On mouse leave
                $('.so-ssl-icon').css({
                    'transform': 'scale(1)',
                    'transition': 'transform 0.3s ease'
                });
            }
        );
    });

})(jQuery);