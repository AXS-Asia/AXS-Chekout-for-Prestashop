<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

class Axs_Checkout extends PaymentModule
{
    // Config keys
    const CFG_ENABLED  = 'AXS_ENABLED';
    const CFG_TESTMODE = 'AXS_TESTMODE';

    const CFG_TEST_MERCHANT_LINK = 'AXS_TEST_MERCHANT_LINK';
    const CFG_TEST_CLIENT_KEY    = 'AXS_TEST_CLIENT_KEY';
    const CFG_TEST_SECRET_KEY    = 'AXS_TEST_SECRET_KEY';

    const CFG_LIVE_MERCHANT_LINK = 'AXS_LIVE_MERCHANT_LINK';
    const CFG_LIVE_CLIENT_KEY    = 'AXS_LIVE_CLIENT_KEY';
    const CFG_LIVE_SECRET_KEY    = 'AXS_LIVE_SECRET_KEY';

    public function __construct()
    {
        $this->name = 'axs_checkout';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.0';
        $this->author = 'AXS Pte Ltd';
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('AXS Checkout');
        $this->description = $this->l('Enables seamless and secure payments for credit/debit cards, QR (local and foreign wallets), Apple Pay and more.');
    }

    public function install()
    {
        return parent::install()
            && Configuration::updateValue(self::CFG_ENABLED, '0')
            && Configuration::updateValue(self::CFG_TESTMODE, '1')
            && Configuration::updateValue(self::CFG_TEST_MERCHANT_LINK, '')
            && Configuration::updateValue(self::CFG_TEST_CLIENT_KEY, '')
            && Configuration::updateValue(self::CFG_TEST_SECRET_KEY, '')
            && Configuration::updateValue(self::CFG_LIVE_MERCHANT_LINK, '')
            && Configuration::updateValue(self::CFG_LIVE_CLIENT_KEY, '')
            && Configuration::updateValue(self::CFG_LIVE_SECRET_KEY, '')
            && $this->registerHook('paymentOptions')
            && $this->registerHook('displayPaymentTop');
    }

    public function uninstall()
    {
        return parent::uninstall()
            && Configuration::deleteByName(self::CFG_ENABLED)
            && Configuration::deleteByName(self::CFG_TESTMODE)
            && Configuration::deleteByName(self::CFG_TEST_MERCHANT_LINK)
            && Configuration::deleteByName(self::CFG_TEST_CLIENT_KEY)
            && Configuration::deleteByName(self::CFG_TEST_SECRET_KEY)
            && Configuration::deleteByName(self::CFG_LIVE_MERCHANT_LINK)
            && Configuration::deleteByName(self::CFG_LIVE_CLIENT_KEY)
            && Configuration::deleteByName(self::CFG_LIVE_SECRET_KEY);
    }

    public function getContent()
    {
        $output = '';

        if (Tools::isSubmit('submitAxsCheckoutConfig')) {
            // switches return "1" or "0"
            Configuration::updateValue(self::CFG_ENABLED, (string)(int)Tools::getValue(self::CFG_ENABLED));
            Configuration::updateValue(self::CFG_TESTMODE, (string)(int)Tools::getValue(self::CFG_TESTMODE));

            Configuration::updateValue(self::CFG_TEST_MERCHANT_LINK, trim((string)Tools::getValue(self::CFG_TEST_MERCHANT_LINK)));
            Configuration::updateValue(self::CFG_TEST_CLIENT_KEY, trim((string)Tools::getValue(self::CFG_TEST_CLIENT_KEY)));
            $this->updateSecretConfiguration(self::CFG_TEST_SECRET_KEY);

            Configuration::updateValue(self::CFG_LIVE_MERCHANT_LINK, trim((string)Tools::getValue(self::CFG_LIVE_MERCHANT_LINK)));
            Configuration::updateValue(self::CFG_LIVE_CLIENT_KEY, trim((string)Tools::getValue(self::CFG_LIVE_CLIENT_KEY)));
            $this->updateSecretConfiguration(self::CFG_LIVE_SECRET_KEY);

            $output .= $this->displayConfirmation($this->l('Settings updated.'));
        }

        return $output . $this->renderForm();
    }

