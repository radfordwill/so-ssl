/**
 * So SSL Admin JavaScript
 *
 * Balanced form change detection that properly tracks changes without false positives
 */

(function($) {
    'use strict';

    // Flag to track if form has been modified
    let formModified = false;
    let isFormSubmitting = false;

    // Create an array to store initial form element values
    let originalFormValues = [];

    // Flag to indicate if initial state has been captured
    let initialStateCaptured = false;

    // Function to store the initial state of the form
    function storeOriginalFormValues() {
        originalFormValues = [];
        $('form:first').find('input, select, textarea').each(function() {
            const $el = $(this);
            const id = $el.attr('id') || '';
            const name = $el.attr('name') || '';

            // Skip elements without ID/name or submit buttons
            if ((!id && !name) || $el.attr('type') === 'submit') {
                return;
            }

            // Use either ID or name as identifier
            const identifier = id || name;

            let value;
            if ($el.is(':checkbox') || $el.is(':radio')) {
                value = $el.is(':checked');
            } else {
                value = $el.val();
            }

            originalFormValues.push({
                identifier: identifier,
                value: value
            });
        });

        initialStateCaptured = true;
        //console.log('Initial form state captured with ' + originalFormValues.length + ' fields');
    }

    // Function to check if the form has been modified
    function isFormModified() {
        // If initial state hasn't been captured yet, form is not modified
        if (!initialStateCaptured) {
            return false;
        }

        let modifiedFields = [];

        // Check each original value against current value
        for (let i = 0; i < originalFormValues.length; i++) {
            const item = originalFormValues[i];
            const $el = $('#' + item.identifier);

            // If element not found by ID, try by name
            let $element = $el.length ? $el : $('[name="' + item.identifier + '"]');

            // Skip if element no longer exists
            if ($element.length === 0) {
                continue;
            }

            let currentValue;
            if ($element.is(':checkbox') || $element.is(':radio')) {
                // For radio buttons, we need special handling
                if ($element.is(':radio')) {
                    // Find the checked radio in the group
                    currentValue = $('[name="' + item.identifier + '"]:checked').val() || '';
                } else {
                    currentValue = $element.is(':checked');
                }
            } else {
                currentValue = $element.val();
            }

            // Convert to string for comparison if not boolean
            if (typeof currentValue !== 'boolean') {
                currentValue = String(currentValue || '');
            }
            if (typeof item.value !== 'boolean') {
                item.value = String(item.value || '');
            }

            // If the value has changed, form is modified
            if (currentValue !== item.value) {
                modifiedFields.push(item.identifier);
            }
        }

        if (modifiedFields.length > 0) {
            //console.log('Modified fields detected:', modifiedFields);
            return true;
        }

        // No changes found
        return false;
    }

    // Function to update form modified state
    function updateFormModifiedState() {
        const wasModified = formModified;
        formModified = isFormModified();

        // Only update UI if state has changed
        if (wasModified !== formModified) {
            highlightSubmitButton();
        }

        return formModified;
    }

    // Initialize tooltips if available
    function initializeTooltips() {
        if (typeof $.fn.tooltip !== 'undefined') {
            $('.so-ssl-tooltip').tooltip();
        }
    }

    // Toggle sections based on checkbox state
    function toggleSections() {
        // HSTS fields
        $('#so_ssl_enable_hsts').on('change', function() {
            const $hstsFields = $('.so-ssl-hsts-field').closest('tr');
            if ($(this).is(':checked')) {
                $hstsFields.fadeIn(300);
            } else {
                $hstsFields.fadeOut(200);
            }
            updateFormModifiedState();
        });

        // X-Frame-Options fields
        $('#so_ssl_enable_xframe').on('change', function() {
            const $xframeFields = $('.so-ssl-xframe-field').closest('tr');
            if ($(this).is(':checked')) {
                $xframeFields.fadeIn(300);
            } else {
                $xframeFields.fadeOut(200);
            }
            updateFormModifiedState();
        });

        // CSP Frame-Ancestors fields
        $('#so_ssl_enable_csp_frame_ancestors').on('change', function() {
            const $cspFields = $('.so-ssl-csp-field').closest('tr');
            if ($(this).is(':checked')) {
                $cspFields.fadeIn(300);
            } else {
                $cspFields.fadeOut(200);
            }
            updateFormModifiedState();
        });

        // Content Security Policy fields
        $('#so_ssl_enable_csp').on('change', function() {
            const $cspFields = $('.so-ssl-csp-directive-field').closest('tr');
            const $cspMode = $('#so_ssl_csp_mode').closest('tr');
            if ($(this).is(':checked')) {
                $cspMode.fadeIn(300);
                $cspFields.fadeIn(300);
            } else {
                $cspMode.fadeOut(200);
                $cspFields.fadeOut(200);
            }
            updateFormModifiedState();
        });

        // Permissions Policy fields
        $('#so_ssl_enable_permissions_policy').on('change', function() {
            const $permissionsFields = $('.so-ssl-permissions-policy-field').closest('tr');
            if ($(this).is(':checked')) {
                $permissionsFields.fadeIn(300);
            } else {
                $permissionsFields.fadeOut(200);
            }
            updateFormModifiedState();
        });

        // Referrer Policy fields
        $('#so_ssl_enable_referrer_policy').on('change', function() {
            const $referrerFields = $('.so-ssl-referrer-field').closest('tr');
            if ($(this).is(':checked')) {
                $referrerFields.fadeIn(300);
            } else {
                $referrerFields.fadeOut(200);
            }
            updateFormModifiedState();
        });

        // Two-Factor Authentication fields
        $('#so_ssl_enable_2fa').on('change', function() {
            const $twoFactorFields = $('.so-ssl-2fa-field').closest('tr');
            if ($(this).is(':checked')) {
                $twoFactorFields.fadeIn(300);
            } else {
                $twoFactorFields.fadeOut(200);
            }
            updateFormModifiedState();
        });

        // User Sessions fields
        $('#so_ssl_enable_user_sessions').on('change', function() {
            const $sessionsFields = $('.so-ssl-sessions-field').closest('tr');
            if ($(this).is(':checked')) {
                $sessionsFields.fadeIn(300);
            } else {
                $sessionsFields.fadeOut(200);
            }
            updateFormModifiedState();
        });

        // Login Limiting fields
        $('#so_ssl_enable_login_limit').on('change', function() {
            const $loginLimitFields = $('.so-ssl-login-limit-field').closest('tr');
            if ($(this).is(':checked')) {
                $loginLimitFields.fadeIn(300);
            } else {
                $loginLimitFields.fadeOut(200);
            }
            updateFormModifiedState();
        });
    }

    // Dynamic CSP field handling
    function handleCSPFields() {
        // CSP frame ancestors option toggle
        $('#so_ssl_csp_frame_ancestors_option').on('change', function() {
            const value = $(this).val();
            const $customFields = $('.so-ssl-csp-custom-field').closest('tr');

            if (value === 'custom') {
                $customFields.fadeIn(300);
            } else {
                $customFields.fadeOut(200);
            }
            updateFormModifiedState();
        });
    }

    // X-Frame-Options field handling
    function handleXFrameFields() {
        // X-Frame-Options toggle
        $('#so_ssl_xframe_option').on('change', function() {
            const value = $(this).val();
            const $allowFromField = $('.so_ssl_allow_from_field').closest('tr');

            if (value === 'allowfrom') {
                $allowFromField.fadeIn(300);
            } else {
                $allowFromField.fadeOut(200);
            }
            updateFormModifiedState();
        });
    }

    // Copy backup codes to clipboard
    function handleBackupCodes() {
        // Copy backup code when clicked
        $(document).on('click', '.so-ssl-backup-codes li', function() {
            const code = $(this).text();
            navigator.clipboard.writeText(code).then(function() {
                const $element = $(this);
                const originalText = $element.text();

                $element.text('Copied!');

                setTimeout(function() {
                    $element.text(originalText);
                }, 1000);
            }.bind(this));
        });
    }

    // Format verification code input
    function formatVerificationCode() {
        $(document).on('input', '#so_ssl_verify_code, #so_ssl_2fa_code', function() {
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
            updateFormModifiedState();
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
        $('.nav-tab').on('click', function(e) {
            e.preventDefault();

            // Get target tab
            const tabId = $(this).attr('href').substring(1);

            // If already on this tab, do nothing
            if ($(this).hasClass('nav-tab-active')) {
                return;
            }

            // If form is currently submitting, allow navigation without warning
            if (isFormSubmitting) {
                proceedWithTabChange($(this), tabId);
                return;
            }

            // Check if form is actually modified
            const formHasChanges = updateFormModifiedState();

            // Only show warning if there are actual changes
            if (formHasChanges) {
                if (!confirm(soSslAdmin.unsavedChangesWarning)) {
                    return false;
                }
            }

            proceedWithTabChange($(this), tabId);
        });

        // Helper function to handle tab changing
        function proceedWithTabChange($tab, tabId) {
            // Remove active class from all tabs
            $('.nav-tab').removeClass('nav-tab-active');
            $('.settings-tab').removeClass('active');

            // Add active class to clicked tab
            $tab.addClass('nav-tab-active');
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
        }

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

            // Activate the tab without triggering change detection
            const $tab = $('.nav-tab[href="#' + activeTab + '"]');

            // Remove active class from all tabs
            $('.nav-tab').removeClass('nav-tab-active');
            $('.settings-tab').removeClass('active');

            // Add active class to target tab
            $tab.addClass('nav-tab-active');

            // Show corresponding tab content
            $('#' + activeTab).addClass('active');

            // Update hidden input
            $('#active_tab').val(activeTab);
        }

        // Initialize active tab
        initActiveTab();
    }

    /**
     * Function to highlight the submit button when form has unsaved changes
     */
    function highlightSubmitButton() {
        if (formModified) {
            // Add pulsing effect to submit button
            $('#submit').addClass('so-ssl-save-highlight');

            // Show a reminder message near the submit button if it doesn't exist yet
            if ($('.so-ssl-save-reminder').length === 0) {
                $('#submit').after('<span class="so-ssl-save-reminder">Don\'t forget to save your changes!</span>');
            }
        } else {
            // Remove highlighting and reminder if form is not modified
            $('#submit').removeClass('so-ssl-save-highlight');
            $('.so-ssl-save-reminder').remove();
        }
    }

    /**
     * Updated function to properly convert checkboxes to toggle switches
     */
    function convertCheckboxesToSwitches() {
        $('input[type="checkbox"]').each(function() {
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
            $checkbox.on('change', function() {
                if ($(this).is(':checked')) {
                    $slider.addClass('checked');
                } else {
                    $slider.removeClass('checked');
                }
                updateFormModifiedState();
            });
        });

        // Hide all converted checkboxes with CSS
        $('head').append('<style>.so-ssl-switch-converted { position: absolute !important; left: -9999px !important; opacity: 0 !important; }</style>');
    }

    // Function to setup initial UI state without triggering changes
    function setupInitialUI() {
        // Hide/show fields based on current checkbox values WITHOUT triggering change events

        // HSTS fields
        const $hstsFields = $('.so-ssl-hsts-field').closest('tr');
        if (!$('#so_ssl_enable_hsts').is(':checked')) {
            $hstsFields.hide();
        }

        // X-Frame-Options fields
        const $xframeFields = $('.so-ssl-xframe-field').closest('tr');
        if (!$('#so_ssl_enable_xframe').is(':checked')) {
            $xframeFields.hide();
        }

        // CSP Frame-Ancestors fields
        const $cspFields = $('.so-ssl-csp-field').closest('tr');
        if (!$('#so_ssl_enable_csp_frame_ancestors').is(':checked')) {
            $cspFields.hide();
        }

        // Content Security Policy fields
        const $cspDirectiveFields = $('.so-ssl-csp-directive-field').closest('tr');
        const $cspMode = $('#so_ssl_csp_mode').closest('tr');
        if (!$('#so_ssl_enable_csp').is(':checked')) {
            $cspMode.hide();
            $cspDirectiveFields.hide();
        }

        // Permissions Policy fields
        const $permissionsFields = $('.so-ssl-permissions-policy-field').closest('tr');
        if (!$('#so_ssl_enable_permissions_policy').is(':checked')) {
            $permissionsFields.hide();
        }

        // Referrer Policy fields
        const $referrerFields = $('.so-ssl-referrer-field').closest('tr');
        if (!$('#so_ssl_enable_referrer_policy').is(':checked')) {
            $referrerFields.hide();
        }

        // Two-Factor Authentication fields
        const $twoFactorFields = $('.so-ssl-2fa-field').closest('tr');
        if (!$('#so_ssl_enable_2fa').is(':checked')) {
            $twoFactorFields.hide();
        }

        // User Sessions fields
        const $sessionsFields = $('.so-ssl-sessions-field').closest('tr');
        if (!$('#so_ssl_enable_user_sessions').is(':checked')) {
            $sessionsFields.hide();
        }

        // Login Limiting fields
        const $loginLimitFields = $('.so-ssl-login-limit-field').closest('tr');
        if (!$('#so_ssl_enable_login_limit').is(':checked')) {
            $loginLimitFields.hide();
        }

        // Handle select field dependent UI

        // CSP frame ancestors option toggle
        const $customFields = $('.so-ssl-csp-custom-field').closest('tr');
        if ($('#so_ssl_csp_frame_ancestors_option').val() !== 'custom') {
            $customFields.hide();
        }

        // X-Frame-Options toggle
        const $allowFromField = $('.so_ssl_allow_from_field').closest('tr');
        if ($('#so_ssl_xframe_option').val() !== 'allowfrom') {
            $allowFromField.hide();
        }
    }

    // Handle form submission
    // Handle form submission - updated version to prevent immediate warnings
    function handleFormSubmission() {
        $('form').on('submit', function() {
            // Set the submitting flag to true
            isFormSubmitting = true;

            // Clear the formModified flag since we're saving
            formModified = false;

            // Remove highlighting and reminder
            $('#submit').removeClass('so-ssl-save-highlight');
            $('.so-ssl-save-reminder').remove();

            //console.log('Form is being submitted, warnings disabled');

            // Clear the flag after 3 seconds (in case submission fails)
            setTimeout(function() {
                if (isFormSubmitting) {
                    isFormSubmitting = false;
                    storeOriginalFormValues();
                }
            }, 3000);

            return true;
        });
    }

    // Track form changes
    function trackFormChanges() {
        // Track all input changes
        $('form:first').on('change', 'input, select, textarea', function() {
            updateFormModifiedState();
        });

        // Track typing in text inputs
        $('form:first').on('input', 'input[type="text"], input[type="number"], textarea', function() {
            updateFormModifiedState();
        });
    }

    // Initialize when document is ready
    $(document).ready(function() {
        // Set formModified to false initially
        formModified = false;

        // Initialize tooltips
        initializeTooltips();

        // Handle initial UI setup without triggering changes
        setupInitialUI();

        // Convert checkboxes to toggle switches (visual only, doesn't change values)
        convertCheckboxesToSwitches();

        // Handle tabs (should come after UI setup)
        handleTabs();

        // Initialize toggle sections (add event handlers)
        toggleSections();

        // Handle CSP fields
        handleCSPFields();

        // Handle X-Frame-Options fields
        handleXFrameFields();

        // Handle backup codes
        handleBackupCodes();

        // Format verification code input
        formatVerificationCode();

        // Calculate security score (visual only)
        calculateSecurityScore();

        // Handle form submission
        handleFormSubmission();

        // Track form changes (add event handlers)
        trackFormChanges();

        // Capture initial form state AFTER all UI is setup
        // This ensures we don't detect false changes
        setTimeout(function() {
            storeOriginalFormValues();
        }, 500);
    });

    jQuery(document).ready(function($) {
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
            function() {
                // On mouse enter
                $('.so-ssl-icon').css({
                    'transform': 'scale(1.1)',
                    'transition': 'transform 0.3s ease'
                });
            },
            function() {
                // On mouse leave
                $('.so-ssl-icon').css({
                    'transform': 'scale(1)',
                    'transition': 'transform 0.3s ease'
                });
            }
        );
    });

})(jQuery);