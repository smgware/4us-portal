$(function () {
    $('#reportPageUrl').val(window.location.href);

    $('#errorReportForm').on('submit', function (e) {
        e.preventDefault();

        const $btn = $('#errorReportBtn');
        const $alert = $('#errorReportAlert');

        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Küldés...');
        $alert.addClass('d-none').removeClass('alert-success alert-danger').text('');

        $.ajax({
            url: APP_BASE_URL + '/error_reporting/save',
            method: 'POST',
            dataType: 'json',
            data: {
                name: $('#reportName').val(),
                email: $('#reportEmail').val(),
                subject: $('#reportSubject').val(),
                message: $('#reportMessage').val(),
                page_url: $('#reportPageUrl').val()
            },
            success: function (res) {
                if (res.success) {
                    $alert.removeClass('d-none').addClass('alert-success').text(res.message);
                    $('#errorReportForm')[0].reset();
                    $('#reportPageUrl').val(window.location.href);
                    return;
                }

                $alert.removeClass('d-none').addClass('alert-danger').text(res.message || 'Nem sikerült menteni.');
            },
            error: function (xhr) {
                let msg = 'Szerverhiba történt.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
                $alert.removeClass('d-none').addClass('alert-danger').text(msg);
            },
            complete: function () {
                $btn.prop('disabled', false).html('<i class="bi bi-send me-1"></i> Hibabejelentés küldése');
            }
        });
    });
});