    private function renderForm()
    {
        $fields_form = [[
            'form' => [
                'legend' => ['title' => $this->l('AXS Checkout Settings')],
                'input' => [
                    [
                        'type' => 'switch',
                        'label' => $this->l('Enable AXS Checkout'),
                        'name' => self::CFG_ENABLED,
                        'values' => [
                            ['id' => 'enabled_on', 'value' => 1, 'label' => $this->l('Yes')],
                            ['id' => 'enabled_off', 'value' => 0, 'label' => $this->l('No')],
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Test mode'),
                        'name' => self::CFG_TESTMODE,
                        'values' => [
                            ['id' => 'test_on', 'value' => 1, 'label' => $this->l('Yes')],
                            ['id' => 'test_off', 'value' => 0, 'label' => $this->l('No')],
                        ],
                        'desc' => $this->l('Use sandbox credentials when enabled.'),
                    ],

                    ['type' => 'text', 'label' => $this->l('Test Payment Link'), 'name' => self::CFG_TEST_MERCHANT_LINK],
                    ['type' => 'text', 'label' => $this->l('Test Client ID'), 'name' => self::CFG_TEST_CLIENT_KEY],
                    [
                        'type' => 'password',
                        'label' => $this->l('Test Secret'),
                        'name' => self::CFG_TEST_SECRET_KEY,
                        'desc' => $this->l('Leave blank to keep the currently saved secret.'),
                    ],

                    ['type' => 'text', 'label' => $this->l('Live Payment Link'), 'name' => self::CFG_LIVE_MERCHANT_LINK],
                    ['type' => 'text', 'label' => $this->l('Live Client ID'), 'name' => self::CFG_LIVE_CLIENT_KEY],
                    [
                        'type' => 'password',
                        'label' => $this->l('Live Secret'),
                        'name' => self::CFG_LIVE_SECRET_KEY,
                        'desc' => $this->l('Leave blank to keep the currently saved secret.'),
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save'),
                    'name' => 'submitAxsCheckoutConfig',
                ],
            ],
        ]];

        $helper = new HelperForm();
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = $this->context->link->getAdminLink(
            'AdminModules',
            true,
            [],
            ['configure' => $this->name]
        );
        $helper->default_form_language = (int)Configuration::get('PS_LANG_DEFAULT');
        $helper->allow_employee_form_lang = (int)Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG');
        $helper->show_toolbar = false;

        $helper->fields_value = [
            self::CFG_ENABLED => (int)Configuration::get(self::CFG_ENABLED),
            self::CFG_TESTMODE => (int)Configuration::get(self::CFG_TESTMODE),

            self::CFG_TEST_MERCHANT_LINK => (string)Configuration::get(self::CFG_TEST_MERCHANT_LINK),
            self::CFG_TEST_CLIENT_KEY => (string)Configuration::get(self::CFG_TEST_CLIENT_KEY),
            self::CFG_TEST_SECRET_KEY => (string)Configuration::get(self::CFG_TEST_SECRET_KEY),

            self::CFG_LIVE_MERCHANT_LINK => (string)Configuration::get(self::CFG_LIVE_MERCHANT_LINK),
            self::CFG_LIVE_CLIENT_KEY => (string)Configuration::get(self::CFG_LIVE_CLIENT_KEY),
            self::CFG_LIVE_SECRET_KEY => (string)Configuration::get(self::CFG_LIVE_SECRET_KEY),
        ];

        return $helper->generateForm($fields_form);
    }

    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return [];
        }

        if ((int) Configuration::get(self::CFG_ENABLED) !== 1) {
            return [];
        }

        $cart = isset($params['cart']) ? $params['cart'] : $this->context->cart;

        if (!$this->hasValidGatewayConfiguration() || !$this->isCurrencySupported($cart)) {
            return [];
        }

        $option = new PaymentOption();
        $option->setCallToActionText('')
            ->setAction($this->context->link->getModuleLink($this->name, 'payment', [], true));

        // optional logo
        if (file_exists(__DIR__ . '/checkout-logo.svg')) {
            $option->setLogo(Media::getMediaPath(_PS_MODULE_DIR_ . $this->name . '/checkout-logo.svg'));
        }

        return [$option];
    }

    public function hookDisplayPaymentTop($params)
    {
        if (!$this->active) {
            return '';
        }

        if ((int) Configuration::get(self::CFG_ENABLED) !== 1) {
            return '';
        }

        if (!$this->hasValidGatewayConfiguration()) {
            return '';
        }

        $cart = isset($params['cart']) ? $params['cart'] : $this->context->cart;

        if ($this->isCurrencySupported($cart)) {
            return '';
        }

        return sprintf(
            '<article class="alert alert-info" role="alert" data-axs-checkout-currency-notice="1">%s</article>',
            htmlspecialchars($this->l('AXS Checkout is only available for SGD orders.'), ENT_QUOTES, 'UTF-8')
        );
    }

