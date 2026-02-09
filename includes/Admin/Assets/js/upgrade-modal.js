/**
 * Contact Inbox - Upgrade Modal JavaScript
 */
(function($) {
	'use strict';

	/**
	 * Upgrade Modal Handler
	 */
	var UpgradeModal = {

		/**
		 * Initialize the modal
		 */
		init: function() {
			this.modal = $('#contactinbox-upgrade-modal');
			this.closeBtn = $('.contactinbox-modal-close');
			this.overlay = $('.contactinbox-modal-overlay');
			this.upgradeBtn = $('.contactinbox-upgrade-btn');

			this.bindEvents();
		},

		/**
		 * Bind all events
		 */
		bindEvents: function() {
			var self = this;

			// Show modal when clicking on premium triggers
			$(document).on('click', '.contactinbox-show-upgrade-modal', function(e) {
				e.preventDefault();
				e.stopPropagation();
				self.show();
			});

			// Close modal events
			this.closeBtn.on('click', function(e) {
				e.preventDefault();
				self.hide();
			});

			this.overlay.on('click', function(e) {
				e.preventDefault();
				self.hide();
			});

			// Close on ESC key
			$(document).on('keydown', function(e) {
				if (e.keyCode === 27 && self.modal.is(':visible')) {
					self.hide();
				}
			});

			// Update upgrade button URL
			if (typeof contactinboxUpgrade !== 'undefined' && contactinboxUpgrade.proUrl) {
				this.upgradeBtn.attr('href', contactinboxUpgrade.proUrl);
			}

			// Prevent clicks on premium fields
			$(document).on('click', '.contactinbox-premium-field', function(e) {
				if (!$(e.target).hasClass('contactinbox-show-upgrade-modal') && 
					!$(e.target).closest('.contactinbox-show-upgrade-modal').length) {
					e.preventDefault();
					e.stopPropagation();
					self.show();
				}
			});

			// Make premium elements trigger modal
			this.makePremiumElementsClickable();
		},

		/**
		 * Show the modal
		 */
		show: function() {
			this.modal.fadeIn(200);
			$('body').addClass('contactinbox-modal-open');
		},

		/**
		 * Hide the modal
		 */
		hide: function() {
			this.modal.fadeOut(200);
			$('body').removeClass('contactinbox-modal-open');
		},

		/**
		 * Make premium elements trigger the modal on click
		 */
		makePremiumElementsClickable: function() {
			var self = this;

			// Add data attribute to identify premium elements
			var premiumSelectors = [
				'.contactinbox-premium-field',
				'[data-premium="true"]',
				'.contactinbox-pro-badge'
			];

			$(premiumSelectors.join(',')).each(function() {
				$(this).css('cursor', 'pointer');
				if (!$(this).hasClass('contactinbox-show-upgrade-modal')) {
					$(this).addClass('contactinbox-premium-trigger');
				}
			});

			// Handle clicks on premium triggers
			$(document).on('click', '.contactinbox-premium-trigger', function(e) {
				if (!$(e.target).is('a') && !$(e.target).closest('a').length) {
					e.preventDefault();
					e.stopPropagation();
					self.show();
				}
			});
		}
	};

	/**
	 * Initialize on document ready
	 */
	$(document).ready(function() {
		UpgradeModal.init();
	});

})(jQuery);
