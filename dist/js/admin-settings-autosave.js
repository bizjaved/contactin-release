// Dependency popup/modal logic for REST API, File Attachment, and Subject field
function showDependencyDialog(message, onYes, onNo) {
	var dialog = $( '<div class="cin-modal-dialog" style="z-index:99999;position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,0.25);display:flex;align-items:center;justify-content:center;"><div style="background:#fff;padding:32px 28px 24px 28px;border-radius:10px;box-shadow:0 8px 32px rgba(0,0,0,0.18);max-width:420px;width:90vw;text-align:center;"><div style="font-size:17px;font-weight:600;margin-bottom:18px;">' + message + '</div><div style="margin-top:18px;display:flex;gap:18px;justify-content:center;"><button class="button button-primary cin-modal-yes" style="min-width:70px;">Yes</button><button class="button button-secondary cin-modal-no" style="min-width:70px;">No</button></div></div></div>' );
	$( document.body ).append( dialog );
	dialog.find( '.cin-modal-yes' ).on(
		'click',
		function () {
			dialog.remove();
			if (typeof onYes === 'function') {
				onYes();
			}
		}
	);
	dialog.find( '.cin-modal-no' ).on(
		'click',
		function () {
			dialog.remove();
			if (typeof onNo === 'function') {
				onNo();
			}
		}
	);
}

			// Get REST API state from hidden input (if present)
function isRestApiEnabled() {
	var $hidden = $( "input[name='restapi_enable']" );
	return $hidden.length && ($hidden.val() === '1' || $hidden.val() === 1);
}

			// File Attachment button dependency logic
if ($attachmentBtn.length && $attachmentHidden.length) {
	$attachmentBtn.off( 'click' ).on(
		'click',
		function () {
			var enabled    = $attachmentBtn.data( 'enabled' ) === 1 || $attachmentBtn.data( 'enabled' ) === '1';
			var newEnabled = ! enabled;
			// If enabling file attachment and REST API is disabled, show popup
			if (newEnabled && ! isRestApiEnabled()) {
				showDependencyDialog(
					'File attachment requires the REST API service. Enable REST API as well?',
					function () {
						// User chose Yes: enable both
						$attachmentBtn.data( 'enabled', 1 ).addClass( 'enabled' ).text( 'Disable File Attachment' );
						$attachmentHidden.val( '1' );
						$( "input[name='restapi_enable']" ).val( '1' );
						$attachmentBtn.blur();
						$attachmentBtn.prop( 'disabled', true ).text( 'Saving...' );
						$attachmentBtn.closest( 'form' ).trigger( 'submit' );
					},
					function () {
						// User chose No: do nothing
					}
				);
				return;
			}
			$attachmentBtn.data( 'enabled', newEnabled ? 1 : 0 )
			.toggleClass( 'enabled', newEnabled )
			.text( newEnabled ? 'Disable File Attachment' : 'Enable File Attachment' );
			$attachmentHidden.val( newEnabled ? '1' : '0' );
			$attachmentBtn.blur();
			$attachmentBtn.prop( 'disabled', true );
			$attachmentBtn.text( newEnabled ? 'Saving...' : 'Saving...' );
			$attachmentBtn.closest( 'form' ).trigger( 'submit' );
		}
	);
}

			// Subject field button dependency logic
if ($subjectBtn.length && $subjectHidden.length) {
	$subjectBtn.off( 'click' ).on(
		'click',
		function () {
			var enabled    = $subjectBtn.data( 'enabled' ) === 1 || $subjectBtn.data( 'enabled' ) === '1';
			var newEnabled = ! enabled;
			// If disabling subject field and REST API is enabled, show popup
			if ( ! newEnabled && isRestApiEnabled()) {
				showDependencyDialog(
					'Disabling the subject field while REST API is enabled may affect integrations. Proceed?',
					function () {
						// User chose Yes: disable subject field
						$subjectBtn.data( 'enabled', 0 ).removeClass( 'enabled' ).text( 'Enable Subject Field' );
						$subjectHidden.val( '0' );
						$subjectBtn.blur();
						$subjectBtn.prop( 'disabled', true ).text( 'Saving...' );
						$subjectBtn.closest( 'form' ).trigger( 'submit' );
					},
					function () {
						// User chose No: do nothing
					}
				);
				return;
			}
			$subjectBtn.data( 'enabled', newEnabled ? 1 : 0 )
			.toggleClass( 'enabled', newEnabled )
			.text( newEnabled ? 'Disable Subject Field' : 'Enable Subject Field' );
			$subjectHidden.val( newEnabled ? '1' : '0' );
			$subjectBtn.blur();
			$subjectBtn.prop( 'disabled', true );
			$subjectBtn.text( newEnabled ? 'Saving...' : 'Saving...' );
			$subjectBtn.closest( 'form' ).trigger( 'submit' );
		}
	);
}
		// File Attachment enable/disable button logic
		var $attachmentBtn    = $( '#form-enable-attachment-btn' );
		var $attachmentHidden = $( '#form-enable-attachment-hidden' );
