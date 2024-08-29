<?php
function handle_payze_webhook() {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!empty($data['IdempotencyKey']) && !empty($data['PaymentStatus'])) {
        // Извлекаем ID заказа из метаданных
        $order_id_parts = explode('_', $data['IdempotencyKey']);
        $order_id = $order_id_parts[0];
        $order = wc_get_order($order_id);

        if ($order) {
            $status = get_option('woocommerce_payze_settings')['success_status'];

            if ($data['PaymentStatus'] === 'Captured') {
                $order->payment_complete($data['PaymentId']);
                $order->update_status($status, __('Оплата успешно завершена через Payze', 'woocommerce'));

                // Получаем квитанцию
                $receipt = get_payment_receipt($data['PaymentId']);
                if ($receipt) {
                    // Сохранить квитанцию как метаданные заказа
                    $order->update_meta_data('_payment_receipt', base64_encode($receipt)); // Сохраняем в формате base64
                    $order->save();
                } else {
                    error_log('Ошибка при получении квитанции для заказа ' . $order_id);
                }
                
            } elseif ($data['PaymentStatus'] === 'Blocked') {
                $order->update_status('on-hold', __('Платеж заблокирован. Ожидается подтверждение.', 'woocommerce'));
            } else {
                $order->update_status('failed', __('Платеж не был завершен.' . $data['PaymentStatus'] . $data['Refund']['Status'] , 'woocommerce'));
            }
        }
    }

    wp_send_json_success();
    exit;
}
add_action('woocommerce_api_payze_webhook', 'handle_payze_webhook');
?>
