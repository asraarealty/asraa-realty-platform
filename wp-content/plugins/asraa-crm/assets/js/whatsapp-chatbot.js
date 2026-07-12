/**
 * Asraa CRM – WhatsApp AI Chatbot Widget
 *
 * Floating WhatsApp bubble (bottom-left) that opens a conversational
 * lead-capture chat.  Conversation flow:
 *   1. Greeting + quick-reply buttons (Buy / Sell / Investment / Dubai)
 *   2. Ask name
 *   3. Ask phone  →  show "Continue on WhatsApp" button
 *   4. Ask email (optional – user can skip)
 *   5. Ask location
 *   6. Ask budget
 *   7. Ask property type (quick-reply buttons)
 *   8. Submit lead  →  search properties  →  display cards
 *   9. Done / thank-you
 *
 * Config is passed from PHP via wp_localize_script as window.asraaChatbot:
 *   apiUrl    – lead capture REST endpoint URL
 *   searchUrl – property search REST endpoint URL
 *   nonce     – wp_rest nonce
 *   waNumber  – WhatsApp number, digits only (E.164 without +)
 */
(function () {
    'use strict';

    /* ------------------------------------------------------------------ */
    /*  CONFIG                                                              */
    /* ------------------------------------------------------------------ */
    var cfg        = window.asraaChatbot || {};
    var API_URL    = cfg.apiUrl    || '/wp-json/asraa/v1/lead';
    var SEARCH_URL = cfg.searchUrl || '/wp-json/asraa/v1/search-properties';
    var WA_NUMBER  = cfg.waNumber  || '971000000000';
    var NONCE      = cfg.nonce     || '';

    var SESSION_KEY = 'asraa_chat_closed';

    /* ------------------------------------------------------------------ */
    /*  STATE                                                               */
    /* ------------------------------------------------------------------ */
    var state = {
        open:         false,
        step:         'idle',    // idle|greeting|name|phone|email|location|budget|property_type|searching|done
        requirement:  '',        // initial intent from greeting quick-replies
        name:         '',
        phone:        '',
        email:        '',
        location:     '',
        budget:       '',
        propertyType: ''
    };

    /* ------------------------------------------------------------------ */
    /*  STYLES                                                              */
    /* ------------------------------------------------------------------ */
    var CSS = [
        /* Bubble */
        '#asraa-wa-bubble{',
            'position:fixed;bottom:24px;left:24px;z-index:99990;',
            'width:60px;height:60px;border-radius:50%;',
            'background:#25D366;',
            'display:flex;align-items:center;justify-content:center;',
            'cursor:pointer;box-shadow:0 4px 16px rgba(0,0,0,.28);',
            'transition:transform .2s,box-shadow .2s;',
            'border:none;padding:0;',
        '}',
        '#asraa-wa-bubble:hover{transform:scale(1.08);box-shadow:0 6px 22px rgba(0,0,0,.34);}',
        '#asraa-wa-bubble svg{width:32px;height:32px;}',
        /* Pulse ring */
        '#asraa-wa-bubble::before{',
            'content:"";position:absolute;',
            'width:60px;height:60px;border-radius:50%;',
            'background:rgba(37,211,102,.45);',
            'animation:asraa-pulse 2s ease-out infinite;',
        '}',
        '@keyframes asraa-pulse{',
            '0%{transform:scale(1);opacity:.8}',
            '100%{transform:scale(2.2);opacity:0}',
        '}',
        /* Chat window */
        '#asraa-chat-window{',
            'position:fixed;bottom:96px;left:24px;z-index:99991;',
            'width:340px;max-width:calc(100vw - 32px);',
            'background:#fff;border-radius:16px;',
            'box-shadow:0 8px 32px rgba(0,0,0,.22);',
            'display:flex;flex-direction:column;overflow:hidden;',
            'font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;',
            'font-size:14px;',
            'transform:scale(.85) translateY(20px);opacity:0;',
            'transition:transform .25s cubic-bezier(.34,1.56,.64,1),opacity .25s;',
            'pointer-events:none;',
        '}',
        '#asraa-chat-window.asraa-open{',
            'transform:scale(1) translateY(0);opacity:1;pointer-events:auto;',
        '}',
        /* Header */
        '#asraa-chat-header{',
            'background:linear-gradient(135deg,#075E54 0%,#128C7E 100%);',
            'padding:14px 16px;display:flex;align-items:center;gap:12px;color:#fff;',
        '}',
        '#asraa-chat-header-avatar{',
            'width:42px;height:42px;border-radius:50%;background:#25D366;',
            'display:flex;align-items:center;justify-content:center;flex-shrink:0;',
        '}',
        '#asraa-chat-header-avatar svg{width:24px;height:24px;}',
        '#asraa-chat-header-info{flex:1;}',
        '#asraa-chat-header-name{font-weight:700;font-size:15px;}',
        '#asraa-chat-header-status{font-size:12px;opacity:.85;}',
        '#asraa-chat-close{',
            'background:none;border:none;cursor:pointer;',
            'color:#fff;opacity:.8;padding:4px;line-height:1;font-size:20px;',
            'transition:opacity .15s;',
        '}',
        '#asraa-chat-close:hover{opacity:1;}',
        /* Body */
        '#asraa-chat-body{',
            'flex:1;overflow-y:auto;padding:16px;',
            'background:#ECE5DD;',
            'display:flex;flex-direction:column;gap:10px;',
            'max-height:320px;',
        '}',
        '#asraa-chat-body::-webkit-scrollbar{width:4px;}',
        '#asraa-chat-body::-webkit-scrollbar-track{background:transparent;}',
        '#asraa-chat-body::-webkit-scrollbar-thumb{background:rgba(0,0,0,.2);border-radius:2px;}',
        /* Messages */
        '.asraa-msg{',
            'max-width:82%;padding:8px 12px;border-radius:12px;',
            'line-height:1.45;word-wrap:break-word;',
            'animation:asraa-msg-in .2s ease;',
        '}',
        '@keyframes asraa-msg-in{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:none}}',
        '.asraa-msg-bot{',
            'background:#fff;color:#111;',
            'border-bottom-left-radius:3px;align-self:flex-start;',
            'box-shadow:0 1px 2px rgba(0,0,0,.1);',
        '}',
        '.asraa-msg-user{',
            'background:#DCF8C6;color:#111;',
            'border-bottom-right-radius:3px;align-self:flex-end;',
            'box-shadow:0 1px 2px rgba(0,0,0,.1);',
        '}',
        /* Quick replies */
        '#asraa-quick-replies{',
            'padding:10px 12px 0;display:flex;flex-wrap:wrap;gap:8px;',
            'background:#ECE5DD;',
        '}',
        '.asraa-qr-btn{',
            'background:#fff;border:1.5px solid #25D366;',
            'color:#075E54;border-radius:20px;',
            'padding:6px 14px;font-size:13px;font-weight:600;',
            'cursor:pointer;transition:background .15s,color .15s;',
        '}',
        '.asraa-qr-btn:hover{background:#25D366;color:#fff;}',
        /* Footer */
        '#asraa-chat-footer{',
            'padding:10px 12px;background:#F0F0F0;',
            'display:flex;align-items:center;gap:8px;',
            'border-top:1px solid #ddd;',
        '}',
        '#asraa-chat-input{',
            'flex:1;border:1px solid #ccc;border-radius:24px;',
            'padding:8px 14px;font-size:14px;outline:none;',
            'transition:border-color .15s;',
        '}',
        '#asraa-chat-input:focus{border-color:#25D366;}',
        '#asraa-chat-send{',
            'background:#25D366;border:none;border-radius:50%;',
            'width:38px;height:38px;cursor:pointer;',
            'display:flex;align-items:center;justify-content:center;',
            'flex-shrink:0;transition:background .15s;',
        '}',
        '#asraa-chat-send:hover{background:#1ebe5d;}',
        '#asraa-chat-send svg{width:18px;height:18px;}',
        /* WhatsApp CTA */
        '#asraa-wa-cta{',
            'display:none;',
            'margin:0 12px 12px;padding:11px 16px;',
            'background:#25D366;color:#fff;border:none;border-radius:24px;',
            'font-size:14px;font-weight:700;cursor:pointer;',
            'width:calc(100% - 24px);text-align:center;',
            'transition:background .15s;text-decoration:none;',
        '}',
        '#asraa-wa-cta:hover{background:#1ebe5d;}',
        /* Typing indicator */
        '.asraa-typing{',
            'display:flex;gap:4px;padding:10px 14px;align-self:flex-start;',
            'background:#fff;border-radius:12px;border-bottom-left-radius:3px;',
            'box-shadow:0 1px 2px rgba(0,0,0,.1);',
        '}',
        '.asraa-typing span{',
            'width:8px;height:8px;border-radius:50%;background:#aaa;',
            'animation:asraa-bounce .9s infinite;',
        '}',
        '.asraa-typing span:nth-child(2){animation-delay:.15s;}',
        '.asraa-typing span:nth-child(3){animation-delay:.3s;}',
        '@keyframes asraa-bounce{',
            '0%,80%,100%{transform:translateY(0)}',
            '40%{transform:translateY(-6px)}',
        '}',
        /* Mobile */
        '@media(max-width:480px){',
            '#asraa-chat-window{width:calc(100vw - 32px);left:16px;bottom:90px;}',
            '#asraa-wa-bubble{left:16px;bottom:16px;}',
        '}',
        /* Property result cards */
        '.asraa-prop-card{',
            'background:#fff;border-radius:10px;overflow:hidden;',
            'box-shadow:0 2px 8px rgba(0,0,0,.12);',
            'margin-bottom:8px;width:100%;align-self:stretch;',
        '}',
        '.asraa-prop-img{width:100%;height:100px;object-fit:cover;display:block;}',
        '.asraa-prop-body{padding:8px 10px 4px;}',
        '.asraa-prop-title{',
            'font-weight:700;font-size:13px;color:#111;',
            'white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:2px;',
        '}',
        '.asraa-prop-price{color:#075E54;font-weight:700;font-size:13px;margin-bottom:2px;}',
        '.asraa-prop-location{color:#666;font-size:12px;margin-bottom:4px;}',
        '.asraa-prop-view{',
            'display:block;margin:0 10px 10px;background:#075E54;',
            'color:#fff;text-align:center;padding:6px;border-radius:6px;',
            'font-size:12px;font-weight:700;text-decoration:none;',
        '}',
        '.asraa-prop-view:hover{background:#128C7E;color:#fff;}'
    ].join('');

    /* ------------------------------------------------------------------ */
    /*  ICONS                                                               */
    /* ------------------------------------------------------------------ */
    var WA_SVG = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#fff">'
        + '<path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15'
        + '-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475'
        + '-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52'
        + '.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207'
        + '-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372'
        + '-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2'
        + ' 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719'
        + ' 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/>'
        + '<path d="M12 0C5.373 0 0 5.373 0 12c0 2.126.554 4.122 1.523 5.855L.057 23.083'
        + 'a.75.75 0 0 0 .92.92l5.228-1.466A11.944 11.944 0 0 0 12 24c6.627 0 12-5.373 12-12'
        + 'S18.627 0 12 0zm0 21.818a9.818 9.818 0 0 1-5.006-1.369l-.36-.214-3.728 1.045'
        + ' 1.002-3.652-.234-.376A9.82 9.82 0 0 1 2.182 12C2.182 6.57 6.57 2.182 12 2.182'
        + 'c5.43 0 9.818 4.388 9.818 9.818 0 5.43-4.388 9.818-9.818 9.818z"/>'
        + '</svg>';

    var SEND_SVG = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#fff">'
        + '<path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>';

    /* ------------------------------------------------------------------ */
    /*  INIT DOM                                                            */
    /* ------------------------------------------------------------------ */
    function init() {
        var style = document.createElement('style');
        style.id  = 'asraa-chatbot-css';
        style.textContent = CSS;
        document.head.appendChild(style);

        // Floating bubble
        var bubble = document.createElement('button');
        bubble.id        = 'asraa-wa-bubble';
        bubble.setAttribute('aria-label', 'Chat with us on WhatsApp');
        bubble.innerHTML = WA_SVG;
        bubble.addEventListener('click', toggleChat);
        document.body.appendChild(bubble);

        // Chat window
        var win = document.createElement('div');
        win.id  = 'asraa-chat-window';
        win.setAttribute('role', 'dialog');
        win.setAttribute('aria-label', 'Asraa Realty Chat');
        win.innerHTML = [
            '<div id="asraa-chat-header">',
                '<div id="asraa-chat-header-avatar">', WA_SVG, '</div>',
                '<div id="asraa-chat-header-info">',
                    '<div id="asraa-chat-header-name">Asraa Realty</div>',
                    '<div id="asraa-chat-header-status">&#9679; Typically replies instantly</div>',
                '</div>',
                '<button id="asraa-chat-close" aria-label="Close chat">&#x2715;</button>',
            '</div>',
            '<div id="asraa-chat-body"></div>',
            '<div id="asraa-quick-replies"></div>',
            '<a id="asraa-wa-cta" target="_blank" rel="noopener">',
                '&#128172; Continue on WhatsApp',
            '</a>',
            '<div id="asraa-chat-footer">',
                '<input id="asraa-chat-input" type="text"',
                    ' placeholder="Type a message\u2026" autocomplete="off" />',
                '<button id="asraa-chat-send" aria-label="Send">', SEND_SVG, '</button>',
            '</div>'
        ].join('');
        document.body.appendChild(win);

        // Wire events
        document.getElementById('asraa-chat-close').addEventListener('click', closeChat);
        document.getElementById('asraa-chat-send').addEventListener('click', handleSend);
        document.getElementById('asraa-chat-input').addEventListener('keydown', function (e) {
            if (e.key === 'Enter') handleSend();
        });

        // Auto-open after 5 s unless already dismissed this session
        if (!sessionStorage.getItem(SESSION_KEY)) {
            setTimeout(openChat, 5000);
        }
    }

    /* ------------------------------------------------------------------ */
    /*  OPEN / CLOSE / TOGGLE                                               */
    /* ------------------------------------------------------------------ */
    function openChat() {
        state.open = true;
        document.getElementById('asraa-chat-window').classList.add('asraa-open');
        if (state.step === 'idle') {
            state.step = 'greeting';
            startGreeting();
        }
    }

    function closeChat() {
        state.open = false;
        document.getElementById('asraa-chat-window').classList.remove('asraa-open');
        sessionStorage.setItem(SESSION_KEY, '1');
    }

    function toggleChat() {
        if (state.open) { closeChat(); } else { openChat(); }
    }

    /* ------------------------------------------------------------------ */
    /*  CONVERSATION STEPS                                                  */
    /* ------------------------------------------------------------------ */
    function startGreeting() {
        botTypeThen(
            '👋 Hi! Welcome to *Asraa Realty*.\n\nHow can I help you today?',
            function () {
                showQuickReplies(['🏠 Buy', '💰 Sell', '📈 Investment', '🌆 Dubai']);
            },
            800
        );
    }

    function handleQuickReply(label) {
        hideQuickReplies();
        addUserMsg(label);

        if (state.step === 'greeting') {
            // Strip leading emoji / non-word characters for storage
            state.requirement = label.replace(/^[^\w\u00C0-\u017E]+/, '').trim();
            state.step = 'name';
            botTypeThen('Great choice! 🎉\n\nMay I know your *name* please?', null, 600);
            return;
        }

        if (state.step === 'property_type') {
            state.propertyType = label.replace(/^[^\w\u00C0-\u017E]+/, '').trim();
            state.step = 'searching';
            submitAndSearch();
            return;
        }
    }

    function handleSend() {
        var input = document.getElementById('asraa-chat-input');
        var val   = input.value.trim();

        // Allow sending an empty value only to skip the optional email step.
        if (!val && state.step !== 'email') return;

        if (val) input.value = '';

        if (val) addUserMsg(val);

        switch (state.step) {
            case 'name':
                state.name = val;
                state.step = 'phone';
                botTypeThen(
                    'Nice to meet you, *' + escHtml(val) + '*! 😊\n\nCould you share your *phone number*?',
                    null, 600
                );
                break;

            case 'phone':
                state.phone = val;
                state.step  = 'email';
                // Show WA button right after phone is captured
                showWACTA();
                botTypeThen(
                    'Got it! 📱\n\nCould you also share your *email address*?\n_(Type "skip" or press Send to skip)_',
                    null, 700
                );
                break;

            case 'email':
                if (val && val.toLowerCase() !== 'skip') {
                    // Basic email format check.
                    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)) {
                        botTypeThen('That doesn\'t look like a valid email. Please try again or type *skip* to continue.', null, 400);
                        return;
                    }
                    state.email = val;
                } else {
                    state.email = '';
                }
                state.step = 'location';
                botTypeThen(
                    'Almost there! 🙌\n\nWhich *location* are you interested in?\n_(e.g., Dubai Marina, Downtown, JVC)_',
                    null, 600
                );
                break;

            case 'location':
                state.location = val;
                state.step = 'budget';
                botTypeThen(
                    'What is your *budget*?\n_(Enter amount in AED, e.g., 1500000)_',
                    null, 600
                );
                break;

            case 'budget':
                state.budget = val;
                state.step = 'property_type';
                botTypeThen(
                    'What *type of property* are you looking for?',
                    function () {
                        showQuickReplies(['🏢 Apartment', '🏡 Villa', '🏘 Townhouse', '🏬 Office', '🏗 Studio']);
                    },
                    600
                );
                break;

            case 'property_type':
                state.propertyType = val.replace(/^[^\w]+/, '').trim();
                state.step = 'searching';
                hideQuickReplies();
                submitAndSearch();
                break;

            case 'done':
                botTypeThen('You can continue right away on WhatsApp 👆 Use the button above to chat now.', null, 400);
                break;
        }
    }

    function finishConversation() {
        botTypeThen(
            'Thank you, *' + escHtml(state.name) + '*! 🎉\n\n'
            + 'Let’s continue instantly on WhatsApp.\n\n'
            + 'Or click below to chat on WhatsApp right now! 👇',
            function () {
                updateWACTA();
                hideFooterInput();
            },
            800
        );
    }

    /**
     * 1. Submit the lead to get a lead_id.
     * 2. Search properties using location/budget/propertyType.
     * 3. Show results, then show final thank-you.
     */
    function submitAndSearch() {
        // Show "Searching…" immediately while the lead is being saved.
        botTypeThen('🔍 Searching for properties…', null, 600);

        var message = 'Intent: ' + state.requirement
            + '. Location: ' + state.location
            + '. Budget: AED ' + state.budget
            + '. Type: ' + state.propertyType;

        var leadPayload = {
            name:          state.name,
            phone:         state.phone,
            email:         state.email,
            intent:        state.requirement,
            location:      state.location,
            budget:        state.budget,
            type:          state.propertyType,
            property_type: state.propertyType,
            message:       message,
            source:        'AI Chatbot'
        };

        var headers = { 'Content-Type': 'application/json' };
        if (NONCE) headers['X-WP-Nonce'] = NONCE;

        // Submit lead first, then use the returned lead_id for the property search.
        fetch(API_URL, {
            method:  'POST',
            headers: headers,
            body:    JSON.stringify(leadPayload)
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            var leadId = (data && data.id) ? data.id : 0;
            searchProperties(leadId);
        })
        .catch(function () {
            // Even if lead save fails, still show property results.
            searchProperties(0);
        });
    }

    function searchProperties(leadId) {
        var headers = { 'Content-Type': 'application/json' };
        if (NONCE) headers['X-WP-Nonce'] = NONCE;

        fetch(SEARCH_URL, {
            method:  'POST',
            headers: headers,
            body:    JSON.stringify({
                location:      state.location,
                budget:        state.budget,
                property_type: state.propertyType,
                lead_id:       leadId
            })
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            state.step = 'done';
            if (data && data.success && data.properties && data.properties.length) {
                showPropertyCards(data.properties, data.exact_match);
            } else {
                botTypeThen('Sorry, no properties found right now. Please try a nearby location and I’ll show the latest available options.', null, 600);
                finishConversation();
            }
        })
        .catch(function () {
            state.step = 'done';
            botTypeThen('Could not load properties right now. Please try again in a moment.', null, 600);
            finishConversation();
        });
    }

    function showPropertyCards(properties, exactMatch) {
        var headline = exactMatch
            ? '✅ *Found matching properties:*'
            : '📍 *No exact match. Showing nearby options:*';

        botTypeThen(headline, function () {
            var body = document.getElementById('asraa-chat-body');

            properties.forEach(function (prop) {
                var card = document.createElement('div');
                card.className = 'asraa-prop-card';

                var imgHtml = safePropUrl(prop.image)
                    ? '<img class="asraa-prop-img" src="' + escHtml(prop.image) + '" alt="' + escHtml(prop.title || '') + '" loading="lazy">'
                    : '';

                var price = prop.price
                    ? 'AED ' + Number(prop.price).toLocaleString()
                    : '';

                var viewBtn = safePropUrl(prop.link)
                    ? '<a class="asraa-prop-view" href="' + escHtml(prop.link) + '" target="_blank" rel="noopener noreferrer">View Property ›</a>'
                    : '';

                card.innerHTML = imgHtml
                    + '<div class="asraa-prop-body">'
                    +   '<div class="asraa-prop-title">' + escHtml(prop.title || 'Property') + '</div>'
                    +   (price    ? '<div class="asraa-prop-price">' + escHtml(price) + '</div>' : '')
                    +   (prop.location ? '<div class="asraa-prop-location">📍 ' + escHtml(prop.location) + '</div>' : '')
                    + '</div>'
                    + viewBtn;

                body.appendChild(card);
                scrollBody();
            });

            // Give the user a moment to browse before the final CTA.
            setTimeout(finishConversation, 1200);
        }, 700);
    }

    /* ------------------------------------------------------------------ */
    /*  WHATSAPP CTA                                                        */
    /* ------------------------------------------------------------------ */
    function buildWALink() {
        var details = 'Hi! I\'m ' + state.name + '.\n'
            + (state.requirement  ? 'Intent: '   + state.requirement  + '\n' : '')
            + (state.location     ? 'Location: ' + state.location     + '\n' : '')
            + (state.budget       ? 'Budget: AED '  + state.budget    + '\n' : '')
            + (state.propertyType ? 'Type: '     + state.propertyType + '\n' : '')
            + (state.phone        ? 'Phone: '    + state.phone : '');
        return 'https://wa.me/' + WA_NUMBER + '?text=' + encodeURIComponent(details);
    }

    function showWACTA() {
        var cta = document.getElementById('asraa-wa-cta');
        cta.href          = buildWALink();
        cta.style.display = 'block';
    }

    function updateWACTA() {
        var cta = document.getElementById('asraa-wa-cta');
        cta.href = buildWALink();
    }

    function hideFooterInput() {
        var footer = document.getElementById('asraa-chat-footer');
        if (footer) footer.style.display = 'none';
    }

    /* ------------------------------------------------------------------ */
    /*  QUICK REPLIES                                                       */
    /* ------------------------------------------------------------------ */
    function showQuickReplies(options) {
        var container = document.getElementById('asraa-quick-replies');
        container.innerHTML = '';
        options.forEach(function (opt) {
            var btn = document.createElement('button');
            btn.className   = 'asraa-qr-btn';
            btn.textContent = opt;
            btn.addEventListener('click', function () { handleQuickReply(opt); });
            container.appendChild(btn);
        });
    }

    function hideQuickReplies() {
        document.getElementById('asraa-quick-replies').innerHTML = '';
    }

    /* ------------------------------------------------------------------ */
    /*  MESSAGE HELPERS                                                     */
    /* ------------------------------------------------------------------ */
    function addBotMsg(text) {
        var body = document.getElementById('asraa-chat-body');
        var msg  = document.createElement('div');
        msg.className = 'asraa-msg asraa-msg-bot';
        msg.innerHTML = fmtText(text);
        body.appendChild(msg);
        scrollBody();
    }

    function addUserMsg(text) {
        var body = document.getElementById('asraa-chat-body');
        var msg  = document.createElement('div');
        msg.className   = 'asraa-msg asraa-msg-user';
        msg.textContent = text;
        body.appendChild(msg);
        scrollBody();
    }

    /**
     * Shows a typing indicator, waits `delay` ms, then removes it and calls
     * addBotMsg(text) followed by optional callback.
     */
    function botTypeThen(text, callback, delay) {
        var body   = document.getElementById('asraa-chat-body');
        var typer  = document.createElement('div');
        typer.className = 'asraa-typing';
        typer.innerHTML = '<span></span><span></span><span></span>';
        body.appendChild(typer);
        scrollBody();

        setTimeout(function () {
            if (typer.parentNode) typer.parentNode.removeChild(typer);
            addBotMsg(text);
            if (callback) callback();
        }, delay || 800);
    }

    function scrollBody() {
        var body = document.getElementById('asraa-chat-body');
        if (body) body.scrollTop = body.scrollHeight;
    }

    /* ------------------------------------------------------------------ */
    /*  HELPERS                                                             */
    /* ------------------------------------------------------------------ */
    /** Bold *text*, italic _text_, convert \n → <br>. */
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

    /** Returns the URL only when it uses http or https – empty string otherwise. */
    function safePropUrl(url) {
        try {
            var parsed = new URL(String(url));
            return (parsed.protocol === 'http:' || parsed.protocol === 'https:') ? url : '';
        } catch (e) {
            return '';
        }
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
