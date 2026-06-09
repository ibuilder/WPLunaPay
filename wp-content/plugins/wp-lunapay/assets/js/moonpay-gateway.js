/* MoonPay Crypto Payments — Checkout / receipt page JS */
/* global wpLunaPay, jQuery */

(function ($) {
	'use strict';

	// Guard: wpLunaPay must be defined by wp_localize_script before this runs.
	// Using typeof avoids a ReferenceError if the object is missing for any reason.
	if ( typeof wpLunaPay === 'undefined' ) {
		return;
	}

	var mode = wpLunaPay.widgetMode || 'redirect';

	// -------------------------------------------------------------------------
	// Popup mode
	// -------------------------------------------------------------------------

	function initPopup() {
		$(document).on('click', '.moonpay-popup-trigger', function () {
			var url = $(this).data('url');
			var $overlay = $('#moonpay-popup-overlay').length
				? $('#moonpay-popup-overlay')
				: $('.moonpay-popup-overlay').first();
			if ( ! $overlay.length ) return;
			$overlay.find('iframe').attr('src', url);
			$overlay.fadeIn(200);
			$('body').css('overflow', 'hidden');
		});

		$(document).on('click', '.moonpay-popup-close, #moonpay-popup-close', function () {
			closePopup();
		});

		$(document).on('click', '.moonpay-popup-overlay', function (e) {
			if ( $(e.target).is('.moonpay-popup-overlay') ) {
				closePopup();
			}
		});

		$(document).on('keydown', function (e) {
			if ( e.key === 'Escape' ) closePopup();
		});
	}

	function closePopup() {
		var $overlay = $('#moonpay-popup-overlay').length
			? $('#moonpay-popup-overlay')
			: $('.moonpay-popup-overlay').first();
		$overlay.fadeOut(200, function () {
			$overlay.find('iframe').attr('src', '');
		});
		$('body').css('overflow', '');
	}

	// -------------------------------------------------------------------------
	// MoonPay postMessage listener (iframe / popup modes)
	//
	// Security: we match the exact allowed origins rather than using
	// String.prototype.includes(), which could be tricked by a hostname like
	// "buy.moonpay.com.evil.example.com".
	// -------------------------------------------------------------------------

	var ALLOWED_ORIGINS = [
		'https://buy.moonpay.com',
		'https://buy-sandbox.moonpay.com',
	];

	function initMessageListener() {
		window.addEventListener('message', function (event) {
			if ( typeof event.origin !== 'string' ) return;

			// Strict origin whitelist — never use .includes() for security checks
			var allowed = false;
			for ( var i = 0; i < ALLOWED_ORIGINS.length; i++ ) {
				if ( event.origin === ALLOWED_ORIGINS[i] ) {
					allowed = true;
					break;
				}
			}
			if ( ! allowed ) return;

			var data = event.data;
			if ( ! data || typeof data !== 'object' ) return;

			if ( data.type === 'moonpay:txStatus' ) {
				handleTxStatus(data);
			}
		}, false);
	}

	function handleTxStatus(data) {
		if ( data.status === 'completed' ) {
			// Redirect to thank-you page after payment completes inside the widget.
			// The webhook will have (or will shortly) update the order status server-side.
			if ( window.location.href.indexOf('order-received') === -1 ) {
				// Small delay to let the webhook arrive before the thank-you page loads
				setTimeout(function () {
					window.location.reload();
				}, 1500);
			}
		}
	}

	// -------------------------------------------------------------------------
	// Init
	// -------------------------------------------------------------------------

	$(function () {
		if ( mode === 'popup' ) {
			initPopup();
		}
		// Always listen for postMessage events in iframe and popup modes
		if ( mode === 'iframe' || mode === 'popup' ) {
			initMessageListener();
		}
	});

})(jQuery);
