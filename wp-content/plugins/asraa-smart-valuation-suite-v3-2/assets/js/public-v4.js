/* Asraa Smart Valuation Suite – Public Init v4 */
/* jshint esversion: 5 */
(function ($) {
    'use strict';

    /* ============================================================
       NUMBER FORMAT
    ============================================================ */
    function numberFormat(n) {
        return n ? Number(n).toLocaleString() : '0';
    }


    /* ============================================================
       RESULT POPUP
    ============================================================ */
    function showResultPopup(d) {
        var modal = $('#asraa-svs-premium-modal');

        modal.find('.asraa-svs-modal-main').text(d.message_main || '');
        modal.find('.asraa-svs-modal-sub').text(d.message_sub || '');

        if (d.range_label) {
            modal.find('.asraa-svs-range').text(d.range_label).show();
        } else {
            modal.find('.asraa-svs-range').hide();
        }

        /* Price cards – support both legacy (fair/premium) and v4 (mid/high) keys */
        var lowPrice  = d.low_price   || 0;
        var midPrice  = d.mid_price   || d.fair_price    || 0;
        var highPrice = d.high_price  || d.premium_price || 0;

        var $cards = modal.find('.asraa-svs-price-cards');
        if (lowPrice || midPrice || highPrice) {
            $cards.html(
                '<div class="asraa-svs-pcard asraa-svs-pcard--low">' +
                    '<span class="asraa-svs-pcard-label">Low</span>' +
                    '<span class="asraa-svs-pcard-val">' + (lowPrice ? numberFormat(lowPrice) : '–') + '</span>' +
                '</div>' +
                '<div class="asraa-svs-pcard asraa-svs-pcard--fair">' +
                    '<span class="asraa-svs-pcard-label">Mid</span>' +
                    '<span class="asraa-svs-pcard-val">' + (midPrice ? numberFormat(midPrice) : '–') + '</span>' +
                '</div>' +
                '<div class="asraa-svs-pcard asraa-svs-pcard--premium">' +
                    '<span class="asraa-svs-pcard-label">High</span>' +
                    '<span class="asraa-svs-pcard-val">' + (highPrice ? numberFormat(highPrice) : '–') + '</span>' +
                '</div>'
            ).show();
        } else {
            $cards.hide();
        }

        var line = 'Confidence: ' + d.confidence + '% (' + (d.confidence_label || '') + ') · Demand: ' + (d.demand || '');
        if (d.valuation_source) { line += ' · ' + d.valuation_source; }
        modal.find('.asraa-svs-confidence-text').text(line);

        modal.find('.asraa-svs-details').html(d.details_html || '');
        modal.find('.asraa-svs-ai-body').html(d.ai_html || '');

        var waText = 'My Property Valuation:\n' + (d.message_main || '') + '\n';
        if (d.range_label)      { waText += 'Range: ' + d.range_label + '\n'; }
        if (d.quick_sale_price) { waText += 'Quick Sale: ' + numberFormat(d.quick_sale_price) + '\n'; }
        waText += line;
        modal.find('.asraa-svs-whatsapp').attr('href', 'https://wa.me/?text=' + encodeURIComponent(waText));

        modal.fadeIn(120);
    }


    /* ============================================================
       MODAL CLOSE – click X / click overlay / swipe down
    ============================================================ */
    function animateClose($modal) {
        if ($modal.data('closing')) { return; }
        $modal.data('closing', true);
        var $content = $modal.find('.asraa-svs-modal-content');
        $modal.addClass('asraa-svs-modal--closing-overlay');
        $content.addClass('asraa-svs-modal--closing');
        $content.one('animationend webkitAnimationEnd oAnimationEnd', function () {
            $modal.hide();
            $modal.removeClass('asraa-svs-modal--closing-overlay');
            $content.removeClass('asraa-svs-modal--closing');
            $content.css({ transform: '', transition: '' });
            $modal.data('closing', false);
        });
    }


    /* ============================================================
       GOOGLE MAPS SAFE LOADER
    ============================================================ */
    function waitForGoogle(callback) {
        if (window.google && google.maps && google.maps.places) {
            callback();
        } else {
            setTimeout(function () { waitForGoogle(callback); }, 200);
        }
    }

    function initPlacesAutocomplete() {
        var $input = $('#asraa-svs-address-search');
        if (!$input.length) { return; }
        if (window.AsraaVSAutocomplete) {
            AsraaVSAutocomplete._initGoogle();
        }
    }


    /* ============================================================
       MAIN AsraaValuation OBJECT
    ============================================================ */
    window.AsraaValuation = {

        showResultPopup: showResultPopup,

        calculate: function () {
            if (window.AsraaVSEngine) {
                AsraaVSEngine.calculate();
            }
        },

        init: function () {

            var $form         = $('.asraa-svs-valuation-form');
            var $addressInput = $('#asraa-svs-address-search');
            var $dropdown     = $('#asraa-svs-ac-dropdown');
            var $filled       = $('#asraa-svs-address-filled');

            /* Init autocomplete */
            if (window.AsraaVSAutocomplete && $addressInput.length) {
                AsraaVSAutocomplete.init($addressInput, $dropdown, $filled);

                /* Wait for Google Maps then wire up Places autocomplete */
                if (window.ASRAA_SVS && ASRAA_SVS.google_places_key) {
                    waitForGoogle(initPlacesAutocomplete);
                }
            }

            /* Init engine */
            if (window.AsraaVSEngine && $form.length) {
                AsraaVSEngine.init($form, $addressInput);
            }

            /* Property type toggle – JS fallback for browsers without :has() support */
            (function () {
                function syncPills() {
                    $('.asraa-svs-type-toggle .asraa-svs-type-pill').each(function () {
                        var $radio = $(this).find('input[type="radio"]');
                        if ($radio.is(':checked')) {
                            $(this).addClass('asraa-active');
                        } else {
                            $(this).removeClass('asraa-active');
                        }
                    });
                }
                // Initial sync
                syncPills();
                // On change
                $(document).on('change', '.asraa-svs-type-toggle input[type="radio"]', function () {
                    syncPills();
                });
                // On click (immediate feedback)
                $(document).on('click', '.asraa-svs-type-pill', function () {
                    var $radio = $(this).find('input[type="radio"]');
                    if (!$radio.is(':checked')) {
                        $radio.prop('checked', true).trigger('change');
                    }
                });
            }());

            /* Modal close – click X button */
            $(document).on('click', '.asraa-svs-close', function (e) {
                e.preventDefault();
                animateClose($(this).closest('.asraa-svs-modal'));
            });

            /* Modal close – click overlay */
            $(document).on('click', '.asraa-svs-modal', function (e) {
                if ($(e.target).hasClass('asraa-svs-modal')) { animateClose($(this)); }
            });

            /* Modal close – swipe down on mobile */
            var startY = 0, currentY = 0, dragging = false, threshold = 80;
            $('.asraa-svs-modal-content')
                .on('touchstart', function (e) {
                    if (e.touches.length !== 1) { return; }
                    startY = currentY = e.touches[0].clientY;
                    dragging = true;
                    $(this).css('transition', 'none');
                })
                .on('touchmove', function (e) {
                    if (!dragging) { return; }
                    currentY = e.touches[0].clientY;
                    var diff = currentY - startY;
                    if (diff > 0) { $(this).css('transform', 'translateY(' + diff + 'px)'); }
                })
                .on('touchend touchcancel', function () {
                    if (!dragging) { return; }
                    dragging = false;
                    var diff = currentY - startY;
                    var $content = $(this);
                    $content.css('transition', 'transform 0.2s ease-out');
                    if (diff > threshold) {
                        animateClose($content.closest('.asraa-svs-modal'));
                    } else {
                        $content.css('transform', 'translateY(0)');
                        setTimeout(function () { $content.css('transition', ''); }, 200);
                    }
                });
        }
    };


    /* ============================================================
       BOOT ON DOM READY
    ============================================================ */
    document.addEventListener('DOMContentLoaded', function () {
        if (window.AsraaValuation) {
            AsraaValuation.init();
        }
    });

}(jQuery));