    public function getGatewayConfiguration()
    {
        $isTest = ((int) Configuration::get(self::CFG_TESTMODE) === 1);

        return [
            'is_test' => $isTest,
            'merchant_link' => $isTest
                ? trim((string) Configuration::get(self::CFG_TEST_MERCHANT_LINK))
                : trim((string) Configuration::get(self::CFG_LIVE_MERCHANT_LINK)),
            'client_key' => $isTest
                ? trim((string) Configuration::get(self::CFG_TEST_CLIENT_KEY))
                : trim((string) Configuration::get(self::CFG_LIVE_CLIENT_KEY)),
            'secret_key' => $isTest
                ? trim((string) Configuration::get(self::CFG_TEST_SECRET_KEY))
                : trim((string) Configuration::get(self::CFG_LIVE_SECRET_KEY)),
        ];
    }

    public function hasValidGatewayConfiguration()
    {
        $config = $this->getGatewayConfiguration();

        if ($config['merchant_link'] === '' || $config['client_key'] === '' || $config['secret_key'] === '') {
            return false;
        }

        return (bool) filter_var($config['merchant_link'], FILTER_VALIDATE_URL);
    }

    public function isCurrencySupported($cart = null)
    {
        return $this->getCurrencyIsoCode($cart) === 'SGD';
    }

    public function getCurrencyIsoCode($cart = null)
    {
        if ($cart && Validate::isLoadedObject($cart) && (int) $cart->id_currency > 0) {
            $currency = new Currency((int) $cart->id_currency);

            if (Validate::isLoadedObject($currency)) {
                return Tools::strtoupper((string) $currency->iso_code);
            }
        }

        if ($this->context->currency && Validate::isLoadedObject($this->context->currency)) {
            return Tools::strtoupper((string) $this->context->currency->iso_code);
        }

        return '';
    }

    public function getPendingOrderStateId()
    {
        $configuredStateId = (int) Configuration::get('PS_OS_PREPARATION');

        return $configuredStateId > 0 ? $configuredStateId : (int) Configuration::get('PS_OS_ERROR');
    }

    public function getOrderStateIdForPaymentStatus($status)
    {
        switch ($this->normalizePaymentStatus($status)) {
            case 'success':
                return (int) Configuration::get('PS_OS_PAYMENT');

            case 'failed':
                return (int) Configuration::get('PS_OS_ERROR');

            case 'canceled':
            case 'expired':
                return (int) Configuration::get('PS_OS_CANCELED');

            default:
                return 0;
        }
    }

    public function extractPaymentStatus(array $data)
    {
        foreach (['status', 'paymentStatus', 'result', 'transactionStatus', 'callbackStatus', 'callbackType', 'event', 'eventType', 'type'] as $key) {
            if (!array_key_exists($key, $data)) {
                continue;
            }

            $value = $data[$key];

            if (is_scalar($value) && trim((string) $value) !== '') {
                return (string) $value;
            }
        }

        foreach ($data as $value) {
            if (!is_array($value)) {
                continue;
            }

            $status = $this->extractPaymentStatus($value);

            if ($status !== '') {
                return $status;
            }
        }

        foreach ($data as $key => $value) {
            if (!is_scalar($value)) {
                continue;
            }

            $candidate = trim((string) $value);

            if ($candidate === '') {
                continue;
            }

            if ($this->normalizePaymentStatus($candidate) !== 'pending') {
                return $candidate;
            }

            if (is_string($key) && $this->normalizePaymentStatus($key) !== 'pending') {
                return (string) $key;
            }
        }

        return '';
    }

    public function getNormalizedPaymentStatusFromOrder(Order $order)
    {
        $currentStateId = (int) $order->getCurrentState();

        if ($currentStateId === (int) Configuration::get('PS_OS_PAYMENT')) {
            return 'success';
        }

        if ($currentStateId === (int) Configuration::get('PS_OS_ERROR')) {
            return 'failed';
        }

        if ($currentStateId === (int) Configuration::get('PS_OS_CANCELED')) {
            return 'canceled';
        }

        return 'pending';
    }

    public function normalizePaymentStatus($status)
    {
        $normalizedStatus = Tools::strtolower(trim((string) $status));

        if ($normalizedStatus === '') {
            return 'pending';
        }

        if (preg_match('/success|succeed|paid|complete|approv/', $normalizedStatus)) {
            return 'success';
        }

        if (preg_match('/fail|declin|error|denied/', $normalizedStatus)) {
            return 'failed';
        }

        if (preg_match('/cancel/', $normalizedStatus)) {
            return 'canceled';
        }

        if (preg_match('/expir|timeout|timed[_-]?out/', $normalizedStatus)) {
            return 'expired';
        }

        switch ($normalizedStatus) {
            case 'success':
            case 'successful':
            case 'succeeded':
            case 'paid':
            case 'completed':
            case 'approved':
                return 'success';

            case 'failed':
            case 'failure':
            case 'declined':
            case 'error':
                return 'failed';

            case 'cancelled':
            case 'canceled':
                return 'canceled';

            case 'expired':
            case 'timeout':
            case 'timed_out':
                return 'expired';

            case 'pending':
            case 'processing':
            case 'initiated':
            default:
                return 'pending';
        }
    }

