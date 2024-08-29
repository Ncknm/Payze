<?php
// Добавление кнопки "Сгенерировать чек" на странице заказа
add_action('woocommerce_admin_order_data_after_order_details', 'add_generate_receipt_button');
function add_generate_receipt_button($order) {
    if ($order->is_paid()) {
        echo '<button id="generate-receipt" class="button">Сгенерировать чек</button>';
        echo '<div id="receipt-result"></div>';
    }
}

// Обработка AJAX запроса
add_action('wp_ajax_generate_receipt', 'generate_receipt_ajax');
function generate_receipt_ajax() {
    $order_id = intval($_POST['order_id']);
    $order = wc_get_order($order_id);

    if ($order) {
        $payment_id = $order->get_meta('_transaction_id');
        $receipt = get_payment_receipt($payment_id);

        if ($receipt) {
            // Генерация ссылки для скачивания чека
            $receipt_link = 'data:application/pdf;base64,' . base64_encode($receipt);
            wp_send_json_success(['receipt_link' => $receipt_link]);

        } else {
            wp_send_json_error('Не удалось получить чек.');
        }
    } else {
        wp_send_json_error('Заказ не найден.');
    }
}

// JavaScript для обработки нажатия кнопки
add_action('admin_footer', 'generate_receipt_js');
function generate_receipt_js() {
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        $('#generate-receipt').on('click', function(e) {
            e.preventDefault();
            var order_id = <?php echo get_the_ID(); ?>;
            
            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: 'generate_receipt',
                    order_id: order_id,
                },
                success: function(response) {
                    if (response.success) {
                        $('#receipt-result').html('<a href="' + response.data.receipt_link + '" download="receipt.pdf">Скачать чек</a>');
                    } else {
                        $('#receipt-result').text('Ошибка: ' + response.data);
                    }
                }
            });
        });
    });
    </script>
    <?php
}

// Функция для отображения квитанции в админке
add_action('woocommerce_admin_order_data_after_order_details', 'display_payment_receipt');
function display_payment_receipt($order) {
    $receipt = $order->get_meta('_payment_receipt');
    if ($receipt) {
        echo '<p><strong>' . __('Квитанция об оплате', 'woocommerce') . ':</strong> <a href="data:application/pdf;base64,' . esc_attr($receipt) . '" download="receipt.pdf">' . __('Скачать квитанцию', 'woocommerce') . '</a></p>';
    }
}
?>
