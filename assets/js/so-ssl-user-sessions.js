(function($) {
    'use strict';

    $(document).ready(function() {
        // Terminate single session
        $('.so-ssl-terminate-session').on('click', function() {
            var token = $(this).data('token');
            var userId = $(this).data('user') || soSslUserSessions.userId;

            if (confirm(soSslUserSessions.terminateConfirm)) {
                $.ajax({
                    url: soSslUserSessions.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'so_ssl_terminate_session',
                        token: token,
                        user_id: userId,
                        nonce: soSslUserSessions.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            // If terminating current session, redirect to login
                            if (token === soSslUserSessions.currentSession) {
                                window.location.href = '/wp-login.php';
                            } else {
                                // Reload the page to refresh the sessions list
                                window.location.reload();
                            }
                        } else {
                            alert(response.data.message);
                        }
                    }
                });
            }
        });

        // Terminate all sessions for current user
        $('#so_ssl_terminate_all_sessions').on('click', function() {
            if (confirm(soSslUserSessions.terminateAllConfirm)) {
                $.ajax({
                    url: soSslUserSessions.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'so_ssl_terminate_all_sessions',
                        user_id: soSslUserSessions.userId,
                        nonce: soSslUserSessions.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            // Redirect to login page
                            window.location.href = '/wp-login.php';
                        } else {
                            alert(response.data.message);
                        }
                    }
                });
            }
        });

        // Terminate all other sessions for current user
        $('#so_ssl_terminate_other_sessions').on('click', function() {
            if (confirm(soSslUserSessions.terminateOthersConfirm)) {
                $.ajax({
                    url: soSslUserSessions.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'so_ssl_terminate_other_sessions',
                        user_id: soSslUserSessions.userId,
                        nonce: soSslUserSessions.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            // Reload the page to refresh the sessions list
                            window.location.reload();
                        } else {
                            alert(response.data.message);
                        }
                    }
                });
            }
        });

        // Terminate all sessions for specific user (admin page)
        $('.so-ssl-terminate-all-user-sessions').on('click', function() {
            var userId = $(this).data('user');

            if (confirm(soSslUserSessions.terminateAllConfirm)) {
                $.ajax({
                    url: soSslUserSessions.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'so_ssl_terminate_all_sessions',
                        user_id: userId,
                        nonce: soSslUserSessions.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            // If terminating current user's sessions, redirect to login
                            if (userId == soSslUserSessions.userId) {
                                window.location.href = '/wp-login.php';
                            } else {
                                // Reload the page to refresh the sessions list
                                window.location.reload();
                            }
                        } else {
                            alert(response.data.message);
                        }
                    }
                });
            }
        });
    });

})(jQuery);
