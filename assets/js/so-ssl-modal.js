jQuery(document).ready(function ($) {
    const modal = $('#sslAgreementModal');
    const acceptButton = $('#soSslAcceptButton');
    const body = $('body');

    // Function to show modal
    function showModal() {
        modal.show();
        body.addClass('modal-open');
    }

    // Function to hide modal
    function hideModal() {
        modal.hide();
        body.removeClass('modal-open');
    }

    // Show modal if agreement not accepted
    if (!localStorage.getItem('sslAgreementAccepted')) {
        showModal();
    }

    // Handle accept button click
    acceptButton.on('click', function () {
        // Save acceptance via AJAX
        $.ajax({
            url: soSslModal.ajaxurl,
            type: 'POST',
            data: {
                action: 'so_ssl_save_agreement',
                nonce: soSslModal.nonce
            },
            success: function (response) {
                if (response.success) {
                    localStorage.setItem('sslAgreementAccepted', 'true');
                    hideModal();
                } else {
                    alert('Error saving agreement acceptance. Please try again.');
                }
            },
            error: function () {
                alert('Error saving agreement acceptance. Please try again.');
            }
        });
    });

    // Prevent clicking through to background
    modal.on('click', function (event) {
        if (event.target === this) {
            event.stopPropagation();
        }
    });

    // Prevent keyboard navigation while modal is open
    $(document).on('keydown', function (event) {
        if (modal.is(':visible')) {
            if (event.key === 'Tab') {
                event.preventDefault();
            }
        }
    });
});