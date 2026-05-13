<?php

require_once _PS_MODULE_DIR_ . 'axs_checkout/payment_link_generator.php';

class Axs_CheckoutCancelModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        /** @var Axs_Checkout $module */
        $module = $this->module;

        PrestaShopLogger::addLog(
            'AXS CANCEL LOG: ' . print_r($_REQUEST, true),
            1,
            null,
            'Axs_Checkout',
            0,
            true
        );

        $order = $this->loadOrder($module);
        $status = $module->extractPaymentStatus($_REQUEST);
        $normalizedStatus = $module->normalizePaymentStatus($status);

        if ($order && Validate::isLoadedObject($order) && $status !== '') {
            $module->applyPaymentStatusToOrder($order, $status, [
                'transactionRef' => (string) Tools::getValue('transactionRef'),
                'amount' => (string) Tools::getValue('amount'),
                'currency' => (string) Tools::getValue('currency'),
            ]);
        }

        if ($order && Validate::isLoadedObject($order) && $normalizedStatus === 'pending') {
            $normalizedStatus = $this->waitForResolvedFailureStatus($module, $order);

            if ($normalizedStatus === 'pending') {
                $module->applyPaymentStatusToOrder($order, 'canceled', [
                    'transactionRef' => (string) Tools::getValue('transactionRef'),
                    'amount' => (string) Tools::getValue('amount'),
                    'currency' => (string) Tools::getValue('currency'),
                ]);
                $normalizedStatus = 'canceled';
            }
        }

        if ($order && Validate::isLoadedObject($order) && !$module->restoreRetryCartFromOrder($order)) {
            PrestaShopLogger::addLog(
                sprintf('AXS Checkout: failed to restore retry cart from order %d after cancel redirect', (int) $order->id),
                3,
                null,
                'Order',
                (int) $order->id,
                true
            );
        }

        $this->errors[] = $module->l('Payment Cancelled. You have not been charged.', 'cancel');

        if (method_exists($this, 'redirectWithNotifications')) {
            return $this->redirectWithNotifications($this->context->link->getPageLink('order', true, null, 'step=1'));
        }

        Tools::redirect($this->context->link->getPageLink('order', true, null, 'step=1'));
    }

    private function loadOrder(Axs_Checkout $module)
    {
        $orderId = (int) Tools::getValue('id_order');
        if ($orderId > 0) {
            $order = new Order($orderId);
            if (Validate::isLoadedObject($order)) {
                return $order;
            }
        }

        $merchantRef = Tools::getValue('merchantRef');
        if ($merchantRef === false || $merchantRef === null || $merchantRef === '') {
            $merchantRef = Tools::getValue('merchantReference');
        }

        return $module->findOrderByMerchantReference($merchantRef);
    }

    private function waitForResolvedFailureStatus(Axs_Checkout $module, Order $order)
    {
        for ($attempt = 0; $attempt < 6; ++$attempt) {
            $refreshedOrder = new Order((int) $order->id);

            if (Validate::isLoadedObject($refreshedOrder)) {
                $normalizedStatus = $module->getNormalizedPaymentStatusFromOrder($refreshedOrder);

                if ($normalizedStatus !== 'pending') {
                    return $normalizedStatus;
                }
            }

            usleep(500000);
        }

        return 'pending';
    }
}