if ($attachmentBtn.length && $attachmentHidden.length) {
	$attachmentBtn.on(
		'click',
		function () {
			var enabled    = $attachmentBtn.data( 'enabled' ) === 1 || $attachmentBtn.data( 'enabled' ) === '1';
			var newEnabled = ! enabled;
			$attachmentBtn.data( 'enabled', newEnabled ? 1 : 0 )
			.toggleClass( 'enabled', newEnabled )
			.text( newEnabled ? 'Disable File Attachment' : 'Enable File Attachment' );
			$attachmentHidden.val( newEnabled ? '1' : '0' );
			$attachmentBtn.blur();
			$attachmentBtn.prop( 'disabled', true );
			$attachmentBtn.text( newEnabled ? 'Saving...' : 'Saving...' );
			$attachmentBtn.closest( 'form' ).trigger( 'submit' );
		}
	);
	// Re-enable button after save (listen for custom event)
	$( document ).on(
		'cin:settings:saved',
		function () {
			$attachmentBtn.prop( 'disabled', false );
			var enabled = $attachmentBtn.data( 'enabled' ) === 1 || $attachmentBtn.data( 'enabled' ) === '1';
			$attachmentBtn.text( enabled ? 'Disable File Attachment' : 'Enable File Attachment' );
		}
	);
}
	// Subject field enable/disable button logic
	var $subjectBtn    = $( '#form-enable-subject-btn' );
	var $subjectHidden = $( '#form-enable-subject-hidden' );
if ($subjectBtn.length && $subjectHidden.length) {
	$subjectBtn.on(
		'click',
		function () {
			var enabled    = $subjectBtn.data( 'enabled' ) === 1 || $subjectBtn.data( 'enabled' ) === '1';
			var newEnabled = ! enabled;
			$subjectBtn.data( 'enabled', newEnabled ? 1 : 0 )
			.toggleClass( 'enabled', newEnabled )
			.text( newEnabled ? 'Disable Subject Field' : 'Enable Subject Field' );
			$subjectHidden.val( newEnabled ? '1' : '0' );
			$subjectBtn.blur();
			$subjectBtn.prop( 'disabled', true );
			$subjectBtn.text( newEnabled ? 'Saving...' : 'Saving...' );
			$subjectBtn.closest( 'form' ).trigger( 'submit' );
		}
	);
	// Re-enable button after save (listen for custom event)
	$( document ).on(
		'cin:settings:saved',
		function () {
			$subjectBtn.prop( 'disabled', false );
			var enabled = $subjectBtn.data( 'enabled' ) === 1 || $subjectBtn.data( 'enabled' ) === '1';
			$subjectBtn.text( enabled ? 'Disable Subject Field' : 'Enable Subject Field' );
		}
	);
}

	// Salutation field enable/disable button logic
	var $salutationBtn    = $( '#form-enable-salutation-btn' );
	var $salutationHidden = $( '#form-enable-salutation-hidden' );
if ($salutationBtn.length && $salutationHidden.length) {
	$salutationBtn.on(
		'click',
		function () {
			var enabled    = $salutationBtn.data( 'enabled' ) === 1 || $salutationBtn.data( 'enabled' ) === '1';
			var newEnabled = ! enabled;
			$salutationBtn.data( 'enabled', newEnabled ? 1 : 0 )
			.toggleClass( 'enabled', newEnabled )
			.text( newEnabled ? 'Disable Salutation Field' : 'Enable Salutation Field' );
			$salutationHidden.val( newEnabled ? '1' : '0' );
			$salutationBtn.blur();
			$salutationBtn.prop( 'disabled', true );
			$salutationBtn.text( newEnabled ? 'Saving...' : 'Saving...' );
			$salutationBtn.closest( 'form' ).trigger( 'submit' );
		}
	);
	$( document ).on(
		'cin:settings:saved',
		function () {
			$salutationBtn.prop( 'disabled', false );
			var enabled = $salutationBtn.data( 'enabled' ) === 1 || $salutationBtn.data( 'enabled' ) === '1';
			$salutationBtn.text( enabled ? 'Disable Salutation Field' : 'Enable Salutation Field' );
		}
	);
}
/**
 * ContactIn – Admin Settings: Modern Auto-Save & Toggle UI
 * - Auto-save on change for all settings fields
 * - Modern enable/disable toggle for REST API and file upload
 */
(function ($) {
	'use strict';

	$(
		function () {
			var $form = $( '#contactin-settings-form' );
			if ( ! $form.length) {
				return;
			}

		}
	);

})( jQuery );
