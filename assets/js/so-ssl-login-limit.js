(function($) {
    'use strict';

    $(document).ready(function() {
        // Whitelist an IP
        $('.so-ssl-whitelist-ip').on('click', function() {
            var ip = $(this).data('ip');

            if (confirm(soSslLoginLimit.whitelistConfirm)) {
                $.ajax({
                    url: soSslLoginLimit.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'so_ssl_whitelist_ip',
                        ip: ip,
                        nonce: soSslLoginLimit.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            alert(response.data.message);
                            window.location.reload();
                        } else {
                            alert(response.data.message);
                        }
                    }
                });
            }
        });

        // Blacklist an IP
        $('.so-ssl-blacklist-ip').on('click', function() {
            var ip = $(this).data('ip');

            if (confirm(soSslLoginLimit.blacklistConfirm)) {
                $.ajax({
                    url: soSslLoginLimit.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'so_ssl_blacklist_ip',
                        ip: ip,
                        nonce: soSslLoginLimit.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            alert(response.data.message);
                            window.location.reload();
                        } else {
                            alert(response.data.message);
                        }
                    }
                });
            }
        });

        // Reset attempts for an IP
        $('.so-ssl-reset-attempts').on('click', function() {
            var ip = $(this).data('ip');

            if (confirm(soSslLoginLimit.resetConfirm)) {
                $.ajax({
                    url: soSslLoginLimit.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'so_ssl_reset_attempts',
                        ip: ip,
                        nonce: soSslLoginLimit.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            alert(response.data.message);
                            window.location.reload();
                        } else {
                            alert(response.data.message);
                        }
                    }
                });
            }
        });

        // Remove from whitelist/blacklist
        $('.so-ssl-remove-from-list').on('click', function() {
            var ip = $(this).data('ip');
            var list = $(this).data('list');

            if (confirm(soSslLoginLimit.removeConfirm)) {
                $.ajax({
                    url: soSslLoginLimit.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'so_ssl_remove_from_list',
                        ip: ip,
                        list: list,
                        nonce: soSslLoginLimit.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            alert(response.data.message);
                            window.location.reload();
                        } else {
                            alert(response.data.message);
                        }
                    }
                });
            }
        });

        // Add to whitelist form
        $('#so-ssl-add-whitelist-form').on('submit', function(e) {
            e.preventDefault();

            var ip = $('#so-ssl-new-whitelist-ip').val();

            if (!ip) {
                alert('Please enter an IP address');
                return;
            }

            $.ajax({
                url: soSslLoginLimit.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'so_ssl_whitelist_ip',
                    ip: ip,
                    nonce: soSslLoginLimit.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        window.location.reload();
                    } else {
                        alert(response.data.message);
                    }
                }
            });
        });

        // Add to blacklist form
        $('#so-ssl-add-blacklist-form').on('submit', function(e) {
            e.preventDefault();

            var ip = $('#so-ssl-new-blacklist-ip').val();

            if (!ip) {
                alert('Please enter an IP address');
                return;
            }

            $.ajax({
                url: soSslLoginLimit.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'so_ssl_blacklist_ip',
                    ip: ip,
                    reason: 'Manually blacklisted',
                    nonce: soSslLoginLimit.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        window.location.reload();
                    } else {
                        alert(response.data.message);
                    }
                }
            });
        });
    });

})(jQuery);
