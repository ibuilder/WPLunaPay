/* MoonPay Crypto Payments — Shortcode / Widget JS */
/* global wpLunaPayShortcodes, jQuery */

(function ($) {
	'use strict';

	if ( typeof wpLunaPayShortcodes === 'undefined' ) return;

	// -------------------------------------------------------------------------
	// Price ticker
	// -------------------------------------------------------------------------

	function fetchPrice($el) {
		var currency   = $el.data('currency');
		var fiat       = $el.data('fiat');
		var showChange = ( $el.data('show-change') === true || $el.data('show-change') === 'true' );

		$.post(wpLunaPayShortcodes.ajaxUrl, {
			action:   'wp_lunapay_get_price',
			nonce:    wpLunaPayShortcodes.nonce,
			currency: currency,
			fiat:     fiat
		})
		.done(function (res) {
			if ( ! res.success || res.data.price === null ) return;

			var price    = parseFloat(res.data.price);
			var fiatCode = (res.data.fiat || fiat).toUpperCase();
			var formatted;

			// Intl.NumberFormat throws on unsupported currency codes — always guard.
			try {
				formatted = new Intl.NumberFormat('en-US', {
					style:                 'currency',
					currency:              fiatCode,
					minimumFractionDigits: 2,
					maximumFractionDigits: price < 1 ? 6 : 2
				}).format(price);
			} catch (e) {
				// Fallback: plain number with fiat code suffix
				formatted = price.toFixed(price < 1 ? 6 : 2) + ' ' + fiatCode;
			}

			$el.find('.moonpay-price-value').text(formatted);
		})
		.fail(function () {
			// Silent fail — ticker stays at "Loading…" and retries in 60s
		});
	}

	function initPriceTickers() {
		$('.moonpay-price-ticker').each(function () {
			var $el = $(this);
			fetchPrice($el);
			// Refresh every 60 seconds (server-side transient also caches for 60s)
			setInterval(function () { fetchPrice($el); }, 60000);
		});
	}

	// -------------------------------------------------------------------------
	// Popup shortcode ([moonpay_buy mode="popup"])
	// -------------------------------------------------------------------------

	function initShortcodePopup() {
		$(document).on('click', '.moonpay-popup-trigger', function () {
			var url     = $(this).data('url');
			var $wrap   = $(this).closest('.moonpay-popup-wrap');
			var $overlay = $wrap.find('.moonpay-popup-overlay');
			if ( ! $overlay.length ) return;
			$overlay.find('iframe').attr('src', url);
			$overlay.fadeIn(200);
			$('body').css('overflow', 'hidden');
		});

		$(document).on('click', '.moonpay-popup-close', function () {
			var $overlay = $(this).closest('.moonpay-popup-overlay');
			$overlay.fadeOut(200, function () {
				$overlay.find('iframe').attr('src', '');
			});
			$('body').css('overflow', '');
		});

		$(document).on('click', '.moonpay-popup-overlay', function (e) {
			if ( $(e.target).is('.moonpay-popup-overlay') ) {
				var $overlay = $(this);
				$overlay.fadeOut(200, function () {
					$overlay.find('iframe').attr('src', '');
				});
				$('body').css('overflow', '');
			}
		});

		$(document).on('keydown', function (e) {
			if ( e.key === 'Escape' ) {
				$('.moonpay-popup-overlay:visible').each(function () {
					$(this).fadeOut(200, function () {
						$(this).find('iframe').attr('src', '');
					});
				});
				$('body').css('overflow', '');
			}
		});
	}

	// -------------------------------------------------------------------------
	// Init
	// -------------------------------------------------------------------------

	$(function () {
		initPriceTickers();
		initShortcodePopup();
	});

})(jQuery);
