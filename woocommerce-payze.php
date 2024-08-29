<?php
/*
Plugin Name: WooCommerce Payze Gateway
Description: Платежный шлюз Payze для WooCommerce.
Version: 1.0
Author: Payze
*/
//
// Инициализация Payze gateway
function init_payze_gateway() {
    if (class_exists('WC_Payment_Gateway')) {
        add_filter('woocommerce_payment_gateways', 'add_payze_gateway_class', 100);
    }
}
add_action('plugins_loaded', 'init_payze_gateway');

function add_payze_gateway_class($gateways) {
    $gateways[] = 'WC_Gateway_Payze';
    return $gateways;
}

function define_payze_gateway_class() {
    if (class_exists('WC_Payment_Gateway')) {

        class WC_Gateway_Payze extends WC_Payment_Gateway {
            private $api_key;
            private $secret_key;

            public function __construct() {
                $this->id                 = 'payze';
                $this->has_fields         = true;
                $this->method_title       = 'Payze';
                $this->method_description = 'Оплата через Payze';

                $this->init_form_fields();
                $this->init_settings();

                $this->title        = $this->get_option('title');
                $this->description  = $this->get_option('description');
                $this->api_key      = $this->get_option('api_key');
                $this->secret_key   = $this->get_option('secret_key');
                $this->success_status = $this->get_option('success_status');

                $this->supports = array('products');

                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            }

            public function init_form_fields() {
                $this->form_fields = array(
                    'enabled' => array(
                        'title'   => 'Включить/Отключить',
                        'label'   => 'Включить Payze Gateway',
                        'type'    => 'checkbox',
                        'default' => 'no',
                    ),
                    'title' => array(
                        'title'       => 'Название',
                        'type'        => 'text',
                        'description' => 'Название, которое пользователь увидит при оформлении заказа.',
                        'default'     => 'Payze',
                    ),
                    'description' => array(
                        'title'       => 'Описание',
                        'type'        => 'textarea',
                        'description' => 'Описание, которое пользователь увидит при оформлении заказа.',
                        'default'     => 'Оплатите через Payze',
                    ),
                    'api_key' => array(
                        'title'       => 'API Key',
                        'type'        => 'text',
                        'description' => 'Введите ваш API Key.',
                    ),
                    'secret_key' => array(
                        'title'       => 'Secret Key',
                        'type'        => 'text',
                        'description' => 'Введите ваш Secret Key.',
                    ),
                    'success_status' => array(
                        'title'       => 'Статус при успешной оплате',
                        'type'        => 'select',
                        'description' => 'Выберите статус, который будет установлен при успешной оплате.',
                        'default'     => 'processing',
                        'options'     => wc_get_order_statuses(),
                    ),
                );
            }

            public function is_available() {
                return 'yes' === $this->get_option('enabled');
            }

            public function payment_fields() {
                echo '<p>' . esc_html($this->description) . '</p>';
            }

            public function process_payment($order_id) {
                $order = wc_get_order($order_id);
                $site_url = get_site_url();
                $success_redirect = add_query_arg(
                    array('key' => $order->get_order_key()),
                    wc_get_checkout_url() . "order-received/" . $order_id
                );
                
                $data = array(
                    'source'          => 'Card',
                    'amount'          => $order->get_total(),
                    'language'        => 'RU',
                    'currency'        => 'UZS',
                    'idempotencyKey'  => (string)$order_id . '_' . time(),
                    'hooks'           => array(
                        'webhookGateway'        => $site_url . '/wc-api/payze_webhook',
                        'successRedirectGateway' => $success_redirect,
                        'errorRedirectGateway'  => $site_url . '/checkout-error',
                    ),
                );

                $ch = curl_init();
                curl_setopt_array($ch, array(
                    CURLOPT_URL            => 'https://payze.io/v2/api/payment',
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_CUSTOMREQUEST  => 'PUT',
                    CURLOPT_HTTPHEADER     => array(
                        'accept: application/json',
                        'content-type: application/json',
                        "authorization: {$this->api_key}:{$this->secret_key}",
                        'user-agent: python-app/v1'
                    ),
                    CURLOPT_POSTFIELDS     => json_encode($data),
                ));

                $response = curl_exec($ch);
                $responseData = json_decode($response, true);

                if (!empty($responseData['data']['payment']['transactionId'])) {
                    $order->update_meta_data('_transaction_id', $responseData['data']['payment']['transactionId']);
                    $order->save();

                    return array(
                        'result'   => 'success',
                        'redirect' => $responseData['data']['payment']['paymentUrl']
                    );
                } else {
                    wc_add_notice('Ошибка: не удалось получить ссылку на оплату.', 'error');
                    return;
                }
            }
        }
    }
}
add_action('plugins_loaded', 'define_payze_gateway_class');

// Обработка вебхуков
add_action('woocommerce_api_payze_webhook', 'handle_payze_webhook');



function get_payment_receipt($payment_id) {
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, "https://payze.io/v2/api/payment/receipt/{$payment_id}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "accept: application/pdf",
        "authorization: {$this->api_key}:{$this->secret_key}",
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    if ($response) {
        return $response; // Возвращает PDF файл квитанции
    } else {
        return false; // Возвращает false в случае ошибки
    }
}





function handle_payze_webhook() {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!empty($data['IdempotencyKey']) && !empty($data['PaymentStatus'])) {
        $order_id_parts = explode('_', $data['IdempotencyKey']);
        $order_id = $order_id_parts[0];
        $order = wc_get_order($order_id);

        if ($order) {
            $status = get_option('woocommerce_payze_settings')['success_status'];

            switch ($data['PaymentStatus']) {
                case 'Captured':
                    $order->payment_complete($data['PaymentId']);
                    $order->update_status($status, __('Оплата успешно завершена через Payze', 'woocommerce'));

                    // Получаем квитанцию
                    $receipt = get_payment_receipt($data['PaymentId']);

                    if ($receipt) {
                        // Сохранить квитанцию как метаданные заказа или отправить по email
                        $order->update_meta_data('_payment_receipt', base64_encode($receipt)); // Сохраняем в формате base64
                        $order->save();
                    } else {
                        // Обработка ошибок, если квитанция не была получена
                        error_log('Ошибка при получении квитанции для заказа ' . $order_id);
                    }
                    break;
                case 'Blocked':
                    $order->update_status('on-hold', __('Платеж заблокирован. Ожидается подтверждение.', 'woocommerce'));
                    break;
                default:
                    $order->update_status('failed', __('Платеж не был завершен.', 'woocommerce'));
                    break;
            }
        }
    }

    wp_send_json_success();
    exit;
}

// Добавление ссылки "Настройки" на страницу списка плагинов
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'payze_gateway_settings_link');

function payze_gateway_settings_link($links) {
    $settings_link = '<a href="admin.php?page=wc-settings&tab=checkout&section=payze">Настройки</a>';
    array_unshift($links, $settings_link);
    return $links;
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
