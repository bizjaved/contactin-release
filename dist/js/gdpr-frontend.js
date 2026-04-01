(function () {
	// Helper to escape HTML characters
	function escapeHtml(text) {
		var map = {
			'&': '&amp;',
			'<': '&lt;',
			'>': '&gt;',
			'"': '&quot;',
			"'": '&#039;'
		};
		return text.replace(
			/[&<>"']/g,
			function (m) {
				return map[m]; }
		);
	}

	document.addEventListener(
		'DOMContentLoaded',
		function () {
			var container = document.querySelector( '[data-cin-gdpr-confirm]' );
			if ( ! container || typeof window.cinGdprFrontend === 'undefined') {
				return;
			}

			var token   = container.getAttribute( 'data-token' ) || '';
			var email   = container.getAttribute( 'data-email' ) || '';
			var ajaxUrl = window.cinGdprFrontend.ajaxUrl || '';
			var homeUrl = window.cinGdprFrontend.homeUrl || '/';
			var i18n    = window.cinGdprFrontend.i18n || {};

			var confirmBtn        = document.getElementById( 'cin-confirm-delete' );
			var progressContainer = document.getElementById( 'cin-progress' );
			var progressFill      = document.getElementById( 'cin-progress-fill' );
			var progressText      = document.getElementById( 'cin-progress-text' );
			var errorContainer    = document.getElementById( 'cin-error' );
			var errorText         = document.getElementById( 'cin-error-text' );
			var actionsContainer  = document.getElementById( 'cin-actions' );

			if ( ! confirmBtn) {
				return;
			}

			confirmBtn.addEventListener(
				'click',
				function () {
					performDeletion();
				}
			);

			function performDeletion() {
				confirmBtn.disabled             = true;
				progressContainer.style.display = 'block';
				errorContainer.style.display    = 'none';

				updateProgress( 10, i18n.starting || 'Starting deletion process...' );

				var formData = new URLSearchParams();
				formData.append( 'action', 'ci_gdpr_frontend_delete' );
				formData.append( 'token', token );
				formData.append( 'email', email );

				var progressTimer = setTimeout(
					function () {
						updateProgress( 35, i18n.deleting || 'Deleting messages and files...' );
					},
					600
				);

				fetch(
					ajaxUrl,
					{
						method: 'POST',
						credentials: 'same-origin',
						headers: {
							'Content-Type': 'application/x-www-form-urlencoded',
							'X-Requested-With': 'XMLHttpRequest'
						},
						body: formData.toString()
					}
				)
				.then(
					function (response) {
						return response.json();
					}
				)
				.then(
					function (data) {
						clearTimeout( progressTimer );
						if (data.success) {
							updateProgress( 100, (data.data && data.data.message) || i18n.done || 'Deletion complete!' );
							// Don't redirect - stay on the page showing completion status
							actionsContainer.innerHTML = '<a href="' + escapeHtml( homeUrl ) + '" class="cin-btn cin-btn-cancel">' + (i18n.back_to_home || 'Back to Home') + '</a>';
						} else {
							showError( (data.data && data.data.message) || data.data || i18n.error || 'An unknown error occurred.' );
						}
					}
				)
				.catch(
					function () {
						clearTimeout( progressTimer );
						showError( i18n.network_error || 'Network error. Please check your connection and try again.' );
					}
				);
			}

			function updateProgress(percent, message) {
				progressFill.style.width = percent + '%';
				progressFill.textContent = percent + '%';
				progressText.textContent = message;
			}

			function showError(message) {
				progressContainer.style.display = 'none';
				errorContainer.style.display    = 'block';
				errorText.textContent           = message;

				actionsContainer.innerHTML = '';

				var cancelLink         = document.createElement( 'a' );
				cancelLink.className   = 'cin-btn cin-btn-cancel';
				cancelLink.href        = homeUrl;
				cancelLink.textContent = i18n.cancel || 'Cancel';

				var retryBtn         = document.createElement( 'button' );
				retryBtn.type        = 'button';
				retryBtn.className   = 'cin-btn cin-btn-retry';
				retryBtn.textContent = i18n.try_again || 'Try Again';
				retryBtn.addEventListener(
					'click',
					function () {
						location.reload();
					}
				);

				actionsContainer.appendChild( cancelLink );
				actionsContainer.appendChild( retryBtn );
			}
		}
	);
})();
