<?php
function get_payment_receipt($payment_id) {
    $ch = curl_init();
    $api_key = get_option('woocommerce_payze_settings')['api_key'];
    $secret_key = get_option('woocommerce_payze_settings')['secret_key'];

    curl_setopt($ch, CURLOPT_URL, "https://payze.io/v2/api/payment/receipt?TransactionId={$payment_id}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
    curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "accept: application/pdf",
        "authorization: {$api_key}:{$secret_key}",
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    if ($response) {
        return $response; // Возвращает PDF файл квитанции
    } else {
        return false; // Возвращает false в случае ошибки
    }
}
?>
