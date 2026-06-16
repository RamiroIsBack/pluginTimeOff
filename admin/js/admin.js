/* global timeoffAdmin, FullCalendar, jQuery */
(function ($) {
    'use strict';

    var cfg     = timeoffAdmin;
    var nonce   = cfg.nonce;
    var ajaxUrl = cfg.ajaxUrl;

    /* ------------------------------------------------------------------ */
    /* Aprobar / Rechazar / Eliminar solicitudes                           */
    /* ------------------------------------------------------------------ */

    var rejectTargetId = null;

    $(document).on('click', '.js-approve', function () {
        if ( ! confirm(cfg.i18n.confirm_approve) ) return;
        var id = $(this).data('id');
        ajaxAction('timeoff_approve_request', { id: id, note: '' }, function () {
            removeRow(id);
        });
    });

    $(document).on('click', '.js-reject', function () {
        rejectTargetId = $(this).data('id');
        $('#timeoff-reject-note').val('');
        $('#timeoff-reject-modal').fadeIn(150);
    });

    $('#timeoff-reject-confirm').on('click', function () {
        if ( ! rejectTargetId ) return;
        var note = $('#timeoff-reject-note').val();
        ajaxAction('timeoff_reject_request', { id: rejectTargetId, note: note }, function () {
            removeRow(rejectTargetId);
            $('#timeoff-reject-modal').fadeOut(150);
            rejectTargetId = null;
        });
    });

    $('#timeoff-reject-cancel').on('click', function () {
        $('#timeoff-reject-modal').fadeOut(150);
        rejectTargetId = null;
    });

    $(document).on('click', '.js-delete', function () {
        if ( ! confirm(cfg.i18n.confirm_delete) ) return;
        var id = $(this).data('id');
        ajaxAction('timeoff_delete_request', { id: id }, function () {
            removeRow(id);
        });
    });

    function removeRow(id) {
        $('tr[data-id="' + id + '"]').fadeOut(300, function () { $(this).remove(); });
    }

    /* ------------------------------------------------------------------ */
    /* Guardar configuración de empleado                                   */
    /* ------------------------------------------------------------------ */

    $(document).on('click', '.js-save-employee', function () {
        var $btn = $(this);
        var $row = $btn.closest('tr');
        var emp  = $btn.data('emp');
        var year = $btn.data('year');

        var data = {
            action:       'timeoff_save_employee',
            nonce:        nonce,
            employee_id:  emp,
            year:         year,
            total_days:   $row.find('.js-total-days').val(),
            period_start: $row.find('.js-period-start').val(),
            period_end:   $row.find('.js-period-end').val(),
        };

        $btn.prop('disabled', true).text('…');

        $.post(ajaxUrl, data, function (res) {
            $btn.prop('disabled', false).text($btn.data('label') || 'Guardar');
            if ( res.success ) {
                var s = res.data;
                $row.find('.js-fixed-days').text( s.fixed || '—' );
                $row.find('.js-free-left').text( s.free_left )
                    .toggleClass('timeoff-zero', s.free_left <= 0);
                showNotice(cfg.i18n.saved, 'success');
            } else {
                showNotice(res.data && res.data.message ? res.data.message : cfg.i18n.error, 'error');
            }
        }).fail(function () {
            $btn.prop('disabled', false);
            showNotice(cfg.i18n.error, 'error');
        });
    });

    $(document).on('click', '.js-delete-period', function () {
        if ( ! confirm('¿Eliminar el período fijado?') ) return;
        var $btn = $(this);
        $.post(ajaxUrl, {
            action:      'timeoff_delete_period',
            nonce:       nonce,
            employee_id: $btn.data('emp'),
            year:        $btn.data('year'),
        }, function (res) {
            if ( res.success ) location.reload();
        });
    });

    /* ------------------------------------------------------------------ */
    /* Calendario FullCalendar                                             */
    /* ------------------------------------------------------------------ */

    var calEl = document.getElementById('timeoff-calendar');
    if ( calEl ) {
        initCalendar(calEl, cfg.year, 'timeoff_get_events');
    }

    function initCalendar(el, year, action) {
        var cal = new FullCalendar.Calendar(el, {
            initialView:  'dayGridMonth',
            initialDate:  year + '-07-01',
            locale:       'es',
            height:       'auto',
            headerToolbar: {
                left:   'prev,next today',
                center: 'title',
                right:  'dayGridMonth,dayGridYear'
            },
            events: function (info, success, failure) {
                $.get(ajaxUrl, { action: action, nonce: nonce, year: year }, function (res) {
                    if ( res.success ) success(res.data);
                    else failure('Error al cargar eventos');
                });
            },
            eventClick: function (info) {
                showPopover(info);
            },
        });
        cal.render();
    }

    /* Popover de evento */
    var $popover = $('#timeoff-event-popover');

    function showPopover(info) {
        var p = info.event.extendedProps;
        $('#popover-title').text(info.event.title);
        $('#popover-meta').text(
            info.event.startStr + ' – ' + (info.event.endStr || info.event.startStr) +
            (p.days_count ? ' (' + p.days_count + ' días)' : '')
        );
        $('#popover-note').text(p.note || p.employee || '');

        $popover
            .css({ top: info.jsEvent.clientY + 10, left: info.jsEvent.clientX + 10 })
            .fadeIn(150);
    }

    $(document).on('click', function (e) {
        if ( !$(e.target).closest('#timeoff-event-popover, .fc-event').length ) {
            $popover.fadeOut(100);
        }
    });

    /* ------------------------------------------------------------------ */
    /* Grupos de cobertura                                                 */
    /* ------------------------------------------------------------------ */

    $('#timeoff-coverage-form').on('submit', function (e) {
        e.preventDefault();
        var $form = $(this);
        var $msg  = $('#timeoff-coverage-msg');
        var members = [];
        $form.find('input[name="members[]"]:checked').each(function () {
            members.push($(this).val());
        });

        var data = {
            action:      'timeoff_save_coverage_group',
            nonce:       nonce,
            id:          $form.find('[name=id]').val() || 0,
            name:        $form.find('[name=name]').val(),
            min_present: $form.find('[name=min_present]').val(),
            description: $form.find('[name=description]').val(),
            members:     members,
        };

        $('#cov-save-btn').prop('disabled', true);

        $.post(ajaxUrl, data, function (res) {
            $('#cov-save-btn').prop('disabled', false);
            if (res.success) {
                showNotice(cfg.i18n.saved, 'success');
                setTimeout(function () { location.reload(); }, 800);
            } else {
                $msg.html('<div class="notice notice-error inline"><p>' +
                    (res.data && res.data.message ? res.data.message : cfg.i18n.error) + '</p></div>');
            }
        }).fail(function () {
            $('#cov-save-btn').prop('disabled', false);
            $msg.html('<div class="notice notice-error inline"><p>' + cfg.i18n.error + '</p></div>');
        });
    });

    $(document).on('click', '.js-delete-group', function () {
        if (!confirm(cfg.i18n.confirm_delete)) return;
        var id = $(this).data('id');
        $.post(ajaxUrl, {
            action: 'timeoff_delete_coverage_group',
            nonce:  nonce,
            id:     id,
        }, function (res) {
            if (res.success) location.reload();
        });
    });

    /* ------------------------------------------------------------------ */
    /* Helpers                                                             */
    /* ------------------------------------------------------------------ */

    function ajaxAction(action, extraData, onSuccess) {
        $.post(ajaxUrl, $.extend({ action: action, nonce: nonce }, extraData), function (res) {
            if ( res.success ) onSuccess(res);
            else showNotice(res.data && res.data.message ? res.data.message : cfg.i18n.error, 'error');
        }).fail(function () {
            showNotice(cfg.i18n.error, 'error');
        });
    }

    function showNotice(msg, type) {
        var cls = type === 'success' ? 'notice-success' : 'notice-error';
        var $n  = $('<div class="notice ' + cls + ' is-dismissible" style="margin:8px 0"><p>' + msg + '</p></div>');
        $('.wrap h1').first().after($n);
        setTimeout(function () { $n.fadeOut(400, function () { $n.remove(); }); }, 3000);
    }

})(jQuery);
