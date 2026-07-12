/**
 * Asraa CRM – Client Portal JS
 * Handles login form AJAX submission and reschedule visit CTA.
 */
(function ($) {
    'use strict';

    /* Login form */
    $(document).on('submit', '#asraa-client-login-form', function (e) {
        e.preventDefault();
        const $form = $(this);
        const $btn  = $form.find('[type=submit]').prop('disabled', true).text('Logging in…');
        const $msg  = $('#asraa-login-msg');

        $.post(asraaPortal.ajaxurl, $form.serialize(), function (res) {
            $btn.prop('disabled', false).text('Login →');
            if (res.success) {
                $msg.hide();
                window.location.href = res.data.redirect;
            } else {
                $msg.css({ background: '#fef2f2', color: '#991b1b', border: '1px solid #fca5a5' })
                    .text(res.data.message || 'Login failed.')
                    .show();
            }
        }).fail(function () {
            $btn.prop('disabled', false).text('Login →');
            $msg.css({ background: '#fef2f2', color: '#991b1b' })
                .text('Network error. Please try again.')
                .show();
        });
    });

})(jQuery);