    public function applyPaymentStatusToOrder(Order $order, $status, array $payload = [])
    {
        $normalizedStatus = $this->normalizePaymentStatus($status);
        $targetStateId = $this->getOrderStateIdForPaymentStatus($normalizedStatus);
        $currentStateId = (int) $order->getCurrentState();
        $paidStateId = (int) Configuration::get('PS_OS_PAYMENT');

        $this->addPrivateOrderMessage(
            (int) $order->id,
            $this->buildOrderStatusMessage($normalizedStatus, $status, $payload)
        );

        if ($normalizedStatus === 'pending' || $targetStateId <= 0) {
            return false;
        }

        if ($currentStateId === $targetStateId) {
            PrestaShopLogger::addLog(
                sprintf('AXS Checkout: ignoring duplicate status "%s" for order %d', $normalizedStatus, (int) $order->id),
                1,
                null,
                'Order',
                (int) $order->id,
                true
            );

            return false;
        }

        if ($currentStateId === $paidStateId && $targetStateId !== $paidStateId) {
            PrestaShopLogger::addLog(
                sprintf('AXS Checkout: ignoring downgrade from paid state for order %d', (int) $order->id),
                2,
                null,
                'Order',
                (int) $order->id,
                true
            );

            return false;
        }

        $history = new OrderHistory();
        $history->id_order = (int) $order->id;
        $history->changeIdOrderState($targetStateId, $order, true);
        $history->addWithemail(true);

        return true;
    }

    public function addPrivateOrderMessage($orderId, $message)
    {
        $privateMessage = new Message();
        $privateMessage->id_order = (int) $orderId;
        $privateMessage->message = (string) $message;
        $privateMessage->private = 1;
        $privateMessage->add();
    }

    public function findOrderByMerchantReference($merchantReference)
    {
        if ($merchantReference === null || $merchantReference === '') {
            return null;
        }

        $collection = Order::getByReference((string) $merchantReference);

        if ($collection && $collection->count() > 0) {
            return $collection->getFirst();
        }

        return null;
    }

    public function restoreRetryCartFromOrder(Order $order)
    {
        $oldCart = new Cart((int) $order->id_cart);

        if (!Validate::isLoadedObject($oldCart)) {
            return false;
        }

        $previousCart = $this->context->cart;
        $previousCustomer = $this->context->customer;
        $previousCurrency = $this->context->currency;

        $this->context->cart = $oldCart;
        $this->context->customer = new Customer((int) $oldCart->id_customer);
        $this->context->currency = new Currency((int) $oldCart->id_currency);

        $duplication = $oldCart->duplicate();

        $this->context->cart = $previousCart;
        $this->context->customer = $previousCustomer;
        $this->context->currency = $previousCurrency;

        if (!$duplication || !isset($duplication['cart']) || !Validate::isLoadedObject($duplication['cart'])) {
            return false;
        }

        $this->context->cart = $duplication['cart'];
        $this->context->cookie->id_cart = (int) $duplication['cart']->id;
        CartRule::autoAddToCart($this->context);
        $this->context->cookie->write();

        return true;
    }

    private function buildOrderStatusMessage($normalizedStatus, $rawStatus, array $payload)
    {
        $parts = [
            'AXS Checkout status update',
            'normalized=' . $normalizedStatus,
            'raw=' . ($rawStatus === '' ? 'N/A' : (string) $rawStatus),
        ];

        if (!empty($payload['transactionRef'])) {
            $parts[] = 'transactionRef=' . (string) $payload['transactionRef'];
        }

        if (!empty($payload['amount'])) {
            $parts[] = 'amount=' . (string) $payload['amount'];
        }

        if (!empty($payload['currency'])) {
            $parts[] = 'currency=' . (string) $payload['currency'];
        }

        return implode('; ', $parts);
    }

    private function updateSecretConfiguration($configurationKey)
    {
        $postedValue = Tools::getValue($configurationKey);

        if ($postedValue === false || $postedValue === null) {
            return;
        }

        $postedValue = trim((string) $postedValue);

        if ($postedValue === '') {
            return;
        }

        Configuration::updateValue($configurationKey, $postedValue);
    }
}