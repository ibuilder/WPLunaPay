/* MoonPay Crypto Payments — Admin JS
 * Handles: Setup Wizard buttons, API test, webhook test, go-live modal, notices.
 * All button/result IDs match the wizard HTML (wlp-* prefix).
 */
/* global wpLunaPayAdmin, jQuery */
(function ($) {
    'use strict';

    if ( typeof wpLunaPayAdmin === 'undefined' ) return;

    var ajax  = wpLunaPayAdmin.ajaxUrl;
    var nonce = wpLunaPayAdmin.nonce;
    var i18n  = wpLunaPayAdmin.i18n;

    /* ── helpers ── */
    function ok( $el, msg )  { $el.css('color','#1a7f37').html( '&#10003; ' + msg ).show(); }
    function err( $el, msg ) { $el.css('color','#cf222e').html( '&#10007; ' + msg ).show(); }
    function msg( res )      { return (res.data && res.data.message) ? res.data.message : (typeof res.data === 'string' ? res.data : ''); }
    function busy( $btn, label ) { $btn.prop('disabled', true).text( label ); }
    function done( $btn, label ) { $btn.prop('disabled', false).text( label ); }

    /* ════════════════════════════════════════════════════════════════════════
       STEP 2 — Test Connection
    ════════════════════════════════════════════════════════════════════════ */
    $( document ).on( 'click', '#wlp-btn-test-connection', function () {
        var $btn = $( this );
        var $res = $( '#wlp-connection-result' );
        var pub  = $( '#wlp-pk' ).val().trim();
        var sec  = $( '#wlp-sk' ).val().trim();

        if ( !pub || !sec ) { err( $res, i18n.enterBothKeys ); return; }

        busy( $btn, i18n.testing );
        $res.hide();

        $.post( ajax, { action: 'wp_lunapay_test_connection', nonce: nonce,
                         publishable_key: pub, secret_key: sec } )
        .done( function(r) { r.success ? ok($res, msg(r) || i18n.connected) : err($res, msg(r) || i18n.failed); } )
        .fail( function()  { err( $res, i18n.failed + ' (network error)' ); } )
        .always( function(){ done( $btn, i18n.btnTestConnection ); } );
    });

    /* STEP 2 — Save Keys */
    $( document ).on( 'click', '#wlp-btn-save-keys', function () {
        var $btn = $( this );
        var $res = $( '#wlp-connection-result' );
        var pub  = $( '#wlp-pk' ).val().trim();
        var sec  = $( '#wlp-sk' ).val().trim();

        if ( !pub || !sec ) { err( $res, i18n.enterBothKeys ); return; }

        busy( $btn, i18n.saving );
        $res.hide();

        $.post( ajax, { action: 'wp_lunapay_save_wizard_step', nonce: nonce,
                         step: 2, data: { publishable_key: pub, secret_key: sec } } )
        .done( function(r) {
            if ( r.success ) {
                ok( $res, msg(r) || 'Saved!' );
                setTimeout( function(){ window.location.href = wpLunaPayAdmin.wizardUrl + '&step=3'; }, 900 );
            } else {
                err( $res, msg(r) || i18n.failed );
                done( $btn, i18n.btnSaveKeys );
            }
        })
        .fail( function(){ err($res, i18n.failed); done($btn, i18n.btnSaveKeys); });
    });

    /* ════════════════════════════════════════════════════════════════════════
       STEP 3 — Copy webhook URL
    ════════════════════════════════════════════════════════════════════════ */
    $( document ).on( 'click', '#wlp-btn-copy-webhook', function () {
        var $btn = $( this );
        var url  = $( '#wlp-webhook-url' ).text().trim();

        if ( navigator.clipboard && window.isSecureContext ) {
            navigator.clipboard.writeText( url )
                .then( function(){ $btn.text(i18n.copied); setTimeout(function(){ $btn.text(i18n.copy); }, 2000); } )
                .catch( function(){ fallbackCopy(url, $btn); });
        } else { fallbackCopy(url, $btn); }
    });

    function fallbackCopy( text, $btn ) {
        var $t = $('<textarea style="position:fixed;top:-9999px;opacity:0">').val(text).appendTo('body');
        $t.get(0).select();
        try { document.execCommand('copy'); $btn.text(i18n.copied); setTimeout(function(){ $btn.text(i18n.copy); }, 2000); }
        catch(e) {}
        $t.remove();
    }

    /* STEP 3 — Test webhook reachability */
    $( document ).on( 'click', '#wlp-btn-test-webhook', function () {
        var $btn = $( this );
        var $res = $( '#wlp-webhook-result' );

        busy( $btn, i18n.testing );
        $res.hide();

        $.post( ajax, { action: 'wp_lunapay_test_webhook_url', nonce: nonce } )
        .done( function(r) { r.success ? ok($res, msg(r) || i18n.reachable) : err($res, msg(r) || i18n.notReachable); } )
        .fail( function()  { err( $res, i18n.failed + ' (network error)' ); } )
        .always( function(){ done( $btn, i18n.btnTestReachability ); } );
    });

    /* ════════════════════════════════════════════════════════════════════════
       STEP 4 — Save config
    ════════════════════════════════════════════════════════════════════════ */
    $( document ).on( 'click', '#wlp-btn-save-config', function () {
        var $btn = $( this );
        var $res = $( '#wlp-config-result' );

        busy( $btn, i18n.saving );
        $res.hide();

        $.post( ajax, {
            action: 'wp_lunapay_save_wizard_step', nonce: nonce, step: 4,
            data: {
                widget_mode:    $( '#wlp-widget-mode' ).val(),
                default_crypto: $( '#wlp-default-crypto' ).val().trim(),
                color_code:     $( '#wlp-color' ).val()
            }
        })
        .done( function(r) {
            if ( r.success ) {
                ok( $res, msg(r) || 'Saved!' );
                setTimeout( function(){ window.location.href = wpLunaPayAdmin.wizardUrl + '&step=5'; }, 900 );
            } else {
                err( $res, msg(r) || i18n.failed );
                done( $btn, i18n.btnSaveConfig );
            }
        })
        .fail( function(){ err($res, i18n.failed); done($btn, i18n.btnSaveConfig); });
    });

    /* ════════════════════════════════════════════════════════════════════════
       STEP 5 — Create test order
    ════════════════════════════════════════════════════════════════════════ */
    $( document ).on( 'click', '#wlp-btn-create-test-order', function () {
        var $btn = $( this );
        var $res = $( '#wlp-test-order-result' );

        busy( $btn, i18n.creating );
        $res.empty().hide();

        $.post( ajax, { action: 'wp_lunapay_create_test_order', nonce: nonce } )
        .done( function(r) {
            if ( r.success ) {
                var d = r.data;
                $res.html(
                    '<p style="color:#1a7f37;">&#10003; ' + ( d.message || i18n.testOrderCreated ) + ' ' +
                    '<a href="' + d.checkout_url + '" target="_blank">' + i18n.openCheckout + '</a> ' +
                    '<a href="' + d.order_url   + '" target="_blank">' + i18n.viewInAdmin   + '</a></p>'
                ).show();
                done( $btn, i18n.btnAnotherTestOrder );
            } else {
                err( $res, msg(r) || i18n.failed );
                $res.show();
                done( $btn, i18n.btnCreateTestOrder );
            }
        })
        .fail( function(){ err($res, i18n.failed); $res.show(); done($btn, i18n.btnCreateTestOrder); });
    });

    /* ════════════════════════════════════════════════════════════════════════
       STEP 6 — Go Live modal
    ════════════════════════════════════════════════════════════════════════ */
    $( document ).on( 'click', '#wlp-btn-go-live', function () {
        $( '#wlp-golive-modal' ).fadeIn( 200 );
    });

    $( document ).on( 'click', '#wlp-btn-cancel-golive', function () {
        $( '#wlp-golive-modal' ).fadeOut( 200 );
    });

    $( document ).on( 'click', '#wlp-btn-confirm-golive', function () {
        var $btn = $( this );
        var $res = $( '#wlp-golive-result' );

        busy( $btn, i18n.goingLive );
        $( '#wlp-golive-modal' ).fadeOut( 200 );

        $.post( ajax, { action: 'wp_lunapay_go_live', nonce: nonce } )
        .done( function(r) {
            if ( r.success ) {
                ok( $res, msg(r) || 'Live mode active!' );
                setTimeout( function(){ window.location.reload(); }, 1500 );
            } else {
                err( $res, msg(r) || i18n.failed );
                done( $btn, i18n.btnGoLive );
            }
        })
        .fail( function(){ err($res, i18n.failed); done($btn, i18n.btnGoLive); });
    });

    /* ════════════════════════════════════════════════════════════════════════
       Admin notice dismissal
    ════════════════════════════════════════════════════════════════════════ */
    $( document ).on( 'click', '.moonpay-admin-notice .notice-dismiss', function () {
        var notice_id = $( this ).closest( '.moonpay-admin-notice' ).data( 'notice-id' );
        if ( !notice_id ) return;
        $.post( ajax, { action: 'wp_lunapay_dismiss_notice', nonce: nonce, notice_id: notice_id } );
    });

    /* WC Settings — confirm sandbox toggle */
    $( document ).on( 'change', 'input[name="woocommerce_moonpay_sandbox"]', function () {
        if ( !$( this ).is( ':checked' ) ) {
            if ( !window.confirm( i18n.confirmGoLive ) ) {
                $( this ).prop( 'checked', true );
            }
        }
    });

})(jQuery);