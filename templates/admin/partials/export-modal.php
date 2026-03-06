<?php
/**
 * Export Modal Template
 * 
 * Reusable modal for CSV export with batch/chunk size selection.
 * Used across inbox, spam, archived, and contact detail pages.
 * 
 * @package ContactInbox\Admin\Templates
 */

if (!defined('ABSPATH')) exit;

use ContactInbox\Core\Config;
use ContactInbox\Admin\Helpers\UpgradeModalHelper;

if ( defined('CONTACTINBOX_IS_FREE') && CONTACTINBOX_IS_FREE ) :
?>
    <div id="cin-export-modal" class="cin-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="cin-export-modal-title">
        <div class="cin-confirm-modal">
            <h3 id="cin-export-modal-title"><?php esc_html_e('Export Records', 'contact-inbox'); ?></h3>
            <p class="cin-export-meta"><?php esc_html_e('CSV export is available in Contact Inbox Pro.', 'contact-inbox'); ?></p>
            <div class="cin-export-footer">
                <button class="button button-secondary cin-export-close"><?php esc_html_e('Close', 'contact-inbox'); ?></button>
                <button type="button" class="button button-primary contactinbox-show-upgrade-modal">
                    <?php esc_html_e('Upgrade to Pro', 'contact-inbox'); ?>
                </button>
            </div>
        </div>
    </div>

    <script>
    jQuery(document).ready(function($) {
        $(document).on('click', '.cin-download-csv', function(e) {
            e.preventDefault();
            $('#contactinbox-upgrade-modal').fadeIn(200);
            $('body').addClass('contactinbox-modal-open');
        });

        $(document).on('click', '.cin-export-close', function() {
            $('#cin-export-modal').removeClass('active');
        });
    });
    </script>
<?php
    return;
endif;
?>

<!-- Export Modal -->
<div id="cin-export-modal" class="cin-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="cin-export-modal-title">
    <div class="cin-confirm-modal">
        <h3 id="cin-export-modal-title"><?php esc_html_e('Export Records', 'contact-inbox'); ?></h3>
        <p class="cin-export-meta">
            <?php esc_html_e('Total:', 'contact-inbox'); ?> <strong id="cin-export-total">0</strong> · 
            <?php esc_html_e('Max per file:', 'contact-inbox'); ?> <strong id="cin-export-max">1000</strong>
        </p>
        <div class="cin-export-row">
            <label for="cin-export-chunk"><?php esc_html_e('Records per file:', 'contact-inbox'); ?></label>
            <input type="number" id="cin-export-chunk" name="cin_export_chunk" min="1" max="1000" value="500" class="cin-export-chunk">
            <span class="cin-export-hint"><?php esc_html_e('(Max 1000)', 'contact-inbox'); ?></span>
        </div>
        <div id="cin-export-links" class="cin-export-links"></div>
        <div class="cin-export-footer">
            <button class="button button-secondary cin-export-close"><?php esc_html_e('Close', 'contact-inbox'); ?></button>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    var exportModal = {
        ajaxUrl: window.ajaxUrl || (typeof ContactINRestLog !== 'undefined' ? ContactINRestLog.ajax_url : (typeof contactinCrmLog !== 'undefined' ? contactinCrmLog.ajaxUrl : '')),
        totalItems: 0,
        currentFilters: {},
        
        fetchExportInfo: function(button) {
            var self = this;
            var ajaxAction = button.data('ajax-action');
            var infoAction = button.data('export-info-action') || (ajaxAction ? ajaxAction.replace('download', 'export_info') : '');
            
            // Collect filter data from button
            var filterData = {
                action: infoAction,
                _ajax_nonce: button.data('nonce') || '',
                nonce: button.data('nonce') || '',
                status: button.data('status') || 'all',
                operation: button.data('operation') || 'all',
                method: button.data('http-method') || 'all',
                http_method: button.data('http-method') || 'all',
                endpoint: button.data('endpoint') || 'all',
                http_code: button.data('http-code') || 'all',
                validated: button.data('validated') || 'all',
            };
            
            self.currentFilters = filterData;
            
            $.ajax({
                url: self.ajaxUrl,
                type: 'POST',
                data: filterData,
                success: function(response) {
                    if (response.success && response.data) {
                        $('#cin-export-total').text(response.data.total || 0);
                        $('#cin-export-max').text(response.data.limit || 1000);
                        self.totalItems = response.data.total || 0;
                        
                        // Auto-generate download links
                        $('#cin-export-chunk').trigger('change');
                    }
                },
                error: function() {
                    $('#cin-export-total').text('0');
                }
            });
        }
    };
    
    // Modal close handlers
    $(document).on('click', '.cin-export-close', function() {
        $('#cin-export-modal').removeClass('active');
    });

    $(document).on('click', '#cin-export-modal', function(e) {
        if ($(e.target).is('#cin-export-modal')) {
            $(this).removeClass('active');
        }
    });

    // Handle chunk size change
    $('#cin-export-chunk').on('change', function() {
        var button = $('.cin-download-csv');
        var chunkSize = parseInt($(this).val()) || 500;
        var ajaxAction = button.data('ajax-action');
        
        if (!ajaxAction || exportModal.totalItems === 0) return;

        var totalBatches = Math.max(1, Math.ceil(exportModal.totalItems / chunkSize));
        var links = [];
        
        for (var i = 0; i < totalBatches; i++) {
            var start = i * chunkSize + 1;
            var end = Math.min(exportModal.totalItems, (i + 1) * chunkSize);
            
            // Build AJAX download URL
            var params = new URLSearchParams();
            params.append('action', ajaxAction);
            params.append('batch', i + 1);
            params.append('limit', chunkSize);
            params.append('total_batches', totalBatches);
            params.append('_ajax_nonce', button.data('nonce') || '');
            params.append('nonce', button.data('nonce') || '');
            
            // Add filter parameters
            ['status', 'operation', 'http_method', 'endpoint', 'http_code', 'validated'].forEach(function(key) {
                var value = button.data(key === 'http_method' || key === 'http_code' ? key.replace(/_/g, '-') : key.replace(/_/g, '-'));
                if (value && value !== 'all') {
                    var paramName = key === 'http_method' ? 'method' : key;
                    params.append(paramName, value);
                }
            });
            
            var url = exportModal.ajaxUrl + '?' + params.toString();
            links.push('<div><a class="cin-export-link" href="' + encodeURI(url) + '" download>Download ' + start + '–' + end + '</a></div>');
        }
        
        $('#cin-export-links').html(links.join(''));
    });

    // Trigger export info fetch when export button is clicked
    $(document).on('click', '.cin-download-csv', function(e) {
        e.preventDefault();
        var button = $(this);
        
        // Show modal
        $('#cin-export-modal').addClass('active');
        $('#cin-export-total').text('Loading...');
        $('#cin-export-links').html('');
        
        // Fetch export info
        exportModal.fetchExportInfo(button);
    });
});
</script>
