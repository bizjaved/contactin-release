/**
 * ContactIn – Admin Settings: Attachment/REST API Dependency Logic
 * Modern, modular, safe. No inline JS/styles.
 */
(function ($) {
  'use strict';

  function showNotice($el, message, type) {
    // Add close button if not present
    if ($el.find('.cin-notice-close').length === 0) {
      var $close = $('<button type="button" class="cin-notice-close" aria-label="Close">&times;</button>');
      $close.on('click', function() { $el.fadeOut(200); });
      $el.prepend($close);
    }
    $el.find('.cin-notice-close').show();
    $el.find('.cin-notice-message').remove();
    $el.append($('<span class="cin-notice-message"></span>').text(message));
    $el.removeClass('success error info').addClass(type).show();
  }
  function hideNotice($el) {
    $el.hide().find('.cin-notice-message').remove();
    $el.removeClass('success error info');
    $el.find('.cin-notice-close').hide();
  }

  $(function () {
    var $attachment = $('#form-enable-attachment-checkbox');
    var $restapi = $('#restapi-enable-checkbox');
    var $attachmentNotice = $('#cin-attachment-restapi-notice');
    var $restapiNotice = $('#cin-restapi-attachment-notice');

    // On load, check state
    function checkAttachmentRestApiDependency() {
      if ($attachment.prop('checked') && !$restapi.prop('checked')) {
        // File upload enabled, REST API disabled: auto-enable REST API and show notice
        $restapi.prop('checked', true);
        showNotice($attachmentNotice, 'File upload requires REST API. REST API has been enabled automatically.', 'info');
        showNotice($restapiNotice, 'REST API is required for file uploads. It has been enabled automatically.', 'info');
      } else {
        hideNotice($attachmentNotice);
        hideNotice($restapiNotice);
      }
    }

    $attachment.on('change', function () {
      if ($attachment.prop('checked')) {
        if (!$restapi.prop('checked')) {
          $restapi.prop('checked', true);
          showNotice($attachmentNotice, 'File upload requires REST API. REST API has been enabled automatically.', 'info');
          showNotice($restapiNotice, 'REST API is required for file uploads. It has been enabled automatically.', 'info');
        }
      } else {
        hideNotice($attachmentNotice);
        // If user disables file upload, offer to disable REST API if not used elsewhere
        if ($restapi.prop('checked')) {
          // Use Yes/No dialog
          var dialog = $('<div class="cin-modal-dialog" style="z-index:99999;position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,0.25);display:flex;align-items:center;justify-content:center;"><div style="background:#fff;padding:32px 28px 24px 28px;border-radius:10px;box-shadow:0 8px 32px rgba(0,0,0,0.18);max-width:420px;width:90vw;text-align:center;"><div style="font-size:17px;font-weight:600;margin-bottom:18px;">You have disabled file upload.<br>Do you also want to disable the REST API?<br><span style=\"font-size:13px;font-weight:400;color:#666;display:block;margin-top:8px;\">It may be required by other integrations or apps.</span></div><div style=\"margin-top:18px;display:flex;gap:18px;justify-content:center;\"><button class=\"button button-primary cin-modal-yes\" style=\"min-width:70px;\">Yes</button><button class=\"button button-secondary cin-modal-no\" style=\"min-width:70px;\">No</button></div></div></div>');
          $('body').append(dialog);
          dialog.find('.cin-modal-yes').on('click', function() {
            $restapi.prop('checked', false);
            dialog.remove();
            // Submit the form with REST API disabled
            $attachment.closest('form').trigger('submit');
          });
          dialog.find('.cin-modal-no').on('click', function() {
            // Leave REST API enabled, defer success message until after save
            window.cinRestApiShowSuccessAfterSave = true;
            dialog.remove();
            // Submit the form with REST API enabled
            $attachment.closest('form').trigger('submit');
          });
          // Listen for custom event after settings save
          $(document).on('cin:settings:saved', function(e, data) {
            if (window.cinRestApiShowSuccessAfterSave) {
              var $globalNotice = $('#cin-global-settings-notice');
              if ($globalNotice.length) {
                showNotice($globalNotice, 'REST API remains enabled for integrations and apps. File upload is disabled.', 'success');
              }
              window.cinRestApiShowSuccessAfterSave = false;
            }
          });
        }
      }
    });

    $restapi.on('change', function () {
      if (!$restapi.prop('checked') && $attachment.prop('checked')) {
        // Prevent disabling REST API if file upload is enabled
        $restapi.prop('checked', true);
        showNotice($restapiNotice, 'REST API is required for file uploads. Please disable file upload first.', 'error');
      } else {
        hideNotice($restapiNotice);
      }
    });

    // Initial check
    checkAttachmentRestApiDependency();
  });
})(jQuery);
