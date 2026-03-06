/**
 * Maintenance Page JavaScript
 * Handles AJAX interactions and UI updates for the Maintenance & Operations page
 */

(function($) {
    'use strict';

    const cfg = window.ContactINMaintenance || {};
    const $message = $('#contactin-maint-message');

    /**
     * Display a message to the user
     * @param {string} msg - The message to display
     * @param {boolean} isError - Whether this is an error message
     */
    function showMessage(msg, isError) {
        $message
            .removeClass('notice-success notice-error')
            .addClass(isError ? 'notice notice-error' : 'notice notice-success is-dismissible');

        $message
            .html('<p>' + msg + '</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>')
            .show();

        $message.find('.notice-dismiss').on('click', function() {
            $message.hide();
        });

        // Scroll to message
        $('html, body').animate({
            scrollTop: $message.offset().top - 50
        }, 400);
    }

    /**
     * Disable a button and show processing state
     * @param {jQuery} $btn - The button element
     */
    function disableBtn($btn) {
        $btn.data('orig', $btn.text());
        $btn.prop('disabled', true).text(cfg.messages?.processing || 'Processing...');
    }

    /**
     * Restore a button to its original state
     * @param {jQuery} $btn - The button element
     */
    function restoreBtn($btn) {
        const orig = $btn.data('orig');
        $btn.prop('disabled', false).text(orig || 'Submit');
    }

    /**
     * Progress tracking state
     */
    const progressTimers = {};
    const progressState = {};

    /**
     * Get the progress scope for an action
     * @param {string} action - The action name
     * @returns {string|null} - The scope identifier or null
     */
    function getProgressScope(action) {
        if (action === 'contactin_maint_run_queue_email') {
            return 'email';
        }
        if (action === 'contactin_maint_run_queue_crm') {
            return 'crm';
        }
        return null;
    }

    /**
     * Update the progress bar UI
     * @param {string} scope - The progress scope
     * @param {object} data - The progress data
     */
    function updateProgressUI(scope, data) {
        const $container = $('.contactin-progress[data-progress-scope="' + scope + '"]');
        if (!$container.length) {
            return;
        }

        const $fill = $container.find('.contactin-progress-fill');
        const $text = $container.find('.contactin-progress-text');
        const $count = $container.find('[data-progress-count]');

        const pending = data?.pending_total ?? 0;
        const inProgress = !!data?.in_progress;
        const state = progressState[scope] || { total: null };

        // Initialize total on first update
        if (state.total === null) {
            state.total = pending;
        }

        // Update total if new pending count is higher
        if (pending > state.total) {
            state.total = pending;
        }

        // Calculate progress percentage
        const processed = Math.max(0, (state.total || 0) - pending);
        const percent = state.total > 0
            ? Math.min(100, Math.round((processed / state.total) * 100))
            : (pending === 0 ? 100 : 0);

        progressState[scope] = state;

        // Update UI elements
        $fill.css('width', percent + '%');
        $count.text('Pending: ' + pending + (state.total ? ' / ' + state.total : ''));

        // Update status text
        if (pending === 0 && !inProgress) {
            $text.text(cfg.messages?.progressDone || 'Processing complete.');
            setTimeout(function() {
                $container.removeClass('is-active');
            }, 3000);
        } else {
            $text.text(cfg.messages?.progressRunning || 'Processing in background...');
        }
    }

    /**
     * Stop the progress timer for a scope
     * @param {string} scope - The progress scope
     */
    function stopProgress(scope) {
        if (progressTimers[scope]) {
            clearInterval(progressTimers[scope]);
            delete progressTimers[scope];
        }

        const $container = $('.contactin-progress[data-progress-scope="' + scope + '"]');
        $container.removeClass('is-active');
    }

    /**
     * Start polling for progress updates
     * @param {string} scope - The progress scope
     */
    function startProgress(scope) {
        if (!scope || !cfg.progressNonce || !cfg.ajaxUrl) {
            return;
        }

        const $container = $('.contactin-progress[data-progress-scope="' + scope + '"]');
        $container.addClass('is-active');
        progressState[scope] = { total: null };

        const poll = function() {
            $.post(cfg.ajaxUrl, {
                action: 'contactin_maint_queue_progress',
                nonce: cfg.progressNonce
            }).done(function(resp) {
                if (!resp || !resp.success || !resp.data) {
                    return;
                }

                // Update email progress
                if (scope === 'email') {
                    updateProgressUI('email', resp.data.email);
                    if (resp.data.email?.pending_total === 0 && !resp.data.email?.in_progress) {
                        stopProgress('email');
                    }
                }

                // Update CRM progress (combined with attachments)
                if (scope === 'crm') {
                    const combinedCrm = Object.assign({}, resp.data.crm || {});
                    const attachmentPending = resp.data.attachments?.pending_total || 0;
                    combinedCrm.pending_total = (combinedCrm.pending_total || 0) + attachmentPending;
                    updateProgressUI('crm', combinedCrm);

                    if ((combinedCrm.pending_total || 0) === 0 && !resp.data.crm?.in_progress) {
                        stopProgress('crm');
                    }
                }
            });
        };

        stopProgress(scope);
        poll();
        progressTimers[scope] = setInterval(poll, cfg.progressPollMs || 2000);
    }

    /**
     * Get confirmation message for an action if needed
     * @param {string} action - The action name
     * @returns {string|null} - The confirmation message or null
     */
    function needsConfirm(action) {
        switch(action) {
            case 'contactin_maint_retry_dlq':
                return cfg.confirm?.retryDlq;
            case 'contactin_maint_retry_email_dlq':
                return cfg.confirm?.retryEmailDlq;
            case 'contactin_maint_retry_crm_dlq':
                return cfg.confirm?.retryCrmDlq;
            case 'contactin_maint_reset_circuits':
                return cfg.confirm?.resetCircuits;
            case 'contactin_maint_skip_email':
                return cfg.confirm?.skipEmail;
            default:
                return null;
        }
    }

    /**
     * Handle maintenance action button clicks
     */
    $('.js-maint-action').on('click', function() {
        const $btn = $(this);
        const action = $btn.data('action');
        const nonce = $btn.data('nonce');

        if (!action || !nonce) {
            return;
        }

        // Check for confirmation
        const confirmMsg = needsConfirm(action);
        if (confirmMsg && !window.confirm(confirmMsg)) {
            return;
        }

        const payload = { action, nonce };

        // Handle reschedule actions with delay prompt
        if (action === 'contactin_maint_reschedule_email_queue' || action === 'contactin_maint_reschedule_crm_queue') {
            const defaultDelay = parseInt($btn.data('delay-default'), 10) || cfg.defaults?.delaySeconds || 120;
            const promptTemplate = cfg.messages?.delayPrompt || 'Enter seconds until next run (default %s):';
            const promptMessage = promptTemplate.replace('%s', defaultDelay);
            const input = window.prompt(promptMessage, defaultDelay);

            if (input === null) {
                return;
            }

            const delaySeconds = parseInt(input, 10);
            const isInvalidDelay = (Number.isNaN ? Number.isNaN(delaySeconds) : isNaN(delaySeconds)) || delaySeconds <= 0;

            if (isInvalidDelay) {
                showMessage(cfg.messages?.invalidDelay || 'Please provide a valid number of seconds.', true);
                return;
            }

            payload.delay_seconds = delaySeconds;
        }

        disableBtn($btn);

        $.post(cfg.ajaxUrl || window.ajaxurl, payload)
            .done(function(resp) {
                if (resp && resp.success) {
                    let msg = cfg.messages?.actionCompleted || 'Action completed.';
                    if (resp.data && resp.data.message) {
                        msg = resp.data.message;
                    }

                    // Auto-reload for certain actions
                    const autoReloadActions = [
                        'contactin_maint_reschedule_email_queue',
                        'contactin_maint_reschedule_crm_queue',
                        'contactin_maint_retry_email_dlq',
                        'contactin_maint_retry_crm_dlq',
                        'contactin_maint_retry_dlq'
                    ];

                    const progressScope = getProgressScope(action);
                    if (progressScope) {
                        showMessage(msg);
                        startProgress(progressScope);
                        restoreBtn($btn);
                    } else if (autoReloadActions.includes(action)) {
                        showMessage(msg + ' Reloading...');
                        setTimeout(function() {
                            window.location.reload();
                        }, 1000);
                    } else {
                        msg += ' <a href="javascript:void(0)" onclick="window.location.reload()">Reload page</a>';
                        showMessage(msg);
                        restoreBtn($btn);
                    }
                } else {
                    showMessage((cfg.messages?.ajaxError || 'Error') + ' ' + (resp && resp.data ? resp.data : ''), true);
                    restoreBtn($btn);
                }
            })
            .fail(function() {
                showMessage(cfg.messages?.ajaxError || 'AJAX error occurred.', true);
                restoreBtn($btn);
            });
    });

    /**
     * Initialize on document ready
     */
    $(function() {
        // Handle dismiss buttons for inline notices
        $(document).on('click', '.notice.is-dismissible .notice-dismiss', function(e) {
            e.preventDefault();
            $(this).closest('.notice').fadeOut(300, function() {
                $(this).remove();
            });
        });

        // Load initial progress state
        if (!cfg.progressNonce || !cfg.ajaxUrl) {
            return;
        }

        $.post(cfg.ajaxUrl, {
            action: 'contactin_maint_queue_progress',
            nonce: cfg.progressNonce
        }).done(function(resp) {
            if (!resp || !resp.success || !resp.data) {
                return;
            }

            // Update initial progress UI
            updateProgressUI('email', resp.data.email);

            // Combine CRM and attachment pending
            const combinedCrm = Object.assign({}, resp.data.crm || {});
            combinedCrm.pending_total = (combinedCrm.pending_total || 0) + (resp.data.attachments?.pending_total || 0);
            updateProgressUI('crm', combinedCrm);

            // Start polling if needed
            if (resp.data.email?.in_progress || resp.data.email?.pending_total > 0) {
                startProgress('email');
            }
            if (resp.data.crm?.in_progress || combinedCrm.pending_total > 0) {
                startProgress('crm');
            }
        });
    });

    /**
     * Handle GDPR CRM queue deletion
     */
    $('.cin-gdpr-queue-delete-btn').on('click', function(e) {
        e.preventDefault();
        if (!cfg.ajaxUrl) {
            return;
        }

        const $btn = $(this);
        const nonce = $btn.data('nonce');

        const confirmMsg = cfg.gdprMessages?.confirmQueueDelete ||
            'Queue synced contacts for CRM deletion processing? This will be processed in the background.';

        if (!window.confirm(confirmMsg)) {
            return;
        }

        disableBtn($btn);

        $.post(cfg.ajaxUrl || window.ajaxurl, {
            action: 'contactin_maint_gdpr_queue_delete',
            nonce: nonce
        })
            .done(function(resp) {
                if (resp && resp.success) {
                    let msg = resp.data?.message || 'Contacts queued for deletion.';
                    showMessage(msg + ' <a href="javascript:void(0)" onclick="window.location.reload()">Reload page</a>');
                    restoreBtn($btn);
                } else {
                    showMessage((cfg.messages?.ajaxError || 'Error') + ' ' + (resp && resp.data ? resp.data : ''), true);
                    restoreBtn($btn);
                }
            })
            .fail(function() {
                showMessage(cfg.messages?.ajaxError || 'AJAX error occurred.', true);
                restoreBtn($btn);
            });
    });

    /**
     * Handle GDPR CRM immediate deletion
     */
    $('.cin-gdpr-immediate-delete-btn').on('click', function(e) {
        e.preventDefault();
        if (!cfg.ajaxUrl) {
            return;
        }

        const $btn = $(this);
        const nonce = $btn.data('nonce');

        const confirmMsg = cfg.gdprMessages?.confirmImmediateDelete ||
            'Delete synced contacts from CRM immediately? This action cannot be undone and will use the same sync logic as form submissions with fallback support.';

        if (!window.confirm(confirmMsg)) {
            return;
        }

        disableBtn($btn);

        $.post(cfg.ajaxUrl || window.ajaxurl, {
            action: 'contactin_maint_gdpr_immediate_delete',
            nonce: nonce
        })
            .done(function(resp) {
                if (resp && resp.success) {
                    let msg = resp.data?.message || 'Contacts deleted from CRM.';
                    showMessage(msg + ' Reloading...');
                    setTimeout(function() {
                        window.location.reload();
                    }, 1500);
                } else {
                    showMessage((cfg.messages?.ajaxError || 'Error') + ' ' + (resp && resp.data ? resp.data : ''), true);
                    restoreBtn($btn);
                }
            })
            .fail(function() {
                showMessage(cfg.messages?.ajaxError || 'AJAX error occurred.', true);
                restoreBtn($btn);
            });
    });

})(jQuery);

