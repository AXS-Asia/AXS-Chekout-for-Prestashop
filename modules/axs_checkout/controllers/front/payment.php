<?php

require_once _PS_MODULE_DIR_ . 'axs_checkout/payment_link_generator.php';

class Axs_CheckoutPaymentModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        /** @var Axs_Checkout $module */
        $module = $this->module;

        if (!$module || !$module->active) {
            $this->redirectToCheckout();
        }

        // Must have a cart
        $cart = $this->context->cart;
        if (!$cart || (int) $cart->id <= 0) {
            $this->redirectToCheckout();
        }

        // Module enabled?
        if ((int) Configuration::get(Axs_Checkout::CFG_ENABLED) !== 1) {
            $this->redirectToCheckout();
        }

        // Customer
        $customer = new Customer((int) $cart->id_customer);
        if (!Validate::isLoadedObject($customer)) {
            $this->redirectToCheckout();
        }

        if (!$module->isCurrencySupported($cart)) {
            $this->redirectToCheckoutWithError('AXS Checkout only supports SGD currency.');
        }

        $config = $module->getGatewayConfiguration();
        if (!$module->hasValidGatewayConfiguration()) {
            PrestaShopLogger::addLog(
                sprintf('AXS Checkout: invalid gateway configuration for cart %d', (int) $cart->id),
                3,
                null,
                'Cart',
                (int) $cart->id,
                true
            );
            $this->redirectToCheckoutWithError('Unable to initialize AXS Checkout. Please try again later or use another payment method.');
        }

        // Amount + currency
        $total = (float) $cart->getOrderTotal(true, Cart::BOTH);
        $amountCents = (int) round($total * 100);

        $merchantLink = $config['merchant_link'];
        $clientKey = $config['client_key'];
        $secretKey = $config['secret_key'];

        $currency = $module->getCurrencyIsoCode($cart);
        $currencyId = $this->context->currency ? (int) $this->context->currency->id : (int) Configuration::get('PS_CURRENCY_DEFAULT');

        // 1) Create order FIRST (pending/waiting state)
        $pendingStateId = $module->getPendingOrderStateId();

        try {
            $module->validateOrder(
                (int) $cart->id,
                $pendingStateId,
                $total,
                $module->displayName,
                null,
                [],
                $currencyId,
                false,
                $customer->secure_key
            );
        } catch (Exception $e) {
            PrestaShopLogger::addLog(
                sprintf('AXS Checkout: validateOrder failed for cart %d - %s', (int) $cart->id, $e->getMessage()),
                3,
                null,
                'Cart',
                (int) $cart->id,
                true
            );
            $this->redirectToCheckoutWithError('Unable to initialize AXS Checkout. Please try again later or use another payment method.');
        }

        $orderId = (int) $module->currentOrder;
        $order = new Order($orderId);

        if (!Validate::isLoadedObject($order)) {
            PrestaShopLogger::addLog('AXS Checkout: order not created properly after validateOrder', 3);
            $this->redirectToCheckoutWithError('Unable to initialize AXS Checkout. Please try again later or use another payment method.');
        }

        // 2) URLs
        $successUrl = $this->context->link->getPageLink(
            'order-confirmation',
            true,
            null,
            [
                'id_cart'   => (int) $cart->id,
                'id_module' => (int) $module->id,
                'id_order'  => (int) $order->id,
                'key'       => $customer->secure_key,
            ]
        );

        $failUrl = $this->context->link->getModuleLink(
            'axs_checkout',
            'fail',
            [
                'id_order' => (int) $order->id,
                'key'      => $customer->secure_key,
            ],
            true
        );

        $cancelUrl = $this->context->link->getModuleLink(
            'axs_checkout',
            'cancel',
            [
                'id_order' => (int) $order->id,
                'key'      => $customer->secure_key,
            ],
            true
        );

        $webhookUrl = $this->context->link->getModuleLink(
            'axs_checkout',
            'webhook',
            [],
            true
        );

        // 3) merchantRef (recommended: order reference)
        $merchantRef = (string) $order->reference;

        $params = [
            'clientId'    => $clientKey,
            'amount'      => $amountCents,
            'currency'    => $currency,
            'merchantRef' => $merchantRef,
            'successUrl'  => $successUrl,
            'failUrl'     => $failUrl,
            'webhookUrl'  => $webhookUrl,
            'cancelUrl'   => $cancelUrl,
            'expiry'      => 300,
        ];

        PrestaShopLogger::addLog(
            'AXS FORCE LOG: ' . print_r($params, true),
            1,
            null,
            'Axs_Checkout',
            0,
            true
        );

        try {
            $generator = new PaymentLinkGenerator();
            $paymentLink = $generator->generatePaymentLink($merchantLink, $clientKey, $secretKey, $params);

            if (!is_string($paymentLink) || $paymentLink === '') {
                throw new Exception('Empty payment link generated');
            }

            Tools::redirect($paymentLink);
        } catch (Exception $e) {
            PrestaShopLogger::addLog(
                sprintf(
                    'AXS Checkout: payment initialization failed for cart %d / order %d. merchantLink=%s error=%s',
                    (int) $cart->id,
                    (int) $order->id,
                    $merchantLink,
                    $e->getMessage()
                ),
                3,
                null,
                'Order',
                (int) $order->id,
                true
            );

            $module->addPrivateOrderMessage(
                (int) $order->id,
                'AXS Checkout initialization failed before redirect. The customer was returned to checkout.'
            );

            $this->redirectToCheckoutWithError('Unable to initialize AXS Checkout. Please try again later or use another payment method.');
        }
    }

    private function redirectToCheckout()
    {
        Tools::redirect($this->context->link->getPageLink('order', true, null, 'step=1'));
    }

    private function redirectToCheckoutWithError($message)
    {
        $this->errors[] = $this->module->l($message, 'payment');

        if (method_exists($this, 'redirectWithNotifications')) {
            $this->redirectWithNotifications($this->context->link->getPageLink('order', true, null, 'step=1'));
        }

        $this->redirectToCheckout();
    }
}