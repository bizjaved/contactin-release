<?php
/**
 * Warning Modal Template
 * 
 * Professional warning modal for destructive operations (prune/clear logs)
 * Centralizes warning modal functionality across all log pages
 * 
 * @package ContactIn\Admin\Templates
 */

if (!defined('ABSPATH')) exit;

use ContactInbox\Core\Config;
?>

<script>
jQuery(document).ready(function($) {
    'use strict';

    /**
     * Show Warning Modal for Prune Operations
     * @param {string} title - Modal title
     * @param {string} message - Main warning message
     * @param {object} props - Additional properties {syncedCount, days, type}
     * @param {function} onConfirm - Callback on confirmation
     */
    window.showLogWarningModal = function(config) {
        const {
            title = '⚠️ Destructive Action',
            message = 'This action will permanently delete logs.',
            syncedCount = 0,
            logType = 'logs',
            confirmText = 'Continue',
            confirmStyle = 'primary',
            onConfirm = () => {}
        } = config || {};

        const modalId = 'cin-warning-' + Math.random().toString(36).substr(2, 9);
        
        let warningBox = '';
        if (syncedCount > 0) {
            warningBox = `
                <div class="cin-analysis-warning" style="background-color: #fff3cd; border: 1px solid #ffc107; border-radius: 4px; padding: 12px; margin-top: 12px;">
                    <p style="margin: 0; color: #856404; font-size: 13px;">
                        <strong>⚠️ Note:</strong> This action affects your local audit trail but does not delete synced records from external systems. 
                        <strong>${syncedCount} record(s)</strong> may need separate deletion.
                    </p>
                </div>
            `;
        }

        const html = `
            <div id="${modalId}" class="cin-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="${modalId}-title">
                <div class="cin-confirm-modal">
                    <h3 id="${modalId}-title">${title}</h3>
                    <div class="cin-modal-analytics">
                        <p class="cin-modal-notice">${message}</p>
                        ${syncedCount > 0 ? `
                        <div class="cin-modal-analysis">
                            <div class="cin-analysis-item">
                                <span class="cin-analysis-label">Synced Records:</span>
                                <span class="cin-analysis-value" style="color: #d32f2f; font-weight: bold;">${syncedCount}</span>
                            </div>
                            ${warningBox}
                        </div>
                        ` : ''}
                    </div>
                    <div class="cin-modal-footer">
                        <button type="button" class="button cin-warning-cancel"><?php esc_html_e('Cancel',  'contactin'); ?></button>
                        <button type="button" class="button button-${confirmStyle === 'danger' ? 'danger' : confirmStyle} cin-warning-confirm">
                            ${confirmText}
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        const $modal = $(html);
        $('body').append($modal);
        $modal.addClass('active');
        
        $modal.find('.cin-warning-cancel').on('click', () => {
            $modal.fadeOut(200, () => $modal.remove());
        });
        
        $modal.find('.cin-warning-confirm').on('click', () => {
            $modal.fadeOut(200, () => {
                $modal.remove();
                onConfirm();
            });
        });
        
        // Close on backdrop click
        $modal.on('click', function(e) {
            if ($(e.target).is('#' + modalId)) {
                $modal.find('.cin-warning-cancel').click();
            }
        });

        return $modal;
    };

});
</script>
