/* global timeoffPublic, FullCalendar, jQuery */
(function ($) {
    'use strict';

    var cfg     = timeoffPublic;
    var nonce   = cfg.nonce;
    var ajaxUrl = cfg.ajaxUrl;
    var year    = cfg.year;

    /* ------------------------------------------------------------------ */
    /* Formulario de solicitud                                             */
    /* ------------------------------------------------------------------ */

    var $form    = $('#timeoff-request-form');
    var $start   = $('#timeoff-start');
    var $end     = $('#timeoff-end');
    var $preview = $('#timeoff-days-preview');
    var $msg     = $('#timeoff-form-message');

    function countDays(s, e) {
        if (!s || !e || s > e) return 0;
        var ms = new Date(e) - new Date(s);
        return Math.round(ms / 86400000) + 1;
    }

    function updatePreview() {
        var d = countDays($start.val(), $end.val());
        $preview.text(d > 0 ? d + ' días' : '—');
    }

    $start.on('change', updatePreview);
    $end.on('change', function () {
        if ($end.val() < $start.val()) $end.val($start.val());
        updatePreview();
    });

    $form.on('submit', function (e) {
        e.preventDefault();
        $msg.html('');

        var s = $start.val();
        var en = $end.val();
        var d = countDays(s, en);

        if (!s || !en || d <= 0) {
            showMsg(cfg.i18n.error, 'error');
            return;
        }
        if (d > cfg.summary.free_left) {
            showMsg(cfg.i18n.no_days, 'error');
            return;
        }

        var $btn = $form.find('button[type=submit]').prop('disabled', true);

        $.post(ajaxUrl, {
            action:     'timeoff_submit_request',
            nonce:      nonce,
            start_date: s,
            end_date:   en,
            note:       $('#timeoff-note').val(),
        }, function (res) {
            $btn.prop('disabled', false);
            if (res.success) {
                showMsg(cfg.i18n.success, 'success');
                $form[0].reset();
                $preview.text('—');
                cfg.summary.free_left = res.data.available;
                updateCounter(res.data.available);
                // Recargar la página para actualizar la tabla de solicitudes
                setTimeout(function () { location.reload(); }, 1500);
            } else {
                showMsg(res.data && res.data.message ? res.data.message : cfg.i18n.error, 'error');
            }
        }).fail(function () {
            $btn.prop('disabled', false);
            showMsg(cfg.i18n.error, 'error');
        });
    });

    /* ------------------------------------------------------------------ */
    /* Cancelar solicitud propia                                           */
    /* ------------------------------------------------------------------ */

    $(document).on('click', '.js-cancel-request', function () {
        if (!confirm(cfg.i18n.confirm_delete)) return;
        var $btn = $(this);
        var id   = $btn.data('id');

        $.post(ajaxUrl, {
            action: 'timeoff_delete_own_request',
            nonce:  nonce,
            id:     id,
        }, function (res) {
            if (res.success) {
                $btn.closest('tr').fadeOut(300, function () { $(this).remove(); });
            } else {
                alert(res.data && res.data.message ? res.data.message : cfg.i18n.error);
            }
        });
    });

    /* ------------------------------------------------------------------ */
    /* Calendario personal                                                 */
    /* ------------------------------------------------------------------ */

    var calEl = document.getElementById('timeoff-my-calendar');
    if (calEl) {
        var cal = new FullCalendar.Calendar(calEl, {
            initialView:  'dayGridMonth',
            initialDate:  year + '-01-01',
            locale:       'es',
            height:       'auto',
            headerToolbar: {
                left:   'prev,next today',
                center: 'title',
                right:  'dayGridMonth',
            },
            events: function (info, success, failure) {
                $.get(ajaxUrl, {
                    action: 'timeoff_get_my_events',
                    nonce:  nonce,
                    year:   year,
                }, function (res) {
                    if (res.success) success(res.data);
                    else failure('Error');
                });
            },
            eventClick: function (info) {
                var p = info.event.extendedProps;
                var msg = info.event.title;
                if (p.days)  msg += ' — ' + p.days + ' días';
                if (p.note)  msg += '\n' + p.note;
                alert(msg);
            },
        });
        cal.render();
    }

    /* ------------------------------------------------------------------ */
    /* Helpers                                                             */
    /* ------------------------------------------------------------------ */

    function showMsg(msg, type) {
        var cls = type === 'success' ? 'timeoff-notice-success' : 'timeoff-notice-error';
        $msg.html('<div class="timeoff-notice ' + cls + '">' + msg + '</div>');
        $('html, body').animate({ scrollTop: $msg.offset().top - 60 }, 300);
    }

    function updateCounter(available) {
        $('.counter-available .counter-num').text(available);
        $('.counter-available').toggleClass('counter-zero', available <= 0);
    }

})(jQuery);
