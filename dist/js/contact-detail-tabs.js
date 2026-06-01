/**
 * Contact Detail Page - Tab Navigation
 * Handles switching between Details and Messages tabs
 */
jQuery( document ).ready(
	function ($) {
		// Tab switching functionality
		const $tabButtons = $( '.cin-detail-tab-button' );
		const $tabPanels  = $( '.cin-detail-tab-panel' );

		// Tab button click handler
		$tabButtons.on(
			'click',
			function () {
				const $button = $( this );
				const tabName = $button.data( 'tab' );
				const $panel  = $( '#cin-' + tabName + '-panel' );

				// Remove active state from all buttons and panels
				$tabButtons.removeClass( 'cin-detail-tab-active' ).attr( 'aria-selected', 'false' );
				$tabPanels.removeClass( 'cin-detail-tab-active' );

				// Add active state to clicked button and corresponding panel
				$button.addClass( 'cin-detail-tab-active' ).attr( 'aria-selected', 'true' );
				$panel.addClass( 'cin-detail-tab-active' );

				// Store the active tab in session storage for persistence
				if (typeof sessionStorage !== 'undefined') {
					sessionStorage.setItem( 'cin-contact-active-tab', tabName );
				}
			}
		);

		// Keyboard navigation for tabs (Arrow keys)
		$tabButtons.on(
			'keydown',
			function (e) {
				let $target = null;

				switch (e.keyCode) {
					case 37: // Left Arrow
					case 38: // Up Arrow
						e.preventDefault();
						$target = $( this ).prev( '.cin-detail-tab-button' );
						if ($target.length === 0) {
							$target = $tabButtons.last();
						}
						break;
					case 39: // Right Arrow
					case 40: // Down Arrow
						e.preventDefault();
						$target = $( this ).next( '.cin-detail-tab-button' );
						if ($target.length === 0) {
							$target = $tabButtons.first();
						}
						break;
					case 13: // Enter
					case 32: // Space
						e.preventDefault();
						$target = $( this );
						break;
				}

				if ($target && $target.length) {
					$target.trigger( 'click' ).focus();
				}
			}
		);

		// Always keep Contact Details tab active (ignore session storage)
		// Contact Details is the primary view on this page
		const $detailsButton = $tabButtons.filter( '[data-tab="details"]' );
		if ($detailsButton.length) {
			$detailsButton.trigger( 'click' );
		}

		// Handle window resize to optimize table layout
		let resizeTimer;
		$( window ).on(
			'resize',
			function () {
				clearTimeout( resizeTimer );
				resizeTimer = setTimeout(
					function () {
						// Trigger table responsive behavior if needed
						if (typeof $.fn.dataTable !== 'undefined') {
							$.fn.dataTable.tables( { visible: true, api: true } ).responsive.recalc();
						}
					},
					250
				);
			}
		);
	}
);
