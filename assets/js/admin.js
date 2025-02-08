(function($) {
    'use strict';

    $(document).ready(function() {
        $('.wb-update-status').on('click', function() {
            const button = $(this);
            const row = button.closest('tr');
            const orderId = button.data('order-id');
            const status = row.find('.wb-status-select').val();

            row.addClass('wb-status-updating');
            button.prop('disabled', true);

            $.ajax({
                url: wbAdmin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'wb_update_order_status',
                    order_id: orderId,
                    status: status,
                    nonce: wbAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert(wbAdmin.messages.success);
                    } else {
                        alert(wbAdmin.messages.error);
                    }
                },
                error: function() {
                    alert(wbAdmin.messages.error);
                },
                complete: function() {
                    row.removeClass('wb-status-updating');
                    button.prop('disabled', false);
                }
            });
        });
    });
})(jQuery); 