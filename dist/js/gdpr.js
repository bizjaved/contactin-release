jQuery(document).ready(function ($) {
    if (typeof window.cinGDPR === 'undefined') {
        console.error('ContactInbox: cinGDPR object not found. GDPR assets failed to load.');
        return;
    }

    // Central AJAX wrapper
    function cinAjax(action, data = {}, successCallback, errorCallback) {
        const params = new URLSearchParams();
        params.set('action', action);
        params.set('nonce', cinGDPR.nonce);

        Object.entries(data).forEach(([key, value]) => {
            if (value === null || value === undefined || value === '' || value === false) return;
            params.set(key, value);
        });

        fetch(cinGDPR.ajax_url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: params.toString()
        })
        .then(r => r.json())
        .then(res => {
            if (res && res.success) {
                if (typeof successCallback === 'function') successCallback(res);
            } else {
                const msg = (res && res.data && res.data.message) || 'Request failed';
                console.error('GDPR Error:', msg);
                $('#gdpr-status').text(msg).fadeIn();
                if (typeof errorCallback === 'function') errorCallback(res);
            }
        })
        .catch(err => {
            console.error('GDPR AJAX failed:', err);
            if (typeof errorCallback === 'function') errorCallback(err);
            $('#gdpr-status').text('Network error occurred').fadeIn();
        });
    }

    // GDPR trigger button (in inbox rows)
    $(document).on('click', '.contactin-gdpr', function (e) {
        e.preventDefault();
        const $btn  = $(this);
        const id    = $btn.data('id');
        const email = $btn.data('email');

        if (!id) {
            console.error('No message ID found');
            return;
        }
        
        // Show GDPR confirmation modal instead of browser alert
        showGdprConfirmation(id, email, $btn);
    });

    // Select the generated link for quick copy without inline handlers.
    $(document).on('click', '#gdpr-link-input', function () {
        this.select();
    });

    // GDPR Confirmation Modal
    function showGdprConfirmation(id, email, $btn) {
        var html = '<div id="cin-gdpr-confirm-modal" class="cin-modal-overlay">' +
            '<div class="cin-confirm-modal">' +
            '<h3>Generate GDPR Deletion Link?</h3>' +
            '<div class="cin-confirm-details">' +
            '<p><strong>Email:</strong> ' + escapeHtml(email || '—') + '</p>' +
            '<p style="margin-top: 12px; font-size: 12px; line-height: 1.6; color: #555;">' +
            'A unique deletion link will be generated and displayed. This link allows the user to delete their data. ' +
            'The link will expire after ' + (cinGDPR.expiry_days || '7') + ' days.' +
            '</p>' +
            '</div>' +
            '<div class="cin-confirm-actions">' +
            '<button type="button" class="button cin-confirm-cancel">Cancel</button>' +
            '<button type="button" class="button button-primary cin-btn-info cin-confirm-gdpr" data-id="' + id + '" data-email="' + escapeHtml(email) + '">Generate Link</button>' +
            '</div>' +
            '</div>' +
            '</div>';

        $('body').append(html);
        var $modal = $('#cin-gdpr-confirm-modal');
        setTimeout(function() {
            $modal.addClass('active');
            $modal.find('.cin-confirm-gdpr').focus();
        }, 10);

        $modal.on('click', '.cin-confirm-cancel', function() {
            $modal.removeClass('active');
            setTimeout(function() {
                $modal.remove();
            }, 200);
        });

        $modal.on('click', '.cin-confirm-gdpr', function() {
            var confirmId = $(this).data('id');
            var confirmEmail = $(this).data('email');
            $modal.removeClass('active');
            setTimeout(function() {
                $modal.remove();
                // Trigger actual GDPR generation
                proceedWithGdprGeneration(confirmId, confirmEmail, $btn);
            }, 200);
        });

        // Close on escape
        $(document).one('keyup', function(e) {
            if (e.key === 'Escape' && $modal.hasClass('active')) {
                $modal.removeClass('active');
                setTimeout(function() {
                    $modal.remove();
                }, 200);
            }
        });
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function proceedWithGdprGeneration(id, email, $btn) {
        $btn.addClass('loading');

        cinAjax('contactin_gdpr_link', { id, email, nonce: cinGDPR.nonce }, function (res) {
            const { link, email, expires, message_id, message } = res.data || {};
            const $modal = $('#cin-gdpr-modal');
            
            if (!$modal.length) {
                console.error('GDPR modal not found in DOM');
                return;
            }

            // Populate modal
            $modal.find('.gdpr-modal-info strong').text(email || cinGDPR.i18n.no_data);
            $('#gdpr-link-input').val(link || '');
            $('#gdpr-status').text(message || cinGDPR.i18n.generated);

            // Store message_id and link for send email button
            $('#gdpr-send-email-btn').data('message-id', message_id).data('link', link);

            // Expiry info
            if (expires) {
                $modal.find('.gdpr-expiry').text(expires);
            } else if (cinGDPR.expiry_days) {
                $modal.find('.gdpr-expiry').text(`${cinGDPR.expiry_days} days`);
            }

            // Copy button
            $('#gdpr-copy-btn')
                .text(cinGDPR.i18n.copy_btn)
                .off('click').on('click', function () {
                    const linkVal = $('#gdpr-link-input').val();

                    if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
                        navigator.clipboard.writeText(linkVal).then(() => {
                            $('#gdpr-status').text(cinGDPR.i18n.copy_status)
                                .fadeIn().delay(1500).fadeOut();
                        }).catch(err => {
                            console.error('Clipboard write blocked:', err);
                            $('#gdpr-status').text(cinGDPR.i18n.generated).fadeIn();
                        });
                    } else {
                        $('#gdpr-status').text('Clipboard not available in this context.')
                            .fadeIn().delay(2000).fadeOut();
                    }
                });

            // Show modal with animation
            $modal.addClass('active').show();
            
            // Remove loading state after modal appears
            setTimeout(function() {
                $btn.removeClass('loading');
            }, 300);
        });
    }

    // Handle Send Email button click
    $(document).on('click', '#gdpr-send-email-btn', function(e) {
        e.preventDefault();
        
        const $btn = $(this);
        const messageId = $btn.data('message-id');
        const link = $btn.data('link');
        
        if (!messageId || !link) {
            $('#gdpr-status').text('Error: Missing message ID or link').fadeIn();
            return;
        }

        // Show spinner, hide text
        $btn.prop('disabled', true);
        $btn.find('.btn-text').hide();
        $btn.find('.btn-spinner').show();

        cinAjax('contactin_gdpr_send_email', { 
            id: messageId, 
            link: link, 
            nonce: cinGDPR.nonce 
        }, function (res) {
            // Success
            $('#gdpr-status').text(res.data.message || 'Email is being sent...').fadeIn();
            
            // Update button to show success
            setTimeout(function() {
                $btn.find('.btn-spinner').hide();
                $btn.find('.btn-text').text('✓ Email Sent').show();
                $btn.removeClass('button-primary').addClass('button-secondary');
            }, 500);
            
            // Auto-close modal after 3 seconds
            setTimeout(function() {
                $('#cin-gdpr-modal').removeClass('active');
                setTimeout(function() {
                    $('#cin-gdpr-modal').hide();
                    // Reset button for next use
                    $btn.prop('disabled', false);
                    $btn.find('.btn-text').text('Send Email').show();
                    $btn.find('.btn-spinner').hide();
                    $btn.removeClass('button-secondary').addClass('button-primary');
                }, 300);
            }, 3000);
        }, function(err) {
            // Error
            const errorMsg = (err && err.data && err.data.message) || 'Failed to send email';
            $('#gdpr-status').text(errorMsg).fadeIn();
            
            // Reset button
            $btn.prop('disabled', false);
            $btn.find('.btn-spinner').hide();
            $btn.find('.btn-text').show();
        });
    });

    // Close modal when clicking outside
    $(document).on('click', function(e) {
        const $modal = $('#cin-gdpr-modal');
        const $wrap  = $modal.find('.cin-gdpr-wrap');
        if ($modal.is(':visible') && !$wrap.is(e.target) && $wrap.has(e.target).length === 0) {
            $modal.removeClass('active');
            setTimeout(function() {
                $modal.hide();
            }, 300);
        }
    });
});
