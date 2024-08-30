<?php
class WC_Gateway_Payze extends WC_Payment_Gateway {
    private $api_key;
    private $secret_key;
    private $success_status;

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
?>
