/* ================================================================
   Asraa CRM – Properties JavaScript
================================================================ */

jQuery(function ($) {
    'use strict';

    var ajaxurl = asraaCRM.ajaxurl;
    var nonce   = asraaCRM.nonce;

    console.log('[Asraa CRM] properties.js loaded – ajaxurl:', ajaxurl);

    /* ---- Filter ---- */
    $('#filter-city, #filter-status').on('input change', function () {
        var city   = $('#filter-city').val().toLowerCase();
        var status = $('#filter-status').val().toLowerCase();

        $('#asraa-properties-table tbody tr').each(function () {
            var rowCity   = $(this).data('city')   ? $(this).data('city').toString().toLowerCase()   : '';
            var rowStatus = $(this).data('status') ? $(this).data('status').toString().toLowerCase() : '';
            var show = (!city || rowCity.includes(city)) && (!status || rowStatus === status);
            $(this).toggle(show);
        });
    });

    /* ---- Open Add Modal ---- */
    $('#asraa-add-property-btn').on('click', function () {
        $('#asraa-property-form')[0].reset();
        $('#prop-id').val('');
        $('#prop-source-post-id').val('');
        $('#asraa-import-listing').val('');
        $('#asraa-import-msg').html('');
        $('input[name=action]').val('asraa_save_property');
        $('#asraa-modal-title').text('➕ Add Property');
        $('#asraa-property-modal').fadeIn(200);
        console.log('[Asraa CRM] Add Property modal opened');
    });

    /* ---- Import from existing site listing ---- */
    $(document).on('change', '#asraa-import-listing', function () {
        var postId = $(this).val();
        var $msg = $('#asraa-import-msg');
        $msg.html('');

        if (!postId) return;

        $.post(ajaxurl, { action: 'asraa_import_site_listing', nonce: nonce, post_id: postId }, function (resp) {
            if (!resp.success) {
                $msg.html('<span style="color:red;">✗ ' + (resp.data && resp.data.message ? resp.data.message : 'Could not load listing') + '</span>');
                return;
            }

            var d = resp.data;
            $('input[name=title]').val(d.title);
            if (d.price) $('input[name=price]').val(d.price);
            if (d.location) $('input[name=location]').val(d.location);
            if (d.property_type) $('input[name=property_type]').val(d.property_type);
            if (d.transaction_type) $('select[name=transaction_type]').val(d.transaction_type);
            $('#prop-source-post-id').val(d.post_id);

            if (d.already_imported) {
                $msg.html('<span style="color:#b45309;">⚠ Already imported as Property #' + d.already_imported + '. Saving will be blocked to avoid a duplicate.</span>');
            } else {
                $msg.html('<span style="color:green;">✓ Pre-filled — review the fields below, then Save.</span>');
            }
        }).fail(function () {
            $msg.html('<span style="color:red;">✗ AJAX request failed.</span>');
        });
    });

    /* ---- Close Modal ---- */
    $('#asraa-close-modal, .asraa-modal-overlay').on('click', function () {
        $('#asraa-property-modal').fadeOut(200);
    });

    /* ---- Edit ---- */
    $(document).on('click', '.asraa-edit', function () {
        var row = $(this).closest('tr');

        $('#prop-id').val(row.data('id'));
        $('input[name=title]').val(row.data('title'));
        $('select[name=transaction_type]').val(row.data('transaction'));
        $('input[name=property_type]').val(row.data('type'));
        $('input[name=builder_name]').val(row.data('builder'));
        $('input[name=city]').val(row.data('city'));
        $('input[name=location]').val(row.data('location'));
        $('input[name=price]').val(row.data('price'));
        $('select[name=status]').val(row.data('status'));
        $('#prop-source-post-id').val(row.data('source-post-id') || '');
        $('#asraa-import-listing').val('');
        $('#asraa-import-msg').html('');

        $('input[name=action]').val('asraa_update_property');
        $('#asraa-modal-title').text('✏️ Edit Property');
        $('#asraa-property-modal').fadeIn(200);
        console.log('[Asraa CRM] Edit Property modal opened for id:', row.data('id'));
    });

    /* ---- Delete ---- */
    $(document).on('click', '.asraa-delete', function () {
        if (!confirm('Delete this property?')) return;

        var id = $(this).closest('tr').data('id');
        console.log('[Asraa CRM] Deleting property id:', id);

        $.post(ajaxurl, { action: 'asraa_delete_property', nonce: nonce, id: id }, function (resp) {
            console.log('[Asraa CRM] Delete response:', resp);
            if (resp.success) {
                location.reload();
            } else {
                alert(resp.data && resp.data.message ? resp.data.message : (resp.data || 'Delete failed'));
            }
        }).fail(function (xhr, status, err) {
            console.error('[Asraa CRM] Delete AJAX failed:', status, err);
            alert('AJAX request failed: ' + err);
        });
    });

    /* ---- Save / Update Form ---- */
    $('#asraa-property-form').on('submit', function (e) {
        e.preventDefault();
        var $btn  = $(this).find('[type=submit]');
        var $msg  = $('#asraa-property-msg');
        $btn.prop('disabled', true);
        $msg.html('');

        var formData = $(this).serialize();
        console.log('[Asraa CRM] Submitting property form – action:', $('input[name=action]').val());

        $.post(ajaxurl, formData, function (resp) {
            console.log('[Asraa CRM] Save/Update response:', resp);
            $btn.prop('disabled', false);
            if (resp.success) {
                location.reload();
            } else {
                var errMsg = (resp.data && resp.data.message) ? resp.data.message : (resp.data || 'Error saving property');
                $msg.html('<span style="color:red;">✗ ' + errMsg + '</span>');
            }
        }).fail(function (xhr, status, err) {
            console.error('[Asraa CRM] Save AJAX failed:', status, err, xhr.responseText);
            $btn.prop('disabled', false);
            $msg.html('<span style="color:red;">✗ AJAX request failed: ' + err + '</span>');
        });
    });
});