/**
 * Free Version - Disable maintenance action buttons
 * In the free version, advanced maintenance tools require Pro upgrade
 */
(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        const buttons = document.querySelectorAll('.js-maint-action');
        buttons.forEach(function(btn) {
            btn.setAttribute('disabled', 'disabled');
            btn.setAttribute('aria-disabled', 'true');
            btn.classList.add('contactin-disabled');
        });
    });
})();

/**
 * Intent Reclassification Handler
 * Handles manual intent reclassification for unclassified messages
 */
(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        const reclassifyBtn = document.getElementById('cin-reclassify-intent-btn');
        if (!reclassifyBtn) {
            return;
        }

        reclassifyBtn.addEventListener('click', function(e) {
            e.preventDefault();

            const nonce = reclassifyBtn.dataset.nonce;
            if (!nonce) {
                alert('Security nonce missing.');
                return;
            }

            // Show loading state
            const originalText = reclassifyBtn.textContent;
            reclassifyBtn.disabled = true;
            reclassifyBtn.textContent = 'Processing...';

            // Make AJAX request
            jQuery.post(
                window.ajaxurl,
                {
                    'action': 'contactin_maint_reclassify_intent',
                    'nonce': nonce
                },
                function(response) {
                    reclassifyBtn.disabled = false;
                    reclassifyBtn.textContent = originalText;

                    const resultDiv = document.getElementById('cin-reclassify-result');
                    if (resultDiv) {
                        // Clear previous classes
                        resultDiv.classList.remove('success', 'error');

                        // Add appropriate class
                        resultDiv.classList.add(response.success ? 'success' : 'error');

                        let details = response.data.message || 'Operation completed';
                        if (response.data.remaining > 0) {
                            const retryMsg = 'Run again to process next batch';
                            details += '<br><small>(' + retryMsg + ')</small>';
                        }
                        resultDiv.innerHTML = details;
                    }
                }
            ).fail(function() {
                reclassifyBtn.disabled = false;
                reclassifyBtn.textContent = originalText;
                alert('An error occurred while processing your request.');
            });
        });
    });
})();
