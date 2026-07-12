/**
 * Asraa CRM – Premium AI Assistant
 *
 * Features:
 *   - Welcome modal popup (2 s delay, localStorage dismissal)
 *   - Floating AI chat button  (bottom-right)
 *   - Conversational lead capture with structured + free-text input
 *   - Rule-based intent extraction  (location / budget / property type)
 *   - Optional AI API proxy  (OpenAI / local via /wp-json/asraa/v1/ai-chat)
 *   - Property search  (/wp-json/asraa/v1/search-properties)
 *   - CRM lead creation  (/wp-json/asraa/v1/lead)
 *   - CRM lead save + lead-qualified action (server-side)
 *
 * Config injected via wp_localize_script as window.asraaAI:
 *   apiUrl       – lead capture endpoint
 *   searchUrl    – property search endpoint
 *   aiChatUrl    – AI chat proxy endpoint
 *   nonce        – wp_rest nonce
 *   provider     – "rule_based" | "openai" | "local"
 *   popupLocations – array of location strings for welcome popup buttons
 */
(function () {
if (window.ASRAA_AI_LOADED) {
    return;
}
window.ASRAA_AI_LOADED = true;
document.querySelectorAll('.whatsapp-widget,.wa-floating,#whatsapp-chat,#joinchat,.joinchat,.joinchat--chatbox').forEach(function (el) {
    el.style.display = 'none';
});

// Google Maps addDomListener compatibility shim.
// The addDomListener method was deprecated in Maps JS API v3.44 and
// removed later. Some older plugins/themes may still call it and
// cause console errors. Patch it once when the Maps API object exists.
var GOOGLE_MAPS_RETRY_DELAY_MS = 1200; // interval to re-check if Maps API has loaded
(function patchGoogleMaps() {
    var win = window;
    if (win.google && win.google.maps && win.google.maps.event) {
        if (typeof win.google.maps.event.addDomListener !== 'function') {
            win.google.maps.event.addDomListener = function (el, evName, callback) {
                if (el && typeof el.addEventListener === 'function') {
                    el.addEventListener(evName, callback);
                }
            };
        }
        return; // already patched or native
    }
    // Maps not yet loaded – try again after a short delay.
    setTimeout(patchGoogleMaps, GOOGLE_MAPS_RETRY_DELAY_MS);
}());

(function () {
    'use strict';

    /* ------------------------------------------------------------------ */
    /*  CONFIG                                                              */
    /* ------------------------------------------------------------------ */
    var cfg      = window.asraaAI || {};
    var API_URL      = cfg.apiUrl      || '/wp-json/asraa/v1/lead';
    var SEARCH_URL   = cfg.searchUrl   || '/wp-json/asraa/v1/search-properties';
    var AI_CHAT_URL  = cfg.aiChatUrl   || '/wp-json/asraa/v1/ai-chat';
    var VALUATION_URL = cfg.valuationUrl || '/wp-json/asraa/v1/valuation';
    var NONCE        = cfg.nonce       || '';
    var AI_PROVIDER  = cfg.provider    || 'rule_based';
    var POPUP_LOCATIONS = Array.isArray(cfg.popupLocations) && cfg.popupLocations.length
        ? cfg.popupLocations.filter(function (item) { return String(item || '').toLowerCase() !== 'dubai'; })
        : ['Buy', 'Sell', 'Investment', 'Commercial'];
    if (!POPUP_LOCATIONS.length) POPUP_LOCATIONS = ['Buy', 'Sell', 'Investment', 'Commercial'];

    var POPUP_KEY       = 'asraa_ai_popup_dismissed';
    var CHAT_KEY        = 'asraa_ai_chat_state';
    var SESSION_KEY     = 'asraa_ai_session';
    var MAX_MSG_LEN     = 500;   // max user message characters
    var MAX_NAME_LEN    = 40;    // max detected name length before treating as query
    var MAX_CITY_LEN    = 100;   // max characters for AI-extracted city field
    var TYPING_DELAY_MS = 600;
    var aiDebounceTimer = null;
    var aiRequestInFlight = false;
    var RATES = { AED: 0.044, USD: 0.012, GBP: 0.0095 };
    var ASRAA_CURRENCY = window.ASRAA_CURRENCY || 'INR';
    var currencyDetectPromise = null;
    window.ASRAA_CURRENCY = ASRAA_CURRENCY;

    /* ------------------------------------------------------------------ */
    /*  STATE                                                               */
    /* ------------------------------------------------------------------ */
    var state = {
        open:           false,
        step:           'idle',  // idle|greeting|name|phone|email|location|property_type|budget|advisory|searching|done|valuation|completed
        initialChoice:  '',      // intent from popup / quick-reply
        name:           '',
        phone:          '',
        email:          '',
        emailAsked:     false,
        location:       '',
        city:           '',
        area:           '',
        budget:         '',
        type:           '',
        propertyType:   '',
        leadId:         0,
        history:        [],      // [{role:'user'|'assistant', content:'...'}]
        ai_mode:        '',      // buy|sell|invest
        leadConsent:    false,
        intent:         '',
        valuation_step: 0,       // 0=inactive, 1=ask location, 2=ask config, 3=ask area
        val_location:   '',
        val_config:     '',
        val_area:       ''
    };

    /* ------------------------------------------------------------------ */
    /*  CURRENCY & BUDGET HELPERS                                           */
    /* ------------------------------------------------------------------ */

    function detectCurrency() {
        currencyDetectPromise = new Promise(function (resolve) {
            var timeout = setTimeout(function () {
                ASRAA_CURRENCY = 'INR';
                window.ASRAA_CURRENCY = 'INR';
                resolve(ASRAA_CURRENCY);
            }, 2500);

            fetch('https://ipapi.co/json/')
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    clearTimeout(timeout);
                    var code = (data && data.country_code) ? String(data.country_code).toUpperCase() : '';
                    if (code === 'AE') ASRAA_CURRENCY = 'AED';
                    else if (code === 'GB') ASRAA_CURRENCY = 'GBP';
                    else if (code === 'US') ASRAA_CURRENCY = 'USD';
                    else ASRAA_CURRENCY = 'INR';
                    window.ASRAA_CURRENCY = ASRAA_CURRENCY;
                    resolve(ASRAA_CURRENCY);
                })
                .catch(function () {
                    clearTimeout(timeout);
                    ASRAA_CURRENCY = 'INR';
                    window.ASRAA_CURRENCY = 'INR';
                    resolve(ASRAA_CURRENCY);
                });
        });
        return currencyDetectPromise;
    }

    function asraa_get_currency() {
        return ASRAA_CURRENCY || 'INR';
    }

    function asraa_convert_inr(price, currency) {
        var n = Number(price) || 0;
        if (!currency || currency === 'INR') return n;
        if (!Object.prototype.hasOwnProperty.call(RATES, currency)) return n;
        return n * RATES[currency];
    }

    function asraa_budget_to_inr(value, currency) {
        var n = Number(value) || 0;
        if (!currency || currency === 'INR') return n;
        if (!Object.prototype.hasOwnProperty.call(RATES, currency) || RATES[currency] <= 0) return n;
        return n / RATES[currency];
    }

    /**
     * Format a numeric price with its currency symbol.
     * @param  {number} price
     * @param  {string} currency  'AED' | 'INR' | 'USD'
     * @return {string}
     */
    function asraa_format_price(price, currency) {
        var n = Number(price);
        if (currency === 'INR') return '\u20b9' + n.toLocaleString('en-IN');
        if (currency === 'AED') return 'AED ' + n.toLocaleString();
        if (currency === 'GBP') return '\u00a3' + n.toLocaleString('en-GB');
        if (currency === 'USD') return '$' + n.toLocaleString('en-US');
        return n.toLocaleString();
    }

    /**
     * Parse a budget text like "1.5 cr", "50 lakh", "2 million", "1500000".
     * Uses word-boundary checks so incidental substrings (e.g. 'crescent') are ignored.
     * Returns a raw numeric value.
     * @param  {string} text
     * @return {number}
     */
    function asraa_parse_budget(text) {
    var t = (text || '').toLowerCase().trim();

    // Ignore BHK values and non-budget inputs
    if (/\b[1-9]\s*bhk\b/.test(t)) {
        return 0;
    }

    // Valid budget formats only
    if (!/(cr|crore|crores|lakh|lakhs|million|k|\d{4,})/i.test(t)) {
        return 0;
    }

    var base = parseFloat(t.replace(/[^0-9.]/g, '')) || 0;

    if (/\bcr(ores?)?\b/.test(t)) return base * 10000000;
    if (/\blakh(s)?\b/.test(t)) return base * 100000;
    if (/\bmillion\b/.test(t)) return base * 1000000;
    if (/\bk\b/.test(t)) return base * 1000;

    return base;
}

    function ensureCurrencyReady(callback) {
        if (currencyDetectPromise && typeof currencyDetectPromise.then === 'function') {
            currencyDetectPromise.then(function () { callback(); });
            return;
        }
        callback();
    }

    function parseLocationParts(input) {
        var raw = String(input || '').trim().replace(/\s+/g, ' ');
        if (!raw) return { city: '', area: '', location: '' };
        if (raw.indexOf(',') !== -1) {
            var commaParts = raw.split(',');
            var areaFromComma = String(commaParts[0] || '').trim();
            var cityFromComma = String(commaParts[1] || '').trim();
            return { city: cityFromComma, area: areaFromComma, location: raw };
        }
        var knownCities = ['mumbai', 'dubai', 'delhi', 'bangalore', 'pune', 'hyderabad', 'chennai', 'kolkata'];
        var lower = raw.toLowerCase();
        for (var i = 0; i < knownCities.length; i++) {
            var cityName = knownCities[i];
            if (lower === cityName || lower.indexOf(cityName + ' ') === 0) {
                var area = raw.substring(cityName.length).trim();
                return { city: cityName.charAt(0).toUpperCase() + cityName.slice(1), area: area, location: raw };
            }
        }
        return { city: '', area: raw, location: raw };
    }

    function setLocationState(input) {
        var parsed = parseLocationParts(input);
        state.location = parsed.location;
        state.city = parsed.city;
        state.area = parsed.area;
    }

    function getNextBuyStep() {
        if (!state.location) return 'location';
        if (!state.budget) return 'budget';
        if (!state.propertyType) return 'property_type';
        return 'searching';
    }

    function setPropertyType(typeValue) {
        state.propertyType = typeValue || '';
        state.type = state.propertyType;
    }

    function askBudgetQuestion(context) {
        var currency = asraa_get_currency();
        askWithAI(
            'What is your *budget* in ' + currency + '?',
            context || 'budget_prompt'
        );
    }

    function promptNextBuyStep() {
        var next = getNextBuyStep();
        state.step = next;
        sessionSave();

        if (next === 'location') {
            askWithAI(
                'Which *location* are you interested in?\n_(e.g. Mumbai Mira Road, Dubai Marina, Downtown, JVC)_',
                'location_prompt'
            );
            return;
        }
        if (next === 'budget') {
            ensureCurrencyReady(function () {
                askBudgetQuestion('budget_prompt');
            });
            return;
        }
        if (next === 'property_type') {
            botTypeThen(
                'What *type of property* are you looking for?',
                function () {
                    showQuickReplies(['ðŸ¢ Apartment', 'ðŸ¡ Villa', 'ðŸ˜ Townhouse', 'ðŸ¬ Office', 'ðŸ— Studio']);
                },
                600
            );
            return;
        }
        hideQuickReplies();
        submitAndSearch();
    }

    /* ------------------------------------------------------------------ */
    /*  INLINE STYLES                                                       */
    /* ------------------------------------------------------------------ */
    var CSS = [
        /* â”€â”€ Reset â”€â”€ */
        '#asraa-ai-overlay,#asraa-ai-popup,#asraa-ai-bubble,#asraa-ai-window{',
            'box-sizing:border-box;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;',
        '}',

        /* â”€â”€ Hide WhatsApp widgets when AI is active â”€â”€ */
        '.whatsapp-widget,.wa-floating,#whatsapp-chat{display:none!important;}',

        /* â”€â”€ Dark overlay â”€â”€ */
        '#asraa-ai-overlay{',
            'position:fixed;inset:0;z-index:99998;',
            'background:rgba(0,0,0,.6);',
            'display:flex;align-items:center;justify-content:center;',
            'padding:16px;',
            'opacity:0;transition:opacity .3s;pointer-events:none;',
        '}',
        '#asraa-ai-overlay.asraa-ai-show{opacity:1;pointer-events:auto;}',

        /* â”€â”€ Popup modal â”€â”€ */
        '#asraa-ai-popup{',
            'background:#fff;border-radius:20px;',
            'padding:32px 28px 24px;',
            'max-width:420px;width:100%;',
            'box-shadow:0 24px 64px rgba(0,0,0,.35);',
            'text-align:center;',
            'transform:translateY(40px) scale(.95);opacity:0;',
            'transition:transform .35s cubic-bezier(.34,1.56,.64,1),opacity .35s;',
        '}',
        '#asraa-ai-overlay.asraa-ai-show #asraa-ai-popup{transform:none;opacity:1;}',

        /* Popup avatar */
        '#asraa-ai-popup-avatar{',
            'width:72px;height:72px;border-radius:50%;',
            'background:linear-gradient(135deg,#1a1a2e 0%,#16213e 50%,#0f3460 100%);',
            'display:inline-flex;align-items:center;justify-content:center;',
            'margin-bottom:16px;',
            'box-shadow:0 4px 20px rgba(15,52,96,.4);',
        '}',
        '#asraa-ai-popup-avatar svg{width:36px;height:36px;}',

        '#asraa-ai-popup-title{',
            'font-size:22px;font-weight:800;color:#1a1a2e;margin-bottom:6px;',
        '}',
        '#asraa-ai-popup-subtitle{',
            'font-size:14px;color:#666;margin-bottom:24px;line-height:1.5;',
        '}',

        /* Popup buttons grid */
        '#asraa-ai-popup-btns{',
            'display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:20px;',
        '}',
        '.asraa-ai-popup-btn{',
            'background:linear-gradient(135deg,#1a1a2e,#0f3460);',
            'color:#fff;border:none;border-radius:12px;',
            'padding:12px 16px;font-size:14px;font-weight:600;cursor:pointer;',
            'transition:opacity .15s,transform .15s;',
            'text-align:center;',
        '}',
        '.asraa-ai-popup-btn:hover{opacity:.88;transform:translateY(-1px);}',

        /* Close button */
        '#asraa-ai-popup-close{',
            'background:none;border:none;cursor:pointer;',
            'color:#999;font-size:12px;',
            'transition:color .15s;padding:0;',
        '}',
        '#asraa-ai-popup-close:hover{color:#333;}',

        /* â”€â”€ Floating bubble â”€â”€ */
        '#asraa-ai-bubble{',
            'position:fixed;bottom:24px;right:24px;z-index:99990;',
            'width:60px;height:60px;border-radius:50%;',
            'background:#25D366;',
            'display:flex;align-items:center;justify-content:center;',
            'cursor:pointer;box-shadow:0 4px 20px rgba(37,211,102,.45);',
            'border:none;padding:0;',
            'transition:transform .2s,box-shadow .2s;',
        '}',
        '#asraa-ai-bubble:hover{transform:scale(1.1);box-shadow:0 6px 26px rgba(37,211,102,.55);}',
        '#asraa-ai-bubble svg{width:30px;height:30px;}',
        /* Pulse ring */
        '#asraa-ai-bubble::before{',
            'content:"";position:absolute;',
            'width:60px;height:60px;border-radius:50%;',
            'background:rgba(37,211,102,.35);',
            'animation:asraa-ai-pulse 2.2s ease-out infinite;',
        '}',
        '@keyframes asraa-ai-pulse{',
            '0%{transform:scale(1);opacity:.9}',
            '100%{transform:scale(2.4);opacity:0}',
        '}',

        /* â”€â”€ Chat window â”€â”€ */
        '#asraa-ai-window{',
            'position:fixed;bottom:96px;right:24px;z-index:99991;',
            'width:420px;max-width:95vw;',
            'background:#fff;border-radius:18px;',
            'box-shadow:0 12px 40px rgba(0,0,0,.22);',
            'display:flex;flex-direction:column;overflow:hidden;',
            'font-size:14px;',
            'transform:scale(.88) translateY(24px);opacity:0;',
            'transition:transform .4s cubic-bezier(.34,1.56,.64,1),opacity .4s,top .5s,left .5s,bottom .5s,right .5s,width .5s,border-radius .5s;',
            'pointer-events:none;',
        '}',
        '#asraa-ai-window.asraa-ai-open{',
            'transform:scale(1) translateY(0);opacity:1;pointer-events:auto;',
        '}',
        /* â”€â”€ Centered modal state â”€â”€ */
        '#asraa-ai-window.asraa-ai-modal-centered{',
            'position:fixed;top:50%;left:50%;bottom:auto;right:auto;',
            'transform:translate(-50%,-50%) scale(1);',
            'width:420px;max-width:95vw;',
            'height:70vh;max-height:70vh;',
            'border-radius:16px;',
            'box-shadow:0 24px 64px rgba(0,0,0,.35);',
            'opacity:1;pointer-events:auto;',
        '}',
        /* Overlay backdrop for centered modal */
        '#asraa-ai-backdrop{',
            'position:fixed;inset:0;z-index:99990;',
            'background:rgba(0,0,0,.5);',
            'opacity:0;transition:opacity .3s;pointer-events:none;',
        '}',
        '#asraa-ai-backdrop.asraa-ai-show{opacity:1;pointer-events:auto;}',

        /* Chat header */
        '#asraa-ai-header{',
            'background:linear-gradient(135deg,#075E54 0%,#128C7E 100%);',
            'padding:14px 16px;display:flex;align-items:center;gap:12px;color:#fff;',
        '}',
        '#asraa-ai-header-avatar{',
            'width:40px;height:40px;border-radius:50%;',
            'background:rgba(255,255,255,.15);',
            'display:flex;align-items:center;justify-content:center;flex-shrink:0;',
        '}',
        '#asraa-ai-header-avatar svg{width:22px;height:22px;}',
        '#asraa-ai-header-info{flex:1;}',
        '#asraa-ai-header-name{font-weight:700;font-size:15px;}',
        '#asraa-ai-header-status{font-size:11px;opacity:.8;display:flex;align-items:center;gap:4px;}',
        '.asraa-ai-status-dot{',
            'width:6px;height:6px;border-radius:50%;background:#4ade80;display:inline-block;',
        '}',
        '#asraa-ai-close{',
            'background:none;border:none;cursor:pointer;',
            'color:#fff;opacity:.75;padding:4px;line-height:1;font-size:18px;',
            'transition:opacity .15s;',
        '}',
        '#asraa-ai-close:hover{opacity:1;}',

        /* Chat body */
        '#asraa-ai-body{',
            'flex:1;overflow-y:auto;padding:16px;',
            'background:#ECE5DD;',
            'display:flex;flex-direction:column;gap:10px;',
            'max-height:340px;',
            'scroll-behavior:smooth;-webkit-overflow-scrolling:touch;',
        '}',
        '#asraa-ai-body::-webkit-scrollbar{width:4px;}',
        '#asraa-ai-body::-webkit-scrollbar-track{background:transparent;}',
        '#asraa-ai-body::-webkit-scrollbar-thumb{background:rgba(0,0,0,.18);border-radius:2px;}',

        /* Messages */
        '.asraa-ai-msg{',
            'max-width:75%;padding:9px 13px;border-radius:14px;',
            'line-height:1.5;word-wrap:break-word;font-size:13.5px;',
            'animation:asraa-ai-msg-in .2s ease;',
        '}',
        '@keyframes asraa-ai-msg-in{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}',
        '.asraa-ai-msg-bot{',
            'background:#fff;color:#111;',
            'border-bottom-left-radius:3px;align-self:flex-start;',
            'box-shadow:0 1px 3px rgba(0,0,0,.1);',
        '}',
        '.asraa-ai-msg-user{',
            'background:#DCF8C6;color:#111;',
            'border-bottom-right-radius:3px;align-self:flex-end;',
        '}',

        /* Quick replies */
        '#asraa-ai-quick-replies{',
            'padding:8px 12px 4px;display:flex;flex-wrap:wrap;gap:8px;',
            'background:#ECE5DD;',
        '}',
        '.asraa-ai-qr-btn{',
            'background:#fff;border:1.5px solid #25D366;',
            'color:#075E54;border-radius:20px;',
            'padding:6px 14px;font-size:12.5px;font-weight:600;',
            'cursor:pointer;transition:background .15s,color .15s;',
        '}',
        '.asraa-ai-qr-btn:hover{background:#25D366;color:#fff;}',

        /* Footer */
        '#asraa-ai-footer{',
            'padding:10px 12px;background:#fff;',
            'display:flex;align-items:center;gap:8px;',
            'border-top:1px solid #eee;',
            'position:sticky;bottom:0;z-index:1;',
        '}',
        '#asraa-ai-input{',
            'flex:1;border:1.5px solid #e0e0e0;border-radius:24px;',
            'padding:8px 14px;font-size:13.5px;outline:none;',
            'transition:border-color .15s;',
            'background:#f8f8f8;',
        '}',
        '#asraa-ai-input:focus{border-color:#25D366;background:#fff;}',
        '#asraa-ai-send{',
            'background:#25D366;',
            'border:none;border-radius:50%;',
            'width:38px;height:38px;cursor:pointer;flex-shrink:0;',
            'display:flex;align-items:center;justify-content:center;',
            'transition:opacity .15s;',
        '}',
        '#asraa-ai-send:hover{opacity:.85;}',
        '#asraa-ai-send svg{width:17px;height:17px;}',

        /* Typing indicator */
        '.asraa-ai-typing{',
            'display:flex;gap:5px;padding:10px 14px;align-self:flex-start;',
            'background:#fff;border-radius:14px;border-bottom-left-radius:3px;',
            'box-shadow:0 1px 3px rgba(0,0,0,.1);',
        '}',
        '.asraa-ai-typing span{',
            'width:8px;height:8px;border-radius:50%;background:#aaa;display:inline-block;',
            'animation:asraa-ai-bounce .9s infinite;',
        '}',
        '.asraa-ai-typing span:nth-child(2){animation-delay:.15s;}',
        '.asraa-ai-typing span:nth-child(3){animation-delay:.3s;}',
        '@keyframes asraa-ai-bounce{0%,80%,100%{transform:translateY(0)}40%{transform:translateY(-6px)}}',

        /* Property result cards */
        '.asraa-ai-prop-card{',
            'background:#fff;border-radius:12px;overflow:hidden;',
            'box-shadow:0 2px 10px rgba(0,0,0,.1);',
            'width:100%;align-self:stretch;',
        '}',
        '.asraa-ai-prop-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;width:100%;}',
        '.asraa-ai-prop-img{width:100%;height:110px;object-fit:cover;display:block;}',
        '.asraa-ai-prop-body{padding:10px 12px 6px;}',
        '.asraa-ai-prop-title{',
            'font-weight:700;font-size:13px;color:#111;',
            'overflow:hidden;text-overflow:ellipsis;white-space:nowrap;margin-bottom:3px;',
        '}',
        '.asraa-ai-prop-price{',
            'color:#0f3460;font-weight:700;font-size:13px;margin-bottom:2px;',
        '}',
        '.asraa-ai-prop-location{color:#777;font-size:12px;margin-bottom:6px;}',
        '.asraa-ai-prop-view{',
            'display:block;margin:0 12px 12px;',
            'background:linear-gradient(135deg,#1a1a2e,#0f3460);',
            'color:#fff;text-align:center;padding:7px;border-radius:8px;',
            'font-size:12px;font-weight:700;text-decoration:none;',
            'transition:opacity .15s;',
        '}',
        '.asraa-ai-prop-view:hover{opacity:.85;color:#fff;}',

        /* Mobile */
        '@media(max-width:480px){',
            '#asraa-ai-window{',
                'width:100%;max-width:100%;',
                'bottom:0;right:0;',
                'border-radius:18px 18px 0 0;',
            '}',
            '#asraa-ai-window.asraa-ai-modal-centered{',
                'top:0;left:0;right:0;bottom:0;',
                'transform:none;width:100vw;max-width:100vw;max-height:100vh;',
                'border-radius:0;',
            '}',
            '#asraa-ai-body{max-height:55vh;}',
            '.asraa-ai-prop-grid{grid-template-columns:1fr;}',
            '#asraa-ai-bubble{right:16px;bottom:16px;}',
            '#asraa-ai-popup{border-radius:16px;padding:24px 18px 18px;}',
            '#asraa-ai-popup-btns{grid-template-columns:1fr 1fr;}',
        '}'
    ].join('');

    /* ------------------------------------------------------------------ */
    /*  SVG ICONS                                                           */
    /* ------------------------------------------------------------------ */
    var AI_SVG = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">'
        + '<circle cx="12" cy="8" r="4"/>'
        + '<path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/>'
        + '<path d="M17 3l1.5 1.5M7 3L5.5 4.5M12 2v1.5"/>'
        + '</svg>';

    var SEND_SVG = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#fff">'
        + '<path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>';

    /* ------------------------------------------------------------------ */
    /*  INIT DOM                                                            */
    /* ------------------------------------------------------------------ */
    function init() {
        if (document.getElementById('asraa-ai-window')) {
            return;
        }
        detectCurrency();
        injectStyles();
        buildBackdrop();
        buildBubble();
        buildChatWindow();
        maybeOpenCentered();
    }

    function injectStyles() {
        if (document.getElementById('asraa-ai-css')) return;
        var style = document.createElement('style');
        style.id  = 'asraa-ai-css';
        style.textContent = CSS;
        document.head.appendChild(style);
    }

    /* â”€â”€ Backdrop for centered modal â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    function buildBackdrop() {
        var bd = document.createElement('div');
        bd.id = 'asraa-ai-backdrop';
        bd.addEventListener('click', function () {
            dismissCentered();
        });
        document.body.appendChild(bd);
    }

    /**
     * Show the chat window centered on page load after 1.5 s (once per session).
     */
    function maybeOpenCentered() {
        if (localStorage.getItem(POPUP_KEY)) return;
        setTimeout(function () {
            openChatCentered();
        }, 2000);
    }

    /** Open the chat window in centered modal position. */
    function openChatCentered() {
        var win = document.getElementById('asraa-ai-window');
        var bd  = document.getElementById('asraa-ai-backdrop');
        win.classList.add('asraa-ai-open', 'asraa-ai-modal-centered');
        if (bd) bd.classList.add('asraa-ai-show');
        if (state.step === 'idle') {
            state.step = 'greeting';
            startGreeting();
        }
        state.open = true;
    }

    /** Dismiss the centered modal: mark dismissed and hide (do not destroy chat). */
    function dismissCentered() {
        localStorage.setItem(POPUP_KEY, '1');
        shrinkToBubble();
    }

    /**
     * Transition the chat window from the centered position to the
     * bottom-right floating position.
     */
    function shrinkToBubble() {
        var win = document.getElementById('asraa-ai-window');
        var bd  = document.getElementById('asraa-ai-backdrop');
        var bubble = document.getElementById('asraa-ai-bubble');
        if (!win.classList.contains('asraa-ai-modal-centered')) return;
        win.classList.remove('asraa-ai-modal-centered');
        if (bd) bd.classList.remove('asraa-ai-show');
        if (bubble) bubble.style.display = '';
        localStorage.setItem(POPUP_KEY, '1');
    }

    /* â”€â”€ Floating bubble â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    function buildBubble() {
        var bubble = document.createElement('button');
        bubble.id = 'asraa-ai-bubble';
        bubble.setAttribute('aria-label', 'Open Asraa AI Assistant');
        bubble.innerHTML = AI_SVG;
        // Hide until the centered modal has been interacted with once
        if (!localStorage.getItem(POPUP_KEY)) {
            bubble.style.display = 'none';
        }
        bubble.addEventListener('click', toggleChat);
        document.body.appendChild(bubble);
    }

    /* â”€â”€ Chat window â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    function buildChatWindow() {
        var win = document.createElement('div');
        win.id = 'asraa-ai-window';
        win.setAttribute('role', 'dialog');
        win.setAttribute('aria-label', 'Asraa AI Assistant');
        win.innerHTML = [
            '<div id="asraa-ai-header">',
                '<div id="asraa-ai-header-avatar">', AI_SVG, '</div>',
                '<div id="asraa-ai-header-info">',
                    '<div id="asraa-ai-header-name">Asraa AI Assistant 👋</div>',
                    '<div id="asraa-ai-header-status">',
                        '<span class="asraa-ai-status-dot"></span> Online',
                    '</div>',
                '</div>',
                '<button id="asraa-ai-close" aria-label="Close">&#x2715;</button>',
            '</div>',
            '<div id="asraa-ai-body"></div>',
            '<div id="asraa-ai-quick-replies"></div>',
            '<div id="asraa-ai-footer">',
                '<input id="asraa-ai-input" type="text"',
                    ' placeholder="Type a message\u2026" autocomplete="off" />',
                '<button id="asraa-ai-send" aria-label="Send">', SEND_SVG, '</button>',
            '</div>'
        ].join('');
        document.body.appendChild(win);

        document.getElementById('asraa-ai-close').addEventListener('click', closeChat);
        document.getElementById('asraa-ai-send').addEventListener('click', handleSend);
        document.getElementById('asraa-ai-input').addEventListener('keydown', function (e) {
            if (e.key === 'Enter') handleSend();
        });
    }

    /* ------------------------------------------------------------------ */
    /*  OPEN / CLOSE / TOGGLE                                               */
    /* ------------------------------------------------------------------ */
    function openChat() {
        state.open = true;
        var win = document.getElementById('asraa-ai-window');
        win.classList.add('asraa-ai-open');
        if (state.step === 'idle') {
            state.step = 'greeting';
            startGreeting();
        }
    }

    function closeChat() {
        state.open = false;
        var win = document.getElementById('asraa-ai-window');
        win.classList.remove('asraa-ai-open');
        // If it was centered, also remove centered class and backdrop
        if (win.classList.contains('asraa-ai-modal-centered')) {
            win.classList.remove('asraa-ai-modal-centered');
            var bd = document.getElementById('asraa-ai-backdrop');
            if (bd) bd.classList.remove('asraa-ai-show');
            var bubble = document.getElementById('asraa-ai-bubble');
            if (bubble) bubble.style.display = '';
            localStorage.setItem(POPUP_KEY, '1');
        }
    }

    function toggleChat() {
        if (state.open) { closeChat(); } else { openChat(); }
    }

    /* ------------------------------------------------------------------ */
    /*  CONVERSATION FLOW                                                   */
    /* ------------------------------------------------------------------ */
    function startGreeting() {
        sessionRestore();  // reload any previously collected data
        if (state.name || state.phone || state.email || state.location || state.budget || state.propertyType) {
            botTypeThen('Welcome back! Let’s continue from where we left off.', function () {
                continueGuidedFlow();
            }, 700);
            return;
        }
        state.step = 'name';
        botTypeThen('Hi! I’m *Asraa AI Assistant*. 👋\n\nMay I know your *name*?', null, 700);
    }

    /**
     * Handles the location/topic chosen from popup or quick-reply buttons.
     * Routes to the correct AI mode (buy / sell / invest).
     */
    function sendInitialChoice(loc) {
        if (state.step !== 'greeting') return;
        state.initialChoice = loc;
        hideQuickReplies();
        addUserMsg(loc);

        var mode = detectMode(loc);
        state.ai_mode = mode;
        state.intent = mode;
        sessionSave();

        if (mode === 'sell') {
            startValuationFlow();
            return;
        }
        if (mode === 'invest') {
            botTypeThen(
                '📈 Great choice! I can help with ROI-focused options.\n\nShare your *location*, *budget*, and *property type* to shortlist investment-friendly properties.',
                null, 800
            );
            return;
        }
        // default: buy
        if (!state.location && /mumbai|dubai|bangalore|pune|delhi|hyderabad|chennai/i.test(loc)) {
            setLocationState(loc);
            sessionSave();
        }
        botTypeThen('Great! I\'ll help you find properties in *' + escHtml(loc) + '*. 🏙️', function () {
            promptNextBuyStep();
        }, 700);
    }

    /**
     * Handle a quick-reply label chosen inside the chat window (from greeting step).
     */
    function handleQuickReply(label) {
        hideQuickReplies();
        addUserMsg(label);
        // After first interaction, shrink centered modal to floating bubble
        shrinkToBubble();

        switch (state.step) {
            case 'property_type':
                var clean = stripLeadingEmoji(label);
                setPropertyType(clean);
                state.step = 'budget';
                sessionSave();
                ensureCurrencyReady(function () {
                    askBudgetQuestion('type_collected');
                });
                break;
            case 'done':
                handleLeadOptinChoice(label);
                break;
        }
    }

    function sendInitialChoiceFromLabel(label) {
        var clean = stripLeadingEmoji(label);
        var mode  = detectMode(clean);
        state.initialChoice = clean;
        state.ai_mode       = mode;
        state.intent        = mode;
        sessionSave();

        if (mode === 'sell') {
            startValuationFlow();
            return;
        }
        if (mode === 'invest') {
            botTypeThen(
                '📈 Smart move! I can guide you on ROI and growth corridors.\n\nWhat *location*, *budget*, and *property type* are you considering?',
                null, 800
            );
            return;
        }
        botTypeThen('Great! I\'ll look for *' + escHtml(clean) + '* properties. 🏙️', function () {
            promptNextBuyStep();
        }, 700);
    }

    /**
     * Main send handler — processes typed input according to current step.
     */
    function handleSend() {
        var input = document.getElementById('asraa-ai-input');
        var val   = input.value.trim();

        // Enforce message length limit
        if (val.length > MAX_MSG_LEN) {
            val = val.substring(0, MAX_MSG_LEN);
        }

        if (!val) return;
        if (val) { input.value = ''; }
        if (val) { addUserMsg(val); }

        // After first user interaction, shrink centered modal to floating bubble
        shrinkToBubble();

        switch (state.step) {
            case 'greeting':
            case 'name':
                if (looksLikeBudgetInput(val)) {
                    botTypeThen('Please share your *name* (not budget).', null, 500);
                    return;
                }
                if (isLikelyGeneralQuery(val)) {
                    handleFreeTextGreeting(val);
                    return;
                }
                state.name = val;
                state.step = 'phone';
                sessionSave();
                botTypeThen('Thanks, *' + escHtml(val) + '*! Please share your *phone number*.', null, 500);
                break;

            case 'phone':
                if (!isValidPhone(val)) {
                    botTypeThen('Please enter a valid phone number (at least 7 digits).', null, 400);
                    return;
                }
                state.phone = val;
                state.step = 'email';
                sessionSave();
                botTypeThen('Great. Please share your *email* or type *skip*.', null, 500);
                break;

            case 'email':
                state.emailAsked = true;
                if (val.toLowerCase() !== 'skip') {
                    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)) {
                        botTypeThen('That doesn’t look like a valid email. Please try again or type *skip*.', null, 400);
                        return;
                    }
                    state.email = val;
                } else {
                    state.email = '';
                }
                state.step = 'location';
                sessionSave();
                botTypeThen('Which *location* are you interested in?', null, 500);
                break;

            case 'location':
                setLocationState(val);
                state.step = 'property_type';
                sessionSave();
                botTypeThen('What *type of property* are you looking for?', function () {
                    showQuickReplies(['Apartment', 'Villa', 'Townhouse', 'Office', 'Studio']);
                }, 500);
                break;

            case 'budget':
                var parsed = asraa_parse_budget(val);
                state.budget = parsed > 0 ? String(Math.round(parsed)) : '';
                if (!state.budget) {
                    botTypeThen('Please enter a valid budget amount (e.g. 1 cr, 10 lakh, 50k).', null, 500);
                    return;
                }
                state.step = 'advisory';
                sessionSave();
                showAdvisoryAndSearch();
                break;

            case 'property_type':
                // Typed answer instead of quick-reply
                var cleanType = stripLeadingEmoji(val);
                setPropertyType(cleanType);
                state.step = 'budget';
                sessionSave();
                hideQuickReplies();
                ensureCurrencyReady(function () {
                    askBudgetQuestion('type_collected');
                });
                break;

            case 'done':
                botTypeThen('Would you like best deals on WhatsApp?', function () {
                    askForLeadOptIn();
                }, 500);
                break;

            case 'completed':
                botTypeThen('If you share another requirement, I can instantly find more options.', null, 500);
                break;

            case 'valuation':
                handleValuationStep(val);
                break;
        }
    }

    function continueGuidedFlow() {
        if (!state.name) {
            state.step = 'name';
            botTypeThen('May I know your *name*?', null, 500);
            return;
        }
        if (!state.phone) {
            state.step = 'phone';
            botTypeThen('Please share your *phone number*.', null, 500);
            return;
        }
        if (!state.emailAsked) {
            state.step = 'email';
            botTypeThen('Please share your *email* or type *skip*.', null, 500);
            return;
        }
        if (!state.location) {
            state.step = 'location';
            botTypeThen('Which *location* are you interested in?', null, 500);
            return;
        }
        if (!state.propertyType) {
            state.step = 'property_type';
            botTypeThen('What *type of property* are you looking for?', function () {
                showQuickReplies(['Apartment', 'Villa', 'Townhouse', 'Office', 'Studio']);
            }, 500);
            return;
        }
        if (!state.budget) {
            state.step = 'budget';
            ensureCurrencyReady(function () {
                askBudgetQuestion('resume_budget');
            });
            return;
        }
        state.step = 'advisory';
        showAdvisoryAndSearch();
    }

    function showAdvisoryAndSearch() {
        var rateHint = getLocationRateHint(state.location || state.city || '');
        var numericBudget = asraa_parse_budget(state.budget);
        var budgetBand = numericBudget > 0
            ? asraa_format_price(asraa_convert_inr(numericBudget, asraa_get_currency()), asraa_get_currency())
            : 'your budget range';
        var advisory = 'Based on your requirement, here’s a quick advisory:\n\n'
            + 'â€¢ ' + rateHint + '\n'
            + 'â€¢ Budget suggestion: target projects around ' + budgetBand + '\n'
            + 'â€¢ Area insight: demand is strongest in well-connected micro-markets.';
        botTypeThen(advisory, function () {
            state.step = 'searching';
            sessionSave();
            submitAndSearch();
        }, 700);
    }

    /**
     * Free-text handler for the greeting step.
     * Detects mode (buy/sell/invest) and extracts intent.
     */
    function handleFreeTextGreeting(text) {
        // Valuation intent takes priority
        if (detectValuationIntent(text)) {
            startValuationFlow();
            return;
        }

        var mode = detectMode(text);
        state.ai_mode = mode;
        state.intent = mode;

        if (mode === 'invest') {
            sessionSave();
            botTypeThen(
                '📈 Great, let’s build an investment-focused shortlist.\n\nShare your *location*, *budget*, and *property type* for ROI-oriented options.',
                null, 700
            );
            return;
        }

        var extracted = extractIntent(text);

        if (extracted.location) {
            state.initialChoice = extracted.location;
            setLocationState(extracted.location);
        }
        if (extracted.budget)   state.budget = extracted.budget;
        if (extracted.type) {
            setPropertyType(extracted.type);
        }

        // If free-text already contains full criteria, skip sequential collection and search now.
        if (hasSearchTriggerCriteria()) {
            state.step = 'searching';
            sessionSave();
            askWithAI(buildSmartEngagementIntro(), 'qualified_intent', function () {
                submitAndSearch();
            });
            return;
        }

        state.step = getNextBuyStep();
        sessionSave();

        var reply = 'Thanks for sharing! 🙌';
        if (extracted.location) {
            reply += '\n\nI\'ll search in *' + escHtml(extracted.location) + '*.';
        }
        reply += '\n\n' + missingCriteriaQuestion();
        askWithAI(reply, 'free_text_greeting');
    }

    /* ------------------------------------------------------------------ */
    /*  INTENT EXTRACTION (rule-based)                                      */
    /* ------------------------------------------------------------------ */
    /**
     * Very lightweight NLP: extract location, budget, property type
     * from a free-text string using keyword lists and regex patterns.
     */
    function extractIntent(text) {
        var lower = text.toLowerCase();
        var result = { location: '', budget: '', type: '' };

        // Locations
        var locations = [
            'mumbai','dubai','delhi','bangalore','pune','hyderabad','chennai','kolkata',
            'marina','jvc','downtown','palm jumeirah','business bay','deira','bur dubai'
        ];
        for (var i = 0; i < locations.length; i++) {
            if (lower.indexOf(locations[i]) !== -1) {
                result.location = locations[i].charAt(0).toUpperCase() + locations[i].slice(1);
                break;
            }
        }

        // Budget: prefer amount tokens with units (cr/lakh/k/million), otherwise use a high standalone number.
        var budgetCandidates = lower.match(/(?:aed|inr|â‚¹|\$|rs\.?)?\s*[\d,]+(?:\.\d+)?\s*(?:k|lakh|lakhs|cr|crore|crores|million)\b/g) || [];
        if (!budgetCandidates.length) {
            budgetCandidates = lower.match(/(?:aed|inr|â‚¹|\$|rs\.?)?\s*[\d,]{4,}(?:\.\d+)?/g) || [];
        }
        if (budgetCandidates.length) {
            var chosen = budgetCandidates[budgetCandidates.length - 1];
            var num = asraa_parse_budget(chosen);
            if (num > 0) result.budget = String(Math.round(num));
        }

        // Property type keywords
        var bhk = lower.match(/\b(\d)\s*bhk\b/);
        if (bhk) {
            result.type = bhk[1] + 'BHK';
        }
        var types = {
            apartment: 'Apartment', flat: 'Apartment', condo: 'Apartment',
            villa: 'Villa', bungalow: 'Villa', house: 'Villa',
            townhouse: 'Townhouse', townhome: 'Townhouse',
            office: 'Office', commercial: 'Office',
            studio: 'Studio', plot: 'Plot', land: 'Plot'
        };
        if (!result.type) {
            for (var key in types) {
                if (Object.prototype.hasOwnProperty.call(types, key)) {
                    if (lower.indexOf(key) !== -1) {
                        result.type = types[key];
                        break;
                    }
                }
            }
        }

        if (!result.location) {
            var cleaned = lower
                .replace(/\b(\d)\s*bhk\b/g, ' ')
                .replace(/(?:aed|inr|â‚¹|\$|rs\.?)?\s*[\d,]+(?:\.\d+)?\s*(?:k|lakh|lakhs|cr|crore|crores|million)?/g, ' ')
                .replace(/\b(apartment|flat|condo|villa|bungalow|house|townhouse|townhome|office|commercial|studio|plot|land|buy|sell|investment|invest|property|properties)\b/g, ' ')
                .replace(/\s+/g, ' ')
                .trim();
            var stopWords = ['in', 'for', 'near', 'at', 'with', 'within', 'under', 'around', 'looking', 'need', 'want', 'show', 'me'];
            var locationTokens = cleaned.split(' ').filter(function (token) {
                return token && stopWords.indexOf(token) === -1;
            });
            cleaned = locationTokens.join(' ').trim();
            if (cleaned.length >= 3 && /[a-z]/i.test(cleaned)) {
                result.location = cleaned.replace(/\b\w/g, function (m) { return m.toUpperCase(); });
            }
        }

        return result;
    }

    /* ------------------------------------------------------------------ */
    /*  AI API PROXY                                                        */
    /* ------------------------------------------------------------------ */
    /**
     * Ask the AI API for a contextual reply (or fall back to fallbackMsg for rule_based).
     * Handles both structured JSON response (action, location, city, budget, type) and
     * legacy plain-text replies containing the SEARCH_PROPERTIES trigger token.
     *
     * @param {string}   fallbackMsg  Shown immediately for rule_based / on API failure.
     * @param {string}   context      Hint string forwarded to the backend.
     * @param {Function} onDone       Optional callback after response render.
     */
    function askWithAI(fallbackMsg, context, onDone) {
        if (aiRequestInFlight) return;
        var lastUserMsg = state.history.length
            ? state.history[state.history.length - 1].content
            : '';

        var headers = { 'Content-Type': 'application/json' };
        if (NONCE) headers['X-WP-Nonce'] = NONCE;

        clearTimeout(aiDebounceTimer);
        aiDebounceTimer = setTimeout(function () {
            var body  = document.getElementById('asraa-ai-body');
            var typer = document.createElement('div');
            typer.className = 'asraa-ai-typing';
            typer.innerHTML = '<span></span><span></span><span></span>';
            body.appendChild(typer);
            scrollBody();
            aiRequestInFlight = true;
            fetch(AI_CHAT_URL, {
                method: 'POST',
                headers: headers,
                body: JSON.stringify({
                    message:       lastUserMsg,
                    messages:      state.history,
                    context:       context || '',
                    lead_id:       state.leadId,
                    ai_mode:       state.ai_mode || 'buy',
                    location:      state.location,
                    budget:        state.budget,
                    property_type: state.propertyType
                })
            })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            aiRequestInFlight = false;
            if (typer.parentNode) typer.parentNode.removeChild(typer);
            if (data && data.success === false) {
                addBotMsg('AI service is temporarily unavailable. Please try again shortly.');
                if (typeof onDone === 'function') onDone();
                return;
            }
            var reply = (data && data.reply) ? data.reply : fallbackMsg;

            // Merge AI-extracted fields into state when non-empty.
            if (data) {
                if (data.location && !state.location) setLocationState(data.location);
                if (data.city     && !state.city) {
                    // Strip HTML special chars; escHtml() further sanitizes at render time.
                    state.city = String(data.city).replace(/[<>"'&]/g, '').substring(0, MAX_CITY_LEN);
                }
                if (data.budget   && !state.budget)   state.budget = String(parseFloat(data.budget) || '');
                if (data.type     && !state.propertyType) setPropertyType(data.type);
                if (data.intent && !state.intent) state.intent = String(data.intent);
            }

            // Determine whether the AI wants to trigger a property search.
            var wantsSearch = (data && data.action === 'search')
                || (reply && reply.indexOf('SEARCH_PROPERTIES') !== -1);

            if (wantsSearch) {
                var cleanReply = reply.replace(/SEARCH_PROPERTIES/g, '').trim();
                if (cleanReply) {
                    addBotMsg(cleanReply);
                }
                if (hasSearchTriggerCriteria()) {
                    state.step = 'searching';
                    sessionSave();
                    submitAndSearch();
                } else {
                    state.step = getNextBuyStep();
                    sessionSave();
                    botTypeThen(missingCriteriaQuestion(), null, 600);
                }
                return;
            }

            addBotMsg(reply);
            sessionSave();
            if (typeof onDone === 'function') onDone();
        })
        .catch(function () {
            aiRequestInFlight = false;
            if (typer.parentNode) typer.parentNode.removeChild(typer);
            addBotMsg('AI service is temporarily unavailable. Please try again shortly.');
            if (typeof onDone === 'function') onDone();
        });
        }, 250);
    }

    function pushHistory(role, content) {
        state.history.push({ role: role, content: content });
        // Keep history to last 10 turns to avoid huge payloads
        if (state.history.length > 20) {
            state.history = state.history.slice(-20);
        }
    }

    /* ------------------------------------------------------------------ */
    /*  LEAD SUBMIT + PROPERTY SEARCH                                       */
    /* ------------------------------------------------------------------ */
    var searchInFlight = false;
    var searchDebounceTimer = null;

    function submitAndSearch() {
        if (searchInFlight) {
            botTypeThen('Search already in progress. Please wait a moment.', null, 300);
            return;
        }
        if (searchDebounceTimer) return;
        botTypeThen('🔍 AI is searching properties…', null, 600);
        clearTimeout(searchDebounceTimer);
        searchDebounceTimer = setTimeout(function () {
            searchDebounceTimer = null;
            saveLeadOnly()
                .then(function (leadId) {
                    runPropertySearch(leadId || 0);
                })
                .catch(function () {
                    runPropertySearch(0);
                });
        }, 250);
    }

    function runPropertySearch(leadId) {
        if (searchInFlight) return;
        searchInFlight = true;
        // Parse text budget (e.g. "1.5 cr", "50 lakh") into a raw number
        var parsedBudget = asraa_parse_budget(state.budget) || 0;
        var userCurrency = asraa_get_currency();
        var budgetINR = asraa_budget_to_inr(parsedBudget > 0 ? parsedBudget : (parseFloat(state.budget) || 0), userCurrency);

        var headers = { 'Content-Type': 'application/json' };
        if (NONCE) headers['X-WP-Nonce'] = NONCE;

        fetch(SEARCH_URL, {
            method:  'POST',
            headers: headers,
            body:    JSON.stringify({
                location:      state.area || state.location,
                city:          state.city || state.location,
                area:          state.area,
                budget:        budgetINR,
                type:          state.propertyType,
                property_type: state.propertyType,
                lead_id:       leadId
            })
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            searchInFlight = false;
            state.step = 'done';
            if (data && data.success && data.properties && data.properties.length) {
                renderPropertyCards(data.properties, data.exact_match, data.match_level);
            } else {
                botTypeThen(
                    'I couldn’t load listings right now. Please share another location and I’ll retry instantly.',
                    finishConversation, 700
                );
            }
        })
        .catch(function () {
            searchInFlight = false;
            state.step = 'done';
            botTypeThen('I’m facing a temporary issue loading options. Please try again in a moment.', finishConversation, 700);
        });
    }

    function renderPropertyCards(properties, exactMatch, matchLevel) {
        var deduped = [];
        var seenKeys = {};
        (properties || []).forEach(function (prop) {
            var key;
            if (prop && prop.id !== undefined && prop.id !== null && String(prop.id) !== '') {
                key = 'id:' + String(prop.id);
            } else {
                key = 'fallback:' + String((prop && prop.title) || '') + '|' + String((prop && prop.price) || '') + '|' + String((prop && prop.city) || (prop && prop.location) || '');
            }
            if (seenKeys[key]) return;
            seenKeys[key] = true;
            deduped.push(prop);
        });
        var currency = asraa_get_currency();
        var searchLocation = state.area || state.location || state.city || 'your location';
        var intro = exactMatch
            ? 'Here are matching properties in *' + escHtml(searchLocation) + '* 👇'
            : 'I didn’t find exact matches in *' + escHtml(searchLocation) + '*, but here are nearby options 👇';
        if (matchLevel === 'global_latest') {
            intro = 'Here are the latest available options you can explore right now 👇';
        }

        botTypeThen(intro, function () {
            var body = document.getElementById('asraa-ai-body');
            var grid = document.createElement('div');
            grid.className = 'asraa-ai-prop-grid';

            deduped.forEach(function (prop) {
                var card = document.createElement('div');
                card.className = 'asraa-ai-prop-card';

                // Support both 'image' (legacy) and 'image_url' column names
                var imgUrl  = safePropUrl(prop.image_url || prop.image);
                var linkUrl = safePropUrl(prop.link || prop.property_url || '');
                // Support both 'location' (legacy) and 'city' column names
                var locText = prop.city || prop.location || '';

                var imgHtml  = imgUrl  ? '<img class="asraa-ai-prop-img" src="' + escHtml(imgUrl)  + '" alt="' + escHtml(prop.title || '') + '" loading="lazy">' : '';
                var converted = prop.price ? asraa_convert_inr(prop.price, currency) : 0;
                var priceStr = converted ? asraa_format_price(converted, currency) : '';
                var viewBtn  = linkUrl
                    ? '<a class="asraa-ai-prop-view" href="' + escHtml(linkUrl) + '" target="_blank" rel="noopener noreferrer">View Property</a>'
                    : '';

                card.innerHTML = imgHtml
                    + '<div class="asraa-ai-prop-body">'
                    +   '<div class="asraa-ai-prop-title">'    + escHtml(prop.title    || 'Property') + '</div>'
                    +   (priceStr  ? '<div class="asraa-ai-prop-price">'    + escHtml(priceStr) + '</div>' : '')
                    +   (locText   ? '<div class="asraa-ai-prop-location">📍 ' + escHtml(locText) + '</div>' : '')
                    + '</div>'
                    + viewBtn;

                grid.appendChild(card);
            });
            body.appendChild(grid);
            scrollBody();

            setTimeout(finishConversation, 1400);
        }, 700);
    }

    function finishConversation() {
        askForLeadOptIn();
    }

    function askForLeadOptIn() {
        state.step = 'done';
        sessionSave();
        botTypeThen('Want best deals on WhatsApp?', function () {
            showQuickReplies(['Yes, connect on WhatsApp', 'No, continue here']);
        }, 700);
    }

    function handleLeadOptinChoice(input) {
        hideQuickReplies();
        var answer = String(input || '').toLowerCase();
        if (/(^|\b)(yes|y|ok|sure|whatsapp|connect)(\b|$)/i.test(answer)) {
            state.step = 'completed';
            sessionSave();
            botTypeThen('Opening WhatsApp with your requirement now.', function () {
                openWhatsAppWithContext();
            }, 500);
            return;
        }
        state.step = 'completed';
        sessionSave();
        botTypeThen('No problem. Share any new location/budget/type and I’ll find more options.', null, 500);
    }

    function saveLeadOnly() {
        var currency = asraa_get_currency();
        var message = 'Intent: ' + (state.intent || state.initialChoice || 'buy')
            + ' | Location: ' + (state.location || '')
            + ' | Budget: ' + currency + ' ' + (state.budget || '')
            + ' | Type: ' + (state.propertyType || '');
        var payload = {
            name: state.name,
            phone: state.phone,
            intent: state.intent || state.initialChoice || 'buy',
            location: state.location,
            budget: state.budget,
            type: state.propertyType,
            property_type: state.propertyType,
            message: message,
            source: 'AI Chatbot'
        };
        var headers = { 'Content-Type': 'application/json' };
        if (NONCE) headers['X-WP-Nonce'] = NONCE;

        return fetch(API_URL, {
            method: 'POST',
            headers: headers,
            body: JSON.stringify(payload)
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                state.leadId = (data && data.id) ? data.id : 0;
                sessionSave();
                return state.leadId;
            });
    }

    function openWhatsAppWithContext() {
        var wa = extractDigitsFromWhatsAppNumber(cfg.whatsappNumber || '');
        if (!wa) return;
        var text = 'Hi, I am interested in '
            + (state.propertyType || 'property')
            + ' in ' + (state.location || 'your area')
            + ' with budget â‚¹' + (state.budget || '0');
        var url = 'https://wa.me/' + encodeURIComponent(wa) + '?text=' + encodeURIComponent(text);
        window.open(url, '_blank', 'noopener');
    }

    /* ------------------------------------------------------------------ */
    /*  QUICK REPLIES                                                       */
    /* ------------------------------------------------------------------ */
    function showQuickReplies(options) {
        var container = document.getElementById('asraa-ai-quick-replies');
        container.innerHTML = '';
        options.forEach(function (opt) {
            var btn = document.createElement('button');
            btn.className   = 'asraa-ai-qr-btn';
            btn.textContent = opt;
            btn.addEventListener('click', function () { handleQuickReply(opt); });
            container.appendChild(btn);
        });
    }

    function hideQuickReplies() {
        var el = document.getElementById('asraa-ai-quick-replies');
        if (el) el.innerHTML = '';
    }

    /* ------------------------------------------------------------------ */
    /*  MESSAGE HELPERS                                                     */
    /* ------------------------------------------------------------------ */
    function addBotMsg(text) {
        var body = document.getElementById('asraa-ai-body');
        var msg  = document.createElement('div');
        msg.className = 'asraa-ai-msg asraa-ai-msg-bot';
        msg.innerHTML = fmtText(text);
        body.appendChild(msg);
        scrollBody();
        pushHistory('assistant', text);
    }

    function addUserMsg(text) {
        var body = document.getElementById('asraa-ai-body');
        var msg  = document.createElement('div');
        msg.className   = 'asraa-ai-msg asraa-ai-msg-user';
        msg.textContent = text;
        body.appendChild(msg);
        scrollBody();
        pushHistory('user', text);
    }

    /**
     * Show typing indicator for `delay` ms (defaults to length-based delay),
     * then display message + optional callback.
     */
    function botTypeThen(text, callback, delay) {
        var body  = document.getElementById('asraa-ai-body');
        var typer = document.createElement('div');
        typer.className = 'asraa-ai-typing';
        typer.innerHTML = '<span>AI is typing...</span>';
        body.appendChild(typer);
        scrollBody();

        setTimeout(function () {
            if (typer.parentNode) typer.parentNode.removeChild(typer);
            addBotMsg(text);
            if (callback) callback();
        }, delay !== undefined ? delay : typingDelay(text));
    }

    function scrollBody() {
        var body = document.getElementById('asraa-ai-body');
        if (body) body.scrollTop = body.scrollHeight;
    }

    /* ------------------------------------------------------------------ */
    /*  HELPERS                                                             */
    /* ------------------------------------------------------------------ */
    /** Bold *text*, italic _text_, \n â†’ <br>. */
    function fmtText(text) {
        return escHtml(text)
            .replace(/\*(.*?)\*/g, '<strong>$1</strong>')
            .replace(/_(.*?)_/g, '<em>$1</em>')
            .replace(/\n/g, '<br>');
    }

    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#x27;');
    }

    /** Allow only http / https URLs; return empty string for anything else. */
    function safePropUrl(url) {
        try {
            var p = new URL(String(url));
            return (p.protocol === 'http:' || p.protocol === 'https:') ? url : '';
        } catch (e) {
            return '';
        }
    }

    /** Very basic phone validation: 5-20 chars, at least 7 digits. */
    function isValidPhone(val) {
        if (!/^[0-9\s+\-()\u202A\u202C]{5,20}$/.test(val)) return false;
        return (val.match(/\d/g) || []).length >= 7;
    }

    function hasCompleteSearchCriteria() {
        return !!(state.location && state.budget && state.propertyType);
    }

    function hasSearchTriggerCriteria() {
        return !!(state.location && (state.budget || state.propertyType));
    }

    function missingCriteriaQuestion() {
        if (!state.location) return 'Which *location* are you looking in?';
        if (!state.budget) return 'What is your *budget*? (e.g. 1 cr, 10 lakh, 50k)';
        if (!state.propertyType) return 'What *type* are you looking for? (Apartment, Villa, Office, Studio)';
        return 'Share a little more detail so I can search.';
    }

    function looksLikeBudgetInput(val) {
        var text = String(val || '').toLowerCase().trim();
        if (!text) return false;
        if (asraa_parse_budget(text) > 0 && /(?:\d[\d,]*(?:\.\d+)?\s*(?:cr|crore|lakh|k|million)?|\d{4,})/.test(text)) return true;
        return /^\d[\d,\s.]*$/.test(text);
    }

    function isLikelyGeneralQuery(val) {
        var text = String(val || '').trim();
        if (!text) return false;
        if (text.length <= 2) return false;
        if (looksLikeBudgetInput(text)) return false;
        var namePattern = new RegExp('^[A-Za-z][A-Za-z\\s\'.-]{1,' + String(Math.max(1, MAX_NAME_LEN - 1)) + '}$');
        if (namePattern.test(text)) return false;
        return /[?]/.test(text)
            || /\b(buy|sell|invest|investment|roi|property|properties|apartment|villa|townhouse|office|studio|valuation|price|budget|location)\b/i.test(text)
            || text.split(/\s+/).length >= 5;
    }

    function extractDigitsFromWhatsAppNumber(number) {
        return String(number || '').replace(/\D+/g, '');
    }

    /**
     * Strip leading emoji / non-word characters from a label string.
     * Handles Latin-extended characters (U+00C0–U+017E) so accented letters
     * in location names are preserved.
     */
    function stripLeadingEmoji(label) {
        return label.replace(/^[^\w\u00C0-\u017E]+/, '').trim();
    }

    /** Typing delay is fixed for consistent UX pacing. */
    function typingDelay() {
        // 600ms keeps responses natural but slightly faster than the prior 700ms pacing.
        return TYPING_DELAY_MS;
    }

    function getLocationRateHint(location) {
        var text = String(location || '').toLowerCase();
        var insights = cfg.marketInsights || {};
        var keys = Object.keys(insights);
        for (var i = 0; i < keys.length; i++) {
            var key = String(keys[i] || '').toLowerCase();
            if (key && text.indexOf(key) !== -1) {
                return String(insights[keys[i]]);
            }
        }
        return 'I can also estimate current price-per-sqft trends for your preferred area.';
    }

    function buildSmartEngagementIntro() {
        var area = state.location || 'your selected area';
        return 'Great choice 👍 ' + area + ' is seeing steady demand.\n\n'
            + getLocationRateHint(area)
            + '\n\nLet me show options 👇\n\nWould you prefer better rental yield or better end-use value?';
    }

    /* ------------------------------------------------------------------ */
    /*  SESSION STORAGE                                                     */
    /* ------------------------------------------------------------------ */
    /** Persist key state fields to sessionStorage so a page refresh restores them. */
    function sessionSave() {
        try {
            sessionStorage.setItem(SESSION_KEY, JSON.stringify({
                location:     state.location,
                budget:       state.budget,
                propertyType: state.propertyType,
                type:         state.propertyType,
                city:         state.city,
                area:         state.area,
                ai_mode:      state.ai_mode,
                intent:       state.intent,
                step:         state.step,
                name:         state.name,
                phone:        state.phone,
                email:        state.email,
                emailAsked:   state.emailAsked,
                leadConsent:  state.leadConsent
            }));
        } catch (e) { /* ignore */ }
    }

    /** Restore state from sessionStorage (called on chat open). */
    function sessionRestore() {
        try {
            var saved = JSON.parse(sessionStorage.getItem(SESSION_KEY) || '{}');
            if (saved.location)     state.location     = saved.location;
            if (saved.budget)       state.budget       = saved.budget;
            if (saved.propertyType) setPropertyType(saved.propertyType);
            if (saved.type && !state.propertyType) setPropertyType(saved.type);
            if (saved.city)         state.city         = saved.city;
            if (saved.area)         state.area         = saved.area;
            if (saved.ai_mode)      state.ai_mode      = saved.ai_mode;
            if (saved.intent)       state.intent       = saved.intent;
            if (saved.step)         state.step         = saved.step;
            if (saved.name)         state.name         = saved.name;
            if (saved.phone)        state.phone        = saved.phone;
            if (saved.email)        state.email        = saved.email;
            if (typeof saved.emailAsked === 'boolean') state.emailAsked = saved.emailAsked;
            if (typeof saved.leadConsent === 'boolean') state.leadConsent = saved.leadConsent;
        } catch (e) { /* ignore */ }
    }

    /* ------------------------------------------------------------------ */
    /*  SMART ROUTING – MODE DETECTION                                      */
    /* ------------------------------------------------------------------ */
    /**
     * Determine AI mode from a user text or label.
     * Returns: 'buy' | 'sell' | 'invest'
     */
    function detectMode(text) {
        var lower = text.toLowerCase();
        if (/\bsell\b|valuation|value.*property|price.*my.*property/.test(lower)) return 'sell';
        if (/\binvest\b|investment|roi|return.*invest/.test(lower))               return 'invest';
        return 'buy';
    }

    /**
     * Returns true when the user expresses intent to sell / get a valuation.
     */
    function detectValuationIntent(text) {
        return /\bsell\b|valuation|value.*property|price.*my.*property/.test(text.toLowerCase());
    }

    /* ------------------------------------------------------------------ */
    /*  VALUATION FLOW                                                      */
    /* ------------------------------------------------------------------ */
    /** Begin the property valuation conversation. */
    function startValuationFlow() {
        state.ai_mode       = 'sell';
        state.intent        = 'sell';
        state.step          = 'valuation';
        state.valuation_step = 1;
        sessionSave();
        botTypeThen(
            'ðŸ·ï¸ I can help you estimate your property value!\n\nFirst, what is the *location* of your property?\n_(e.g. Dubai Marina, Downtown, Bandra Mumbai)_',
            null, 700
        );
    }

    /** Handle each step of the valuation data-collection flow. */
    function handleValuationStep(val) {
        switch (state.valuation_step) {
            case 1:
                state.val_location   = val;
                state.valuation_step = 2;
                botTypeThen(
                    'What is the *configuration* of your property?\n_(e.g. Studio, 1BHK, 2BHK, 3BHK, Villa)_',
                    null, 600
                );
                break;
            case 2:
                state.val_config     = val;
                state.valuation_step = 3;
                botTypeThen('What is the *built-up area* in sq ft?', null, 600);
                break;
            case 3:
                state.val_area       = val;
                state.valuation_step = 0;
                showValuationResult();
                break;
        }
    }

    /** Show the valuation estimate and prompt for contact details. */
    function showValuationResult() {
        var sqft = asraa_parse_budget(state.val_area);
        var headers = { 'Content-Type': 'application/json' };
        if (NONCE) headers['X-WP-Nonce'] = NONCE;

        fetch(VALUATION_URL, {
            method: 'POST',
            headers: headers,
            body: JSON.stringify({
                location: state.val_location,
                type: state.val_config,
                sqft: sqft
            })
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (!data || !data.success) {
                    throw new Error('valuation_unavailable');
                }
                var currency = asraa_get_currency();
                var minVal = asraa_convert_inr(data.range_min, currency);
                var maxVal = asraa_convert_inr(data.range_max, currency);
                var rateVal = asraa_convert_inr(data.base_rate, currency);
                botTypeThen(
                    'ðŸ“Š Estimated valuation range for *' + escHtml(state.val_config) + '* in *' + escHtml(state.val_location) + '*:\n\n'
                    + asraa_format_price(minVal, currency) + ' - ' + asraa_format_price(maxVal, currency)
                    + '\n\nBase rate: ' + asraa_format_price(rateVal, currency) + ' / sq ft',
                    promptForValuationLead,
                    700
                );
            })
            .catch(function () {
                botTypeThen(
                    'ðŸ“Š We could not fetch a precise valuation instantly, but our specialist can share an accurate market range shortly.',
                    promptForValuationLead,
                    700
                );
            });
    }

    function promptForValuationLead() {
        botTypeThen('I can help you get better offers.\nWant to connect on WhatsApp?', function () {
            askForLeadOptIn();
        }, 700);
    }

    /* ------------------------------------------------------------------ */
    /*  BOOT                                                                */
    /* ------------------------------------------------------------------ */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
})();