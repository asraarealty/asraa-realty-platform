jQuery(document).ready(function ($) {

    /* ============================================================
       1) ATTEMPT LIMIT SYSTEM
    ============================================================ */

    function asraaGetAttempts() {
        return parseInt(localStorage.getItem('asraa_svs_attempts') || '0', 10);
    }
    function asraaIncreaseAttempts() {
        localStorage.setItem('asraa_svs_attempts', String(asraaGetAttempts() + 1));
    }
    function asraaLimitReached() {
        return asraaGetAttempts() >= 3;
    }
    function showLimitPopup() {
        $('#asraa-svs-limit-modal').fadeIn(200);
    }


    /* ============================================================
       2) SHOW RESULT POPUP
    ============================================================ */

    function numberFormat(n) {
        return n ? Number(n).toLocaleString() : '0';
    }

    function showResultPopup(d) {
        var modal = $('#asraa-svs-premium-modal');

        modal.find('.asraa-svs-modal-main').text(d.message_main || '');
        modal.find('.asraa-svs-modal-sub').text(d.message_sub || '');

        if (d.range_label) {
            modal.find('.asraa-svs-range').text(d.range_label).show();
        } else {
            modal.find('.asraa-svs-range').hide();
        }

        var $cards = modal.find('.asraa-svs-price-cards');
        if (d.low_price || d.fair_price || d.premium_price) {
            $cards.html(
                '<div class="asraa-svs-pcard asraa-svs-pcard--low">' +
                    '<span class="asraa-svs-pcard-label">Low</span>' +
                    '<span class="asraa-svs-pcard-val">' + (d.low_price ? numberFormat(d.low_price) : '–') + '</span>' +
                '</div>' +
                '<div class="asraa-svs-pcard asraa-svs-pcard--fair">' +
                    '<span class="asraa-svs-pcard-label">Fair</span>' +
                    '<span class="asraa-svs-pcard-val">' + (d.fair_price ? numberFormat(d.fair_price) : '–') + '</span>' +
                '</div>' +
                '<div class="asraa-svs-pcard asraa-svs-pcard--premium">' +
                    '<span class="asraa-svs-pcard-label">Premium</span>' +
                    '<span class="asraa-svs-pcard-val">' + (d.premium_price ? numberFormat(d.premium_price) : '–') + '</span>' +
                '</div>'
            ).show();
        } else {
            $cards.hide();
        }

        var line = 'Confidence: ' + d.confidence + '% (' + d.confidence_label + ') · Demand: ' + d.demand;
        if (d.valuation_source) { line += ' · ' + d.valuation_source; }
        modal.find('.asraa-svs-confidence-text').text(line);

        modal.find('.asraa-svs-details').html(d.details_html || '');
        modal.find('.asraa-svs-ai-body').html(d.ai_html || '');

        var waText = 'My Property Valuation:\n' + (d.message_main || '') + '\n';
        if (d.range_label) { waText += 'Range: ' + d.range_label + '\n'; }
        if (d.quick_sale_price) { waText += 'Quick Sale: ' + numberFormat(d.quick_sale_price) + '\n'; }
        waText += line;
        modal.find('.asraa-svs-whatsapp').attr('href', 'https://wa.me/?text=' + encodeURIComponent(waText));

        modal.fadeIn(120);
    }


    /* ============================================================
       3) CUSTOM AUTOCOMPLETE DROPDOWN
    ============================================================ */

    var $addressInput = $('#asraa-svs-address-search');
    var $dropdown     = $('#asraa-svs-ac-dropdown');
    var $filled       = $('#asraa-svs-address-filled');
    var acTimer       = null;
    var acResults     = [];
    var acActive      = -1;

    function fillHidden(name, val) {
        $('#asraa-field-' + name).val(val || '');
    }

    function clearHiddenFields() {
        ['building_name','area','locality','city','state','country','pincode','latitude','longitude'].forEach(function(f) {
            fillHidden(f, '');
        });
    }

    function applyAutocompleteResult(item) {
        fillHidden('building_name', item.building_name);
        fillHidden('area',          item.area);
        fillHidden('locality',      item.locality);
        fillHidden('city',          item.city);
        fillHidden('state',         item.state);
        fillHidden('country',       item.country);
        fillHidden('pincode',       item.pincode);
        fillHidden('latitude',      item.latitude);
        fillHidden('longitude',     item.longitude);

        if (item.property_type) {
            var $radio = $('input[name="property_type"][value="' + item.property_type + '"]');
            if ($radio.length) { $radio.prop('checked', true).trigger('change'); }
        }

        var badge = item.label || item.building_name || '';
        if (item.avg_rate) { badge += ' · Rate: ' + numberFormat(item.avg_rate); }
        $filled.text('✓ ' + badge).show();
        $addressInput.val(item.label || item.building_name || '');
        closeDropdown();
    }

    function closeDropdown() {
        $dropdown.hide().empty();
        acResults = [];
        acActive  = -1;
    }

    function renderDropdown(items) {
        $dropdown.empty();
        if (!items || !items.length) { closeDropdown(); return; }
        acResults = items;
        acActive  = -1;
        items.forEach(function(item, idx) {
            var $item = $('<div class="asraa-svs-ac-item"></div>').text(item.label || item.building_name);
            if (item.avg_rate) {
                $item.append($('<small></small>').text(' – Rate: ' + numberFormat(item.avg_rate)));
            }
            $item.on('mousedown', function(e) {
                e.preventDefault();
                applyAutocompleteResult(item);
            });
            $item.data('idx', idx);
            $dropdown.append($item);
        });
        $dropdown.show();
    }

    function fetchSuggestions(q) {
        if (!q || q.length < 2) { closeDropdown(); return; }
        $.get(ASRAA_SVS.ajax_url, { action: 'asraa_svs_autocomplete', nonce: ASRAA_SVS.nonce, q: q }, function(resp) {
            if (resp && resp.success && resp.data && resp.data.length) {
                renderDropdown(resp.data);
            } else {
                closeDropdown();
                if (ASRAA_SVS.google_places_key && typeof google !== 'undefined' && google.maps && google.maps.places) {
                    initGoogleFallback();
                }
            }
        });
    }

    $addressInput.on('input', function() {
        clearTimeout(acTimer);
        clearHiddenFields();
        $filled.hide();
        var q = $(this).val().trim();
        if (q.length < 2) { closeDropdown(); return; }
        acTimer = setTimeout(function() { fetchSuggestions(q); }, 300);
    });

    $addressInput.on('keydown', function(e) {
        var $items = $dropdown.find('.asraa-svs-ac-item');
        if (!$items.length) { return; }
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            acActive = Math.min(acActive + 1, $items.length - 1);
            $items.removeClass('active').eq(acActive).addClass('active');
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            acActive = Math.max(acActive - 1, 0);
            $items.removeClass('active').eq(acActive).addClass('active');
        } else if (e.key === 'Enter') {
            if (acActive >= 0 && acResults[acActive]) {
                e.preventDefault();
                applyAutocompleteResult(acResults[acActive]);
            }
        } else if (e.key === 'Escape') {
            closeDropdown();
        }
    });

    $addressInput.on('blur', function() { setTimeout(closeDropdown, 200); });


    /* ============================================================
       4) GOOGLE PLACES FALLBACK
    ============================================================ */

    var googleFallbackInited = false;

    function initGoogleFallback() {
        if (googleFallbackInited) { return; }
        if (typeof google === 'undefined' || !google.maps || !google.maps.places) { return; }
        googleFallbackInited = true;
        var inputEl = $addressInput[0];
        var ac = new google.maps.places.Autocomplete(inputEl, {});
        ac.addListener('place_changed', function() {
            var place = ac.getPlace();
            if (!place || !place.geometry) { return; }
            var comps = {};
            (place.address_components || []).forEach(function(c) {
                c.types.forEach(function(t) { comps[t] = c.long_name; });
            });
            applyAutocompleteResult({
                source:        'google',
                label:         place.name || place.formatted_address || '',
                building_name: place.name || '',
                area:          comps.sublocality_level_1 || comps.sublocality || '',
                locality:      comps.sublocality_level_2 || '',
                city:          comps.locality || comps.administrative_area_level_2 || '',
                state:         comps.administrative_area_level_1 || '',
                country:       comps.country || '',
                pincode:       comps.postal_code || '',
                latitude:      place.geometry.location.lat(),
                longitude:     place.geometry.location.lng(),
                avg_rate:      0,
                property_type: '',
            });
        });
    }


    /* ============================================================
       5) PROPERTY TYPE TOGGLE
    ============================================================ */

    var commercialTypes = ['commercial', 'office', 'shop', 'ground_shop'];

    $('input[name="property_type"]').on('change', function() {
        if (commercialTypes.indexOf($(this).val()) !== -1) {
            $('#asraa-svs-commercial').show();
        } else {
            $('#asraa-svs-commercial').hide();
        }
    });


    /* ============================================================
       6) COMPARABLES TOGGLE
    ============================================================ */

    $('#asraa-svs-comp-toggle').on('click', function() {
        var $body = $('#asraa-svs-comp-body');
        var open  = $body.is(':visible');
        $body.slideToggle(200);
        $(this).text(open ? '＋ Add Comparable Properties (optional)' : '－ Hide Comparables');
    });


    /* ============================================================
       7) FORM SUBMIT
    ============================================================ */

    var $form = $('.asraa-svs-valuation-form');
    if ($form.length) {

        $form.on('submit', function(e) {
            e.preventDefault();

            if (asraaLimitReached()) { showLimitPopup(); return; }

            var $btn   = $form.find('.asraa-svs-btn');
            var $error = $form.find('.asraa-svs-error');
            $error.hide().text('');

            var data = {
                action:        'asraa_svs_get_valuation',
                nonce:         ASRAA_SVS.nonce,
                building_name: $('#asraa-field-building_name').val() || $addressInput.val(),
                area:          $('#asraa-field-area').val(),
                locality:      $('#asraa-field-locality').val(),
                city:          $('#asraa-field-city').val() || $addressInput.val(),
                state:         $('#asraa-field-state').val(),
                country:       $('#asraa-field-country').val(),
                pincode:       $('#asraa-field-pincode').val(),
                latitude:      $('#asraa-field-latitude').val(),
                longitude:     $('#asraa-field-longitude').val(),
                property_type: $form.find('input[name="property_type"]:checked').val(),
                area_size:     $form.find('input[name="area_size"]').val(),
                floor_num:     $form.find('input[name="floor_num"]').val(),
                age_years:     $form.find('input[name="age_years"]').val(),
                monthly_rent:  $form.find('input[name="monthly_rent"]').val(),
                yield_pct:     $form.find('input[name="yield_pct"]').val(),
                comp_1_price:  $form.find('input[name="comp_1_price"]').val(),
                comp_1_area:   $form.find('input[name="comp_1_area"]').val(),
                comp_2_price:  $form.find('input[name="comp_2_price"]').val(),
                comp_2_area:   $form.find('input[name="comp_2_area"]').val(),
                comp_3_price:  $form.find('input[name="comp_3_price"]').val(),
                comp_3_area:   $form.find('input[name="comp_3_area"]').val(),
                name:          $form.find('input[name="name"]').val(),
                email:         $form.find('input[name="email"]').val(),
                phone:         $form.find('input[name="phone"]').val(),
                // Legacy compat
                building:      $('#asraa-field-building_name').val() || $addressInput.val(),
                sqft:          $form.find('input[name="area_size"]').val(),
                config:        $form.find('input[name="property_type"]:checked').val(),
            };

            $btn.prop('disabled', true).addClass('asraa-svs-btn-loading').text('Calculating…');

            $.post(ASRAA_SVS.ajax_url, data, function(response) {
                $btn.prop('disabled', false).removeClass('asraa-svs-btn-loading').text('Get Valuation');
                if (response && response.success) {
                    showResultPopup(response.data);
                    asraaIncreaseAttempts();
                } else {
                    var msg = (response && response.data && response.data.message)
                        ? response.data.message : 'Something went wrong. Please try again.';
                    $error.text(msg).fadeIn(200);
                }
            }).fail(function() {
                $btn.prop('disabled', false).removeClass('asraa-svs-btn-loading').text('Get Valuation');
                $error.text('Server error. Please try again.').fadeIn(200);
            });
        });
    }


    /* ============================================================
       8) CLOSE MODALS – click X / click overlay / swipe down
    ============================================================ */

    function asraaAnimateClose($modal) {
        if ($modal.data('closing')) { return; }
        $modal.data('closing', true);
        var $content = $modal.find('.asraa-svs-modal-content');
        $modal.addClass('asraa-svs-modal--closing-overlay');
        $content.addClass('asraa-svs-modal--closing');
        $content.one('animationend webkitAnimationEnd oAnimationEnd', function() {
            $modal.hide();
            $modal.removeClass('asraa-svs-modal--closing-overlay');
            $content.removeClass('asraa-svs-modal--closing');
            $content.css({ transform: '', transition: '' });
            $modal.data('closing', false);
        });
    }

    $(document).on('click', '.asraa-svs-close', function(e) {
        e.preventDefault();
        asraaAnimateClose($(this).closest('.asraa-svs-modal'));
    });

    $(document).on('click', '.asraa-svs-modal', function(e) {
        if ($(e.target).hasClass('asraa-svs-modal')) { asraaAnimateClose($(this)); }
    });

    var startY = 0, currentY = 0, dragging = false;
    var threshold = 80;

    $('.asraa-svs-modal-content')
        .on('touchstart', function(e) {
            if (e.touches.length !== 1) { return; }
            startY = currentY = e.touches[0].clientY;
            dragging = true;
            $(this).css('transition', 'none');
        })
        .on('touchmove', function(e) {
            if (!dragging) { return; }
            currentY = e.touches[0].clientY;
            var diff = currentY - startY;
            if (diff > 0) { $(this).css('transform', 'translateY(' + diff + 'px)'); }
        })
        .on('touchend touchcancel', function() {
            if (!dragging) { return; }
            dragging = false;
            var diff = currentY - startY;
            var $content = $(this);
            $content.css('transition', 'transform 0.2s ease-out');
            if (diff > threshold) {
                asraaAnimateClose($content.closest('.asraa-svs-modal'));
            } else {
                $content.css('transform', 'translateY(0)');
                setTimeout(function() { $content.css('transition', ''); }, 200);
            }
        });

});
