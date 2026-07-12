/* ================================================================
   Asraa CRM Enhanced – JavaScript
   Handles: lead edit AJAX, messaging dialogs, bulk actions
================================================================ */

jQuery(function ($) {
    'use strict';

    var ajaxurl = asraaCRM.ajaxurl;
    var nonce   = asraaCRM.nonce;

    // Utility: safely escape HTML for DOM insertion
    function escHtml(str) { return $('<div>').text(String(str)).html(); }

    // Global AJAX timeout (30 s) so requests never hang indefinitely on
    // a slow or dropped connection.
    $.ajaxSetup({ timeout: 30000 });

    console.log('[Asraa CRM] crm-enhanced.js loaded – ajaxurl:', ajaxurl);

    /* ============================================================
       LEAD EDIT FORM (lead-view)
    ============================================================ */
    $('#asraa-lead-edit-form').on('submit', function (e) {
        e.preventDefault();
        var $btn = $(this).find('[type=submit]');
        $btn.prop('disabled', true);
        console.log('[Asraa CRM] Saving lead id:', $('[name=lead_id]').val());

        $.post(ajaxurl, $(this).serialize(), function (resp) {
            $btn.prop('disabled', false);
            var $msg = $('#asraa-lead-save-msg');
            if (resp.success) {
                $msg.html('<span style="color:green;">✓ Saved</span>');
                console.log('[Asraa CRM] Lead saved OK');
            } else {
                var errMsg = (resp.data && resp.data.message) ? resp.data.message : (resp.data || 'Error');
                $msg.html('<span style="color:red;">✗ ' + errMsg + '</span>');
                console.error('[Asraa CRM] Lead save failed:', resp.data);
            }
        }).fail(function (xhr, status, err) {
            $btn.prop('disabled', false);
            console.error('[Asraa CRM] Lead save AJAX failed:', status, err);
            $('#asraa-lead-save-msg').html('<span style="color:red;">✗ AJAX error: ' + err + '</span>');
        });
    });

    /* ============================================================
       TEMPLATE LOADER HELPER
    ============================================================ */
    function loadTemplates(type, $select, callback) {
        $select.find('option:not(:first)').remove();
        $.post(ajaxurl, { action: 'get_message_templates', nonce: nonce, type: type }, function (resp) {
            if (resp.success && resp.data.length) {
                $.each(resp.data, function (_, t) {
                    $select.append(
                        $('<option>')
                            .val(t.id)
                            .text(t.title)
                            .data('tpl', t)
                    );
                });
            }
            if (typeof callback === 'function') callback();
        }).fail(function (xhr, status, err) {
            console.error('[Asraa CRM] loadTemplates failed:', status, err);
            if (typeof callback === 'function') callback();
        });
    }

    /* ============================================================
       BULK LEAD SELECTION
    ============================================================ */
    var $toolbar    = $('#asraa-bulk-toolbar');
    var $countLabel = $('#asraa-selected-count');

    function updateBulkToolbar() {
        var count = $('.asraa-lead-cb:checked').length;
        if (count > 0) {
            $toolbar.css('display', 'flex');
            $countLabel.text(count + ' lead' + (count === 1 ? '' : 's') + ' selected');
        } else {
            $toolbar.hide();
        }
    }

    $(document).on('change', '#asraa-select-all', function () {
        $('.asraa-lead-cb').prop('checked', this.checked);
        updateBulkToolbar();
    });

    $(document).on('change', '.asraa-lead-cb', function () {
        var all = $('.asraa-lead-cb').length;
        var checked = $('.asraa-lead-cb:checked').length;
        $('#asraa-select-all').prop('checked', all === checked).prop('indeterminate', checked > 0 && checked < all);
        updateBulkToolbar();
    });

    $('#asraa-deselect-all-btn').on('click', function () {
        $('.asraa-lead-cb, #asraa-select-all').prop('checked', false);
        updateBulkToolbar();
    });

    function getSelectedLeadIds() {
        return $('.asraa-lead-cb:checked').map(function () { return $(this).val(); }).get();
    }

    function getSelectedLeadsInfo() {
        return $('.asraa-lead-cb:checked').map(function () {
            return {
                id:    $(this).val(),
                name:  $(this).data('name'),
                email: $(this).data('email'),
                phone: $(this).data('phone')
            };
        }).get();
    }

    /* ============================================================
       SINGLE QUICK WHATSAPP (from leads list)
    ============================================================ */
    $(document).on('click', '.asraa-quick-whatsapp', function () {
        var leadId   = $(this).data('lead-id');
        var leadName = $(this).data('lead-name');
        var $sel     = $('#asraa-wa-template').empty().append('<option value="">-- Custom Message --</option>');

        $('#asraa-wa-lead-id').val(leadId);
        $('#asraa-wa-recipient').text('To: ' + leadName);
        $('#asraa-wa-message').val('');
        $('#asraa-wa-image').val('');
        $('#asraa-wa-image-preview').hide().find('img').attr('src', '');
        $('#asraa-wa-msg').html('');

        loadTemplates('whatsapp', $sel);
        $('#asraa-wa-dialog').fadeIn(200);
    });

    $('#asraa-wa-template').on('change', function () {
        var tpl = $(this).find(':selected').data('tpl');
        if (tpl) $('#asraa-wa-message').val(tpl.message || tpl.body || '');
    });

    $('#asraa-wa-send-btn').on('click', function () {
        var msg   = $('#asraa-wa-message').val().trim();
        var tplId = $('#asraa-wa-template').val();
        var img   = $('#asraa-wa-image').val().trim();
        var id    = $('#asraa-wa-lead-id').val();

        if (!msg) { $('#asraa-wa-msg').html('<span style="color:red;">Message is required.</span>'); return; }

        console.log('[Asraa CRM] Sending single WhatsApp to lead_id:', id);
        $(this).prop('disabled', true).text('Opening…');
        $.post(ajaxurl, {
            action: 'send_single_whatsapp', nonce: nonce,
            lead_id: id, message: msg, template_id: tplId, image_url: img
        }, function (resp) {
            console.log('[Asraa CRM] WhatsApp response:', resp);
            $('#asraa-wa-send-btn').prop('disabled', false).text('Send on WhatsApp Web');
            if (resp.success) {
                window.open(resp.data.url, '_blank');
                $('#asraa-wa-msg').html('<span style="color:green;">✓ Opened in WhatsApp.</span>');
                setTimeout(function () { $('#asraa-wa-dialog').fadeOut(200); }, 2000);
            } else {
                var errMsg = (resp.data && resp.data.message) ? resp.data.message : (resp.data || 'Error');
                $('#asraa-wa-msg').html('<span style="color:red;">✗ ' + errMsg + '</span>');
            }
        }).fail(function (xhr, status, err) {
            console.error('[Asraa CRM] WhatsApp AJAX failed:', status, err);
            $('#asraa-wa-send-btn').prop('disabled', false).text('Send on WhatsApp Web');
            $('#asraa-wa-msg').empty().append(
                $('<span>').css('color', 'red').text('✗ AJAX error: ' + err)
            );
        });
    });

    $('#asraa-wa-cancel-btn').on('click', function () { $('#asraa-wa-dialog').fadeOut(200); });

    /* ============================================================
       IMAGE PREVIEW HELPERS (single + bulk WA dialogs)
    ============================================================ */
    function initImagePreview($input, $previewWrap) {
        $input.on('input change', function () {
            var url = $(this).val().trim();
            if (url) {
                $previewWrap.find('img').attr('src', url);
                $previewWrap.show();
            } else {
                $previewWrap.hide().find('img').attr('src', '');
            }
        });
    }

    // Media picker – event-delegated so it works even when modals are shown
    // after page load. Buttons must carry .browse-image plus data-input and
    // (optionally) data-preview attributes pointing to the related field selectors.
    // Use a namespaced event and .off() first to ensure only one handler exists.
    var _mediaFrames = {};
    $(document).off('click.asraa-browse', '.browse-image').on('click.asraa-browse', '.browse-image', function (e) {
        e.preventDefault();
        if (typeof wp === 'undefined' || !wp.media) { return; }

        var $btn     = $(this);
        var inputSel = $btn.data('input')   || '';
        var prevSel  = $btn.data('preview') || '';

        if (!inputSel) { return; } // data-input is required

        var frameKey = inputSel; // unique per target input

        if (_mediaFrames[frameKey]) { _mediaFrames[frameKey].open(); return; }

        _mediaFrames[frameKey] = wp.media({
            title:    'Select or Upload Image',
            button:   { text: 'Use this image' },
            multiple: false
        });
        _mediaFrames[frameKey].on('select', function () {
            var attachment = _mediaFrames[frameKey].state().get('selection').first().toJSON();
            var url = attachment.url || '';
            // Re-resolve at select-time so dynamically-loaded modals are handled correctly.
            var $input   = $(inputSel);
            var $preview = prevSel ? $(prevSel) : $();
            $input.val(url).trigger('change');
            if (url && $preview.length) {
                $preview.find('img').attr('src', url);
                $preview.show();
            }
        });
        _mediaFrames[frameKey].open();
    });

    // Wire up preview from URL input (covers manual paste when media picker unavailable)
    initImagePreview($('#asraa-wa-image'), $('#asraa-wa-image-preview'));
    initImagePreview($('#asraa-bulk-wa-image'), $('#asraa-bulk-wa-image-preview'));

    /* ============================================================
       SINGLE QUICK EMAIL (from leads list)
    ============================================================ */
    $(document).on('click', '.asraa-quick-email', function () {
        var leadId    = $(this).data('lead-id');
        var leadName  = $(this).data('lead-name');
        var leadEmail = $(this).data('lead-email');
        var $sel      = $('#asraa-email-template').empty().append('<option value="">-- Custom Message --</option>');

        $('#asraa-email-lead-id').val(leadId);
        $('#asraa-email-recipient').text('To: ' + leadName + ' <' + leadEmail + '>');
        $('#asraa-email-subject').val('');
        $('#asraa-email-body').val('');
        $('#asraa-email-msg').html('');

        loadTemplates('email', $sel);
        $('#asraa-email-dialog').fadeIn(200);
    });

    $('#asraa-email-template').on('change', function () {
        var tpl = $(this).find(':selected').data('tpl');
        if (tpl) {
            if (tpl.subject) $('#asraa-email-subject').val(tpl.subject);
            if (tpl.body)    $('#asraa-email-body').val(tpl.body);
        }
    });

    $('#asraa-email-send-btn').on('click', function () {
        var subject = $('#asraa-email-subject').val().trim();
        var body    = $('#asraa-email-body').val().trim();
        var tplId   = $('#asraa-email-template').val();
        var id      = $('#asraa-email-lead-id').val();

        if (!subject) { $('#asraa-email-msg').html('<span style="color:red;">Subject is required.</span>'); return; }
        if (!body)    { $('#asraa-email-msg').html('<span style="color:red;">Body is required.</span>'); return; }

        console.log('[Asraa CRM] Sending single email to lead_id:', id);
        $(this).prop('disabled', true).text('Sending…');
        $.post(ajaxurl, {
            action: 'send_single_email', nonce: nonce,
            lead_id: id, subject: subject, body: body, template_id: tplId
        }, function (resp) {
            console.log('[Asraa CRM] Email response:', resp);
            $('#asraa-email-send-btn').prop('disabled', false).text('Send Email');
            if (resp.success) {
                $('#asraa-email-msg').html('<span style="color:green;">✓ Email sent.</span>');
                setTimeout(function () { $('#asraa-email-dialog').fadeOut(200); }, 2000);
            } else {
                var errMsg = (resp.data && resp.data.message) ? resp.data.message : (resp.data || 'Error');
                $('#asraa-email-msg').html('<span style="color:red;">✗ ' + errMsg + '</span>');
            }
        }).fail(function (xhr, status, err) {
            console.error('[Asraa CRM] Email AJAX failed:', status, err);
            $('#asraa-email-send-btn').prop('disabled', false).text('Send Email');
            $('#asraa-email-msg').html('<span style="color:red;">✗ AJAX error: ' + err + '</span>');
        });
    });

    $('#asraa-email-cancel-btn').on('click', function () { $('#asraa-email-dialog').fadeOut(200); });

    /* ============================================================
       BULK WHATSAPP – fully automated image + caption sender
       • Opens https://web.whatsapp.com/send?phone= in a named popup
       • Waits for the chat UI to load
       • Fetches image URL → Blob → File, injects into WA file input
         via DataTransfer, triggers attach, waits for preview, inserts
         caption text, clicks send button
       • Falls back to clipboard copy when cross-origin policy blocks
         DOM injection (standard browser security for different origins)
       • Fixed 4 s delay between contacts
       • Progress counter X / Y – Name
       • Stop button halts the loop at any point
    ============================================================ */
    var bulkWaLeads    = [];
    var bulkWaIndex    = 0;
    var bulkWaStopped  = false;
    var bulkWaWin      = null;
    var bulkWaImgBlob  = null; // pre-fetched image blob (null = no image)
    var bulkWaMsgTpl   = '';   // message template captured at send-start

    // Timing constants (all in milliseconds)
    var WA_FIRST_LOAD_MS      = 10000; // longer wait for QR scan / initial session
    var WA_SUBSEQUENT_LOAD_MS = 6000;  // shorter wait once session is established
    var WA_IMAGE_PREVIEW_MS   = 2000;  // time for the image preview to render
    var WA_BETWEEN_CONTACTS_MS = 4000; // pause between contacts

    function bulkWaCleanPhone(phone) {
        var s = String(phone || '').replace(/[^\d+]/g, '');
        if (s.charAt(0) === '+') { s = s.slice(1); }
        return s;
    }

    function bulkWaReplaceVars(tpl, lead) {
        return tpl
            .replace(/\{name\}/gi,  lead.name  || '')
            .replace(/\{phone\}/gi, lead.phone  || '')
            .replace(/\{email\}/gi, lead.email  || '');
    }

    function bulkWaShowMsg(html, color) {
        $('#asraa-bulk-wa-msg').html('<span style="color:' + (color || '#555') + ';">' + html + '</span>');
    }

    function bulkWaLogResult(lead, status) {
        $('#asraa-bulk-wa-results').append(
            '<div class="asraa-bulk-result-item">'
            + '<strong>' + escHtml(lead.name) + '</strong> '
            + '<span style="color:#25D366;">' + escHtml(status) + '</span>'
            + '</div>'
        );
    }

    function bulkWaFinish(stopped) {
        $('#asraa-bulk-wa-send-all-btn').prop('disabled', false).text('📤 Send to All');
        $('#asraa-bulk-wa-stop-btn').hide();
        if (stopped) {
            bulkWaShowMsg('⛔ Stopped at ' + bulkWaIndex + ' / ' + bulkWaLeads.length + '.', '#c0392b');
        } else {
            bulkWaShowMsg('✓ Completed: ' + bulkWaLeads.length + ' contacts processed.', 'green');
        }
    }

    /**
     * bulkWaInjectAndSend – step 2 of the automation pipeline.
     *
     * Called after the chat UI has loaded.  Attempts to inject the image
     * File into WA Web's hidden file input, waits for the preview, inserts
     * the caption, and clicks send.  Falls back to the clipboard approach
     * when a cross-origin SecurityError blocks DOM access.
     */
    function bulkWaInjectAndSend(lead, text) {
        if (bulkWaStopped) { bulkWaFinish(true); return; }

        var injected = false;

        // Attempt full DOM automation.
        // A SecurityError is thrown by the browser for any cross-origin
        // document access and is silently caught below.
        try {
            var doc = bulkWaWin.document;

            if (bulkWaImgBlob) {
                // 1. Locate the hidden file input WhatsApp Web uses for attachments
                var fileInput = doc.querySelector('input[type="file"][accept*="image"]')
                             || doc.querySelector('input[type="file"]');

                if (fileInput) {
                    // 2. Wrap the blob in a File using the popup's constructor
                    var ext      = (bulkWaImgBlob.type.split('/')[1] || 'jpg').replace(/[^a-z0-9]/g, '');
                    var fileName = 'image.' + ext;
                    var file     = new bulkWaWin.File([bulkWaImgBlob], fileName, { type: bulkWaImgBlob.type });

                    // 3. Inject via DataTransfer and trigger a change event
                    var dt = new bulkWaWin.DataTransfer();
                    dt.items.add(file);
                    Object.defineProperty(fileInput, 'files', { value: dt.files, configurable: true });
                    fileInput.dispatchEvent(new bulkWaWin.Event('change', { bubbles: true }));

                    injected = true;

                    // 4. Wait for the image preview to render, then insert caption and send
                    setTimeout(function () {
                        bulkWaInsertCaptionAndSend(lead, text, doc);
                    }, WA_IMAGE_PREVIEW_MS);

                    return; // inner timeout drives the next step
                }
            }

            // Text-only path: no image provided, or file input not found
            var msgInput = doc.querySelector('div[contenteditable="true"][data-tab]')
                         || doc.querySelector('footer div[contenteditable="true"]');
            if (msgInput) {
                msgInput.focus();
                doc.execCommand('selectAll', false, null);
                doc.execCommand('insertText', false, text);
                var sendBtnText = doc.querySelector('[data-testid="send"]')
                               || doc.querySelector('[aria-label="Send"]');
                if (sendBtnText) { sendBtnText.click(); }
                injected = true;
            }
        } catch (e) {
            // SecurityError from cross-origin access – fall through to clipboard fallback
        }

        if (injected) {
            bulkWaLogResult(lead, '✓ Sent');
        } else {
            // Image was already written to clipboard above; user pastes and sends.
            bulkWaLogResult(lead, '📋 Opened (paste image & send)');
        }

        bulkWaIndex++;
        setTimeout(sendBulkWhatsApp, WA_BETWEEN_CONTACTS_MS);
    }

    /**
     * bulkWaInsertCaptionAndSend – step 3 of the automation pipeline.
     *
     * Called after the image preview has rendered.  Inserts the caption
     * text and clicks the send button.
     */
    function bulkWaInsertCaptionAndSend(lead, text, doc) {
        if (bulkWaStopped) { bulkWaFinish(true); return; }

        try {
            // 5. Insert caption into the media caption input
            var captionEl = doc.querySelector(
                'div[data-testid="media-caption-input-container"] div[contenteditable="true"]'
            ) || doc.querySelector(
                'div[contenteditable="true"][data-tab]'
            );

            if (captionEl) {
                captionEl.focus();
                doc.execCommand('selectAll', false, null);
                doc.execCommand('insertText', false, text);
            }

            // 6. Click the send button
            var sendBtn = doc.querySelector('[data-testid="send"]')
                       || doc.querySelector('[data-testid="compose-btn-send"]')
                       || doc.querySelector('span[data-icon="send"]')
                       || doc.querySelector('[aria-label="Send"]');
            if (sendBtn) { sendBtn.click(); }
        } catch (innerErr) { /* cross-origin guard */ }

        bulkWaLogResult(lead, '✓ Sent');
        bulkWaIndex++;
        setTimeout(sendBulkWhatsApp, WA_BETWEEN_CONTACTS_MS);
    }

    /**
     * sendBulkWhatsApp – main automation loop.
     *
     * For each contact the function:
     *  1. Opens https://web.whatsapp.com/send?phone=NUMBER in a shared popup.
     *  2. Waits for the WA Web chat UI to load (10 s first contact / 6 s after).
     *  3. Calls bulkWaInjectAndSend to handle image attach + caption + send.
     *  4. Waits 4 s then recurses to the next contact.
     *
     * Cross-origin note: web.whatsapp.com is a different origin from the WP
     * admin. The browser's same-origin policy will throw a SecurityError on
     * any bulkWaWin.document access. All such accesses are wrapped in
     * try/catch blocks; on failure the function falls back to the clipboard
     * approach (image already copied before opening the popup) so the chat
     * is still opened and the user only needs to paste + send manually.
     */
    function sendBulkWhatsApp() {
        if (bulkWaStopped) { bulkWaFinish(true); return; }
        if (bulkWaIndex >= bulkWaLeads.length) { bulkWaFinish(false); return; }

        var lead    = bulkWaLeads[bulkWaIndex];
        var total   = bulkWaLeads.length;
        var current = bulkWaIndex + 1;
        var pct     = Math.round((current / total) * 100);
        var text    = bulkWaReplaceVars(bulkWaMsgTpl, lead);
        var phone   = bulkWaCleanPhone(lead.phone);
        var waUrl   = 'https://web.whatsapp.com/send?phone=' + encodeURIComponent(phone);

        // Update progress bar
        $('#asraa-bulk-wa-progress-wrap').show();
        $('#asraa-bulk-wa-progress-bar').css('width', pct + '%');
        $('#asraa-bulk-wa-progress-text').text(current + ' / ' + total + ' – ' + escHtml(lead.name));

        bulkWaShowMsg('⏳ ' + current + ' / ' + total + ' – Opening chat for ' + escHtml(lead.name) + '…', '#555');

        // Pre-copy image to clipboard so it is available for manual paste if
        // the DOM injection path is blocked by the cross-origin policy.
        if (bulkWaImgBlob && typeof ClipboardItem !== 'undefined'
                && navigator.clipboard && navigator.clipboard.write) {
            var clipItem = {};
            clipItem[bulkWaImgBlob.type] = bulkWaImgBlob;
            navigator.clipboard.write([ new ClipboardItem(clipItem) ]).catch(function () {});
        }

        // Open or reuse the shared popup window
        if (bulkWaWin && !bulkWaWin.closed) {
            bulkWaWin.location.href = waUrl;
        } else {
            bulkWaWin = window.open(waUrl, 'asraa_bulk_wa_window');
            if (!bulkWaWin) {
                bulkWaShowMsg('⚠️ Popup blocked – allow popups for this page and try again.', '#c0392b');
                bulkWaFinish(true);
                return;
            }
        }

        // Wait for WhatsApp Web chat UI to load.
        // First contact needs a longer wait (QR scan / initial session load).
        var waitMs = (bulkWaIndex === 0) ? WA_FIRST_LOAD_MS : WA_SUBSEQUENT_LOAD_MS;

        setTimeout(function () {
            bulkWaInjectAndSend(lead, text);
        }, waitMs);
    }

    $('#asraa-bulk-whatsapp-btn').on('click', function () {
        var leads = getSelectedLeadsInfo();
        if (!leads.length) return;

        var $recip = $('#asraa-bulk-wa-recipients').empty();
        $.each(leads, function (_, l) {
            $recip.append('<div>' + escHtml(l.name + ' – ' + (l.phone || 'no phone')) + '</div>');
        });

        var $sel = $('#asraa-bulk-wa-template').empty().append('<option value="">-- Custom Message --</option>');
        $('#asraa-bulk-wa-image').val('');
        $('#asraa-bulk-wa-image-preview').hide().find('img').attr('src', '');
        $('#asraa-bulk-wa-message').val('');
        $('#asraa-bulk-wa-msg').html('');
        $('#asraa-bulk-wa-results').empty();
        $('#asraa-bulk-wa-progress-wrap').hide();
        $('#asraa-bulk-wa-progress-bar').css('width', '0%');
        $('#asraa-bulk-wa-stop-btn').hide();
        bulkWaStopped = false;
        bulkWaImgBlob = null;

        loadTemplates('whatsapp', $sel);
        $('#asraa-bulk-wa-dialog').fadeIn(200);
    });

    $('#asraa-bulk-wa-template').on('change', function () {
        var tpl = $(this).find(':selected').data('tpl');
        if (tpl) $('#asraa-bulk-wa-message').val(tpl.message || tpl.body || '');
    });

    $('#asraa-bulk-wa-send-all-btn').on('click', function () {
        var msg    = $('#asraa-bulk-wa-message').val().trim();
        var imgUrl = $('#asraa-bulk-wa-image').val().trim();
        var leads  = getSelectedLeadsInfo().filter(function (l) { return l.phone && l.phone.trim(); });

        if (!msg)          { bulkWaShowMsg('Message is required.', 'red'); return; }
        if (!leads.length) { bulkWaShowMsg('No leads with phone numbers selected.', 'red'); return; }

        bulkWaLeads   = leads;
        bulkWaIndex   = 0;
        bulkWaStopped = false;
        bulkWaMsgTpl  = msg;
        bulkWaImgBlob = null;

        $(this).prop('disabled', true).text('Sending…');
        $('#asraa-bulk-wa-stop-btn').show();
        $('#asraa-bulk-wa-results').empty();

        if (imgUrl) {
            if (!/^https:\/\//i.test(imgUrl)) {
                bulkWaShowMsg('⚠️ Image URL must start with https://.', 'red');
                $(this).prop('disabled', false).text('📤 Send to All');
                $('#asraa-bulk-wa-stop-btn').hide();
                return;
            }
            bulkWaShowMsg('⏳ Fetching image…', '#555');
            fetch(imgUrl, { mode: 'cors' })
                .then(function (r) { return r.blob(); })
                .then(function (blob) {
                    var mime = (blob.type && blob.type.startsWith('image/')) ? blob.type : 'image/jpeg';
                    bulkWaImgBlob = new Blob([blob], { type: mime });
                    bulkWaShowMsg('🖼 Image ready – starting ' + leads.length + ' chats…', '#25D366');
                    sendBulkWhatsApp();
                })
                .catch(function () {
                    bulkWaImgBlob = null;
                    bulkWaShowMsg('⚠️ Could not fetch image (CORS/network). Continuing text-only.', '#e67e22');
                    sendBulkWhatsApp();
                });
        } else {
            bulkWaShowMsg('⏳ Starting ' + leads.length + ' chats…', '#25D366');
            sendBulkWhatsApp();
        }
    });

    $('#asraa-bulk-wa-stop-btn').on('click', function () {
        bulkWaStopped = true;
    });

    $('#asraa-bulk-wa-cancel-btn').on('click', function () {
        bulkWaStopped = true;
        if (bulkWaWin && !bulkWaWin.closed) { bulkWaWin.close(); }
        $('#asraa-bulk-wa-dialog').fadeOut(200);
    });

    /* ============================================================
       BULK EMAIL
    ============================================================ */
    $('#asraa-bulk-email-btn').on('click', function () {
        var leads = getSelectedLeadsInfo();
        if (!leads.length) return;

        var $recip = $('#asraa-bulk-email-recipients').empty();
        $.each(leads, function (_, l) {
            $recip.append('<div>' + escHtml(l.name + ' – ' + (l.email || 'no email')) + '</div>');
        });

        var $sel = $('#asraa-bulk-email-template').empty().append('<option value="">-- Custom Message --</option>');
        $('#asraa-bulk-email-subject').val('');
        $('#asraa-bulk-email-body').val('');
        $('#asraa-bulk-email-msg').html('');

        loadTemplates('email', $sel);
        $('#asraa-bulk-email-dialog').fadeIn(200);
    });

    $('#asraa-bulk-email-template').on('change', function () {
        var tpl = $(this).find(':selected').data('tpl');
        if (tpl) {
            if (tpl.subject) $('#asraa-bulk-email-subject').val(tpl.subject);
            if (tpl.body)    $('#asraa-bulk-email-body').val(tpl.body);
        }
    });

    $('#asraa-bulk-email-send-btn').on('click', function () {
        var subject = $('#asraa-bulk-email-subject').val().trim();
        var body    = $('#asraa-bulk-email-body').val().trim();
        var tplId   = $('#asraa-bulk-email-template').val();
        var ids     = getSelectedLeadIds();

        if (!subject) { $('#asraa-bulk-email-msg').html('<span style="color:red;">Subject is required.</span>'); return; }
        if (!body)    { $('#asraa-bulk-email-msg').html('<span style="color:red;">Body is required.</span>'); return; }
        if (!ids.length) { $('#asraa-bulk-email-msg').html('<span style="color:red;">No leads selected.</span>'); return; }

        $(this).prop('disabled', true).text('Sending…');
        $.post(ajaxurl, {
            action: 'send_bulk_email', nonce: nonce,
            lead_ids: ids, subject: subject, body: body, template_id: tplId
        }, function (resp) {
            console.log('[Asraa CRM] Bulk email response:', resp);
            $('#asraa-bulk-email-send-btn').prop('disabled', false).text('Send Bulk Email');
            if (resp.success) {
                var d = resp.data;
                $('#asraa-bulk-email-msg').html(
                    '<span style="color:green;">✓ ' + d.sent + ' sent, ' +
                    d.failed + ' failed, ' + d.skipped + ' skipped.</span>'
                );
                setTimeout(function () { $('#asraa-bulk-email-dialog').fadeOut(200); }, 3000);
            } else {
                var errMsg = (resp.data && resp.data.message) ? resp.data.message : (resp.data || 'Error');
                $('#asraa-bulk-email-msg').html('<span style="color:red;">✗ ' + errMsg + '</span>');
            }
        }).fail(function (xhr, status, err) {
            console.error('[Asraa CRM] Bulk email AJAX failed:', status, err);
            $('#asraa-bulk-email-send-btn').prop('disabled', false).text('Send Bulk Email');
            $('#asraa-bulk-email-msg').html('<span style="color:red;">✗ AJAX error: ' + err + '</span>');
        });
    });

    $('#asraa-bulk-email-cancel-btn').on('click', function () { $('#asraa-bulk-email-dialog').fadeOut(200); });

    /* ---- Close dialogs on overlay click ---- */
    $(document).on('click', '.asraa-dialog-overlay', function (e) {
        if ($(e.target).is('.asraa-dialog-overlay')) {
            $(this).fadeOut(200);
        }
    });

    /* ============================================================
       LEAD VIEW – WhatsApp & Email Dialogs
       (active only when asraaCRM.leadViewId is set)
    ============================================================ */
    if (asraaCRM.leadViewId) {
        var lvLeadId = asraaCRM.leadViewId;

        /* ---- Lead View WhatsApp ---- */
        $('#asraa-lv-wa-btn').on('click', function () {
            var $sel = $('#asraa-lv-wa-template').empty().append('<option value="">-- Custom Message --</option>');
            loadTemplates('whatsapp', $sel);
            $('#asraa-lv-wa-dialog').fadeIn(200);
        });

        $(document).on('change', '#asraa-lv-wa-template', function () {
            var tpl = $(this).find(':selected').data('tpl');
            if (tpl) $('#asraa-lv-wa-message').val(tpl.message || tpl.body || '');
        });

        $('#asraa-lv-wa-send-btn').on('click', function () {
            var msg   = $('#asraa-lv-wa-message').val().trim();
            var tplId = $('#asraa-lv-wa-template').val();
            var img   = $('#asraa-lv-wa-image').val().trim();
            if (!msg) { $('#asraa-lv-wa-msg').html('<span style="color:red;">Message is required.</span>'); return; }

            console.log('[Asraa CRM] Lead-view WhatsApp for lead_id:', lvLeadId);
            $(this).prop('disabled', true).text('Opening\u2026');
            $.post(ajaxurl, {
                action: 'send_single_whatsapp', nonce: nonce,
                lead_id: lvLeadId, message: msg, template_id: tplId, image_url: img
            }, function (resp) {
                console.log('[Asraa CRM] LV WhatsApp response:', resp);
                $('#asraa-lv-wa-send-btn').prop('disabled', false).text('Open WhatsApp');
                if (resp.success) {
                    window.open(resp.data.url, '_blank');
                    $('#asraa-lv-wa-msg').html('<span style="color:green;">\u2713 Logged &amp; opened in WhatsApp.</span>');
                    setTimeout(function () { $('#asraa-lv-wa-dialog').fadeOut(200); location.reload(); }, 2000);
                } else {
                    var errMsg = (resp.data && resp.data.message) ? resp.data.message : (resp.data || 'Error');
                    $('#asraa-lv-wa-msg').html('<span style="color:red;">\u2717 ' + errMsg + '</span>');
                }
            }).fail(function (xhr, status, err) {
                console.error('[Asraa CRM] LV WhatsApp AJAX failed:', status, err);
                $('#asraa-lv-wa-send-btn').prop('disabled', false).text('Open WhatsApp');
                $('#asraa-lv-wa-msg').html('<span style="color:red;">\u2717 AJAX error: ' + err + '</span>');
            });
        });

        $('#asraa-lv-wa-cancel-btn').on('click', function () { $('#asraa-lv-wa-dialog').fadeOut(200); });

        /* ---- Lead View Email ---- */
        $('#asraa-lv-email-btn').on('click', function () {
            var $sel = $('#asraa-lv-email-template').empty().append('<option value="">-- Custom Message --</option>');
            loadTemplates('email', $sel);
            $('#asraa-lv-email-dialog').fadeIn(200);
        });

        $(document).on('change', '#asraa-lv-email-template', function () {
            var tpl = $(this).find(':selected').data('tpl');
            if (tpl) {
                if (tpl.subject) $('#asraa-lv-email-subject').val(tpl.subject);
                if (tpl.body)    $('#asraa-lv-email-body').val(tpl.body);
            }
        });

        $('#asraa-lv-email-send-btn').on('click', function () {
            var subject = $('#asraa-lv-email-subject').val().trim();
            var body    = $('#asraa-lv-email-body').val().trim();
            var tplId   = $('#asraa-lv-email-template').val();

            if (!subject) { $('#asraa-lv-email-msg').html('<span style="color:red;">Subject is required.</span>'); return; }
            if (!body)    { $('#asraa-lv-email-msg').html('<span style="color:red;">Message body is required.</span>'); return; }

            $(this).prop('disabled', true).text('Sending\u2026');
            $.post(ajaxurl, {
                action: 'send_single_email', nonce: nonce,
                lead_id: lvLeadId, subject: subject, body: body, template_id: tplId
            }, function (resp) {
                console.log('[Asraa CRM] LV email response:', resp);
                $('#asraa-lv-email-send-btn').prop('disabled', false).text('Send Email');
                if (resp.success) {
                    $('#asraa-lv-email-msg').html('<span style="color:green;">\u2713 Email sent successfully.</span>');
                    setTimeout(function () { $('#asraa-lv-email-dialog').fadeOut(200); location.reload(); }, 2000);
                } else {
                    var errMsg = (resp.data && resp.data.message) ? resp.data.message : (resp.data || 'Error');
                    $('#asraa-lv-email-msg').html('<span style="color:red;">\u2717 ' + errMsg + '</span>');
                }
            }).fail(function (xhr, status, err) {
                console.error('[Asraa CRM] LV email AJAX failed:', status, err);
                $('#asraa-lv-email-send-btn').prop('disabled', false).text('Send Email');
                $('#asraa-lv-email-msg').html('<span style="color:red;">\u2717 AJAX error: ' + err + '</span>');
            });
        });

        $('#asraa-lv-email-cancel-btn').on('click', function () { $('#asraa-lv-email-dialog').fadeOut(200); });
    }
});

