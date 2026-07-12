/* Asraa Smart Valuation Suite – Valuation Engine v4 */
/* jshint esversion: 5 */
(function ($) {
    'use strict';

    var ENGINE = window.AsraaVSEngine = {

        $form:         null,
        $addressInput: null,

        init: function ($form, $addressInput) {
            ENGINE.$form         = $form;
            ENGINE.$addressInput = $addressInput;
            ENGINE._bindSubmit();
        },

        /* Public: called by AsraaValuation.calculate() and calculate button */
        calculate: function () {
            if (!ENGINE.$form || !ENGINE.$form.length) { return; }

            var $btn   = ENGINE.$form.find('.asraa-svs-btn');
            var $error = ENGINE.$form.find('.asraa-svs-error');
            $error.hide().text('');

            /* Collect form data */
            var $f         = ENGINE.$form;
            var $ai        = ENGINE.$addressInput;
            var area_size  = $f.find('input[name="area_size"]').val();
            var email      = $f.find('input[name="email"]').val();
            var phone      = $f.find('input[name="phone"]').val();

            /* Client-side validation: sqft, email, phone required */
            if (!area_size || parseFloat(area_size) <= 0) {
                $error.text('Please enter the property area/sqft.').fadeIn(200);
                return;
            }
            if (!email) {
                $error.text('Please enter your email address.').fadeIn(200);
                return;
            }
            if (!phone) {
                $error.text('Please enter your phone number.').fadeIn(200);
                return;
            }

            var action = (window.ASRAA_SVS && ASRAA_SVS.action_calculate)
                ? ASRAA_SVS.action_calculate : 'asraa_svs_calculate';

            var data = {
                action:        action,
                nonce:         ASRAA_SVS.nonce,
                /* Address – use hidden field or fall back to typed value */
                building_name: $('#asraa-field-building_name').val() || ($ai ? $ai.val() : ''),
                property_address: $ai ? $ai.val() : '',
                area:          $('#asraa-field-area').val(),
                locality:      $('#asraa-field-locality').val(),
                city:          $('#asraa-field-city').val() || ($ai ? $ai.val() : ''),
                state:         $('#asraa-field-state').val(),
                country:       $('#asraa-field-country').val(),
                pincode:       $('#asraa-field-pincode').val(),
                latitude:      $('#asraa-field-latitude').val(),
                longitude:     $('#asraa-field-longitude').val(),
                property_type: $f.find('input[name="property_type"]:checked').val(),
                area_size:     area_size,
                floor_num:     $f.find('input[name="floor_num"]').val(),
                age_years:     $f.find('input[name="age_years"]').val(),
                monthly_rent:  $f.find('input[name="monthly_rent"]').val(),
                yield_pct:     $f.find('input[name="yield_pct"]').val(),
                comp_1_price:  $f.find('input[name="comp_1_price"]').val(),
                comp_1_area:   $f.find('input[name="comp_1_area"]').val(),
                comp_2_price:  $f.find('input[name="comp_2_price"]').val(),
                comp_2_area:   $f.find('input[name="comp_2_area"]').val(),
                comp_3_price:  $f.find('input[name="comp_3_price"]').val(),
                comp_3_area:   $f.find('input[name="comp_3_area"]').val(),
                name:          $f.find('input[name="name"]').val(),
                email:         email,
                phone:         phone,
                /* Legacy compat keys */
                building:      $('#asraa-field-building_name').val() || ($ai ? $ai.val() : ''),
                sqft:          area_size,
                config:        $f.find('input[name="property_type"]:checked').val()
            };

            $btn.prop('disabled', true).addClass('asraa-svs-btn-loading').text('Calculating…');

            console.log('[Asraa SVS] Submitting valuation request', data);

            $.post(ASRAA_SVS.ajax_url, data, function (response) {
                $btn.prop('disabled', false).removeClass('asraa-svs-btn-loading').text('Get Valuation');

                if (response && response.success) {
                    console.log('[Asraa SVS] Valuation success:', response.data);
                    if (window.AsraaValuation && typeof AsraaValuation.showResultPopup === 'function') {
                        AsraaValuation.showResultPopup(response.data);
                    }
                } else {
                    var msg = (response && response.data && response.data.message)
                        ? response.data.message
                        : 'Something went wrong. Please try again.';
                    console.error('[Asraa SVS] Valuation error:', msg, response);
                    $error.text(msg).fadeIn(200);
                }
            }).fail(function (xhr, status, err) {
                $btn.prop('disabled', false).removeClass('asraa-svs-btn-loading').text('Get Valuation');
                console.error('[Asraa SVS] AJAX fail:', status, err, xhr.responseText);
                $error.text('Server error. Please try again.').fadeIn(200);
            });
        },

        _bindSubmit: function () {
            ENGINE.$form.on('submit', function (e) {
                e.preventDefault();
                ENGINE.calculate();
            });
        }
    };

}(jQuery));
