/* Asraa Smart Valuation Suite – Address Autocomplete v4 */
/* jshint esversion: 5 */
(function ($) {
    'use strict';

    var AC = window.AsraaVSAutocomplete = {

        $input:        null,
        $dropdown:     null,
        $filled:       null,
        timer:         null,
        results:       [],
        active:        -1,
        onSelect:      null,
        _googleInited: false,

        init: function ($input, $dropdown, $filled, onSelect) {
            AC.$input    = $input;
            AC.$dropdown = $dropdown;
            AC.$filled   = $filled;
            AC.onSelect  = (typeof onSelect === 'function') ? onSelect : function () {};
            AC._bindEvents();
        },

        _bindEvents: function () {
            AC.$input.on('input', function () {
                clearTimeout(AC.timer);
                AC._clearHiddenFields();
                AC.$filled.hide();
                var q = $(this).val().trim();
                if (q.length < 2) { AC._close(); return; }
                AC.timer = setTimeout(function () { AC._fetch(q); }, 300);
            });

            AC.$input.on('keydown', function (e) {
                var $items = AC.$dropdown.find('.asraa-svs-ac-item');
                if (!$items.length) { return; }
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    AC.active = Math.min(AC.active + 1, $items.length - 1);
                    $items.removeClass('active').eq(AC.active).addClass('active');
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    AC.active = Math.max(AC.active - 1, 0);
                    $items.removeClass('active').eq(AC.active).addClass('active');
                } else if (e.key === 'Enter') {
                    if (AC.active >= 0 && AC.results[AC.active]) {
                        e.preventDefault();
                        AC._apply(AC.results[AC.active]);
                    }
                } else if (e.key === 'Escape') {
                    AC._close();
                }
            });

            AC.$input.on('blur', function () {
                setTimeout(function () { AC._close(); }, 200);
            });
        },

        _fetch: function (q) {
            if (!window.ASRAA_SVS) { return; }
            var action = ASRAA_SVS.action_autocomplete || 'asraa_svs_autocomplete';
            $.get(ASRAA_SVS.ajax_url, {
                action: action,
                nonce:  ASRAA_SVS.nonce,
                q:      q
            }, function (resp) {
                if (resp && resp.success && resp.data && resp.data.length) {
                    AC._render(resp.data);
                } else {
                    AC._close();
                    if (ASRAA_SVS.google_places_key &&
                        typeof google !== 'undefined' &&
                        google.maps && google.maps.places) {
                        AC._initGoogle();
                    }
                }
            });
        },

        _render: function (items) {
            AC.$dropdown.empty();
            if (!items || !items.length) { AC._close(); return; }
            AC.results = items;
            AC.active  = -1;
            $.each(items, function (idx, item) {
                var $item = $('<div class="asraa-svs-ac-item"></div>').text(item.label || item.building_name || '');
                if (item.avg_rate) {
                    $item.append($('<small></small>').text(' – Rate: ' + Number(item.avg_rate).toLocaleString()));
                }
                $item.on('mousedown', function (e) {
                    e.preventDefault();
                    AC._apply(item);
                });
                AC.$dropdown.append($item);
            });
            AC.$dropdown.show();
        },

        _apply: function (item) {
            AC._fillHidden('building_name', item.building_name);
            AC._fillHidden('area',          item.area);
            AC._fillHidden('locality',      item.locality);
            AC._fillHidden('city',          item.city);
            AC._fillHidden('state',         item.state);
            AC._fillHidden('country',       item.country);
            AC._fillHidden('pincode',       item.pincode);
            AC._fillHidden('latitude',      item.latitude);
            AC._fillHidden('longitude',     item.longitude);

            if (item.property_type) {
                var $radio = $('input[name="property_type"][value="' + item.property_type + '"]');
                if ($radio.length) { $radio.prop('checked', true).trigger('change'); }
            }

            var badge = item.label || item.building_name || '';
            if (item.avg_rate) { badge += ' · Rate: ' + Number(item.avg_rate).toLocaleString(); }
            AC.$filled.text('✓ ' + badge).show();
            AC.$input.val(item.label || item.building_name || '');
            AC._close();

            if (typeof AC.onSelect === 'function') { AC.onSelect(item); }
        },

        _fillHidden: function (name, val) {
            $('#asraa-field-' + name).val(val || '');
        },

        _clearHiddenFields: function () {
            $.each(['building_name','area','locality','city','state','country','pincode','latitude','longitude'], function (i, f) {
                AC._fillHidden(f, '');
            });
        },

        _close: function () {
            AC.$dropdown.hide().empty();
            AC.results = [];
            AC.active  = -1;
        },

        loadGoogleMaps: function (callback, _retries) {
            // If the Maps API (with Places) is already available, call back immediately.
            if (window.google && google.maps && google.maps.places) {
                callback();
                return;
            }
            // If the central PHP loader has enqueued Maps but it hasn't finished
            // executing yet, poll until it's ready rather than injecting a second
            // <script> tag that would trigger a "Google Maps API loaded multiple
            // times on the page" error.
            var retries = (_retries === undefined) ? 0 : _retries;
            if (retries >= 25) {
                // ~5 seconds elapsed – Maps API did not load (network error / bad key).
                return;
            }
            setTimeout(function () { AC.loadGoogleMaps(callback, retries + 1); }, 200);
        },

        _initGoogle: function () {
            if (AC._googleInited) { return; }
            if (typeof google === 'undefined' || !google.maps || !google.maps.places) { return; }
            AC._googleInited = true;
            var inputEl = AC.$input[0];
            var ac = new google.maps.places.Autocomplete(inputEl, {});
            ac.addListener('place_changed', function () {
                var place = ac.getPlace();
                if (!place || !place.geometry) { return; }
                var comps = {};
                $.each(place.address_components || [], function (i, c) {
                    $.each(c.types, function (j, t) { comps[t] = c.long_name; });
                });
                AC._apply({
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
                    property_type: ''
                });
            });
        }
    };

}(jQuery));
