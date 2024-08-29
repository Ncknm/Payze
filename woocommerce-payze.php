<?php
/*
Plugin Name: WooCommerce Payze Gateway
Description: Платежный шлюз Payze для WooCommerce.
Version: 1.1
Author: Payze
*/

// Подключение классов
// Инициализация Payze gateway
function init_payze_gateway() {
    if (class_exists('WC_Payment_Gateway')) {
        add_filter('woocommerce_payment_gateways', 'add_payze_gateway_class', 100);
        // Подключение необходимых классов
        require_once plugin_dir_path(__FILE__) . 'includes/class-payze-payment.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-payze-webhook.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-payze-receipt.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-payze-admin.php';
    }
}
add_action('plugins_loaded', 'init_payze_gateway', 11);


function add_payze_gateway_class($gateways) {
    $gateways[] = 'WC_Gateway_Payze';
    return $gateways;
}
?>
