<?php

require_once _PS_MODULE_DIR_ . 'axs_checkout/payment_link_generator.php';

class Axs_CheckoutWebhookModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        /** @var Axs_Checkout $module */
        $module = $this->module;

        // 0) Capture request info (same idea as your WooCommerce snippet)
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $requestUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'unknown') . ($_SERVER['REQUEST_URI'] ?? '');

        $rawInput = file_get_contents('php://input');

        $webhookData = [
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'N/A',
            'request_url'    => $requestUrl,
            'content_type'   => $_SERVER['CONTENT_TYPE'] ?? 'N/A',
            'get_data'       => $_GET ?? [],
            'post_data'      => $_POST ?? [],
            'raw_input'      => $rawInput,
        ];

        PrestaShopLogger::addLog(
            'AXS WEBHOOK REQUEST: ' . print_r($webhookData, true),
            1,
            null,
            'Axs_Checkout',
            0,
            true
        );

        // 1) Load secret key from PrestaShop config (adjust keys to your config names)
        $config = $module->getGatewayConfiguration();
        $secretKey = $config['secret_key'];

        if ($secretKey === '') {
            http_response_code(500);
            exit('Missing secret key');
        }

        // 2) Extract JWE token
        $generator = new PaymentLinkGenerator();

        $jweToken = $this->extractJweToken($generator, $requestUrl, $rawInput);

        if (!$jweToken) {
            http_response_code(400);
            exit('Could not extract JWE token');
        }

        // 3) Decrypt JWE
        $decrypted = $generator->decryptJWE($jweToken, $secretKey);
        PrestaShopLogger::addLog(
            'AXS WEBHOOK DECRYPTED: ' . print_r($decrypted, true),
            1,
            null,
            'Axs_Checkout',
            0,
            true
        );

        if (!is_array($decrypted) || empty($decrypted['success'])) {
            http_response_code(400);
            exit('Decryption failed');
        }

        $payload = $decrypted['payload'] ?? [];
        $merchantRef = $payload['merchantRef'] ?? ($payload['merchantReference'] ?? null);

        if (!$merchantRef) {
            http_response_code(400);
            exit('Invalid webhook data: merchantRef missing');
        }

        $order = $module->findOrderByMerchantReference($merchantRef);

        if (!$order || !Validate::isLoadedObject($order)) {
            http_response_code(404);
            exit('Order not found');
        }

        // 5) Update order status based on payload status
        $status = $module->extractPaymentStatus($payload);
        $module->applyPaymentStatusToOrder($order, $status, $payload);

        // 6) Respond OK
        http_response_code(200);
        header('Content-Type: text/plain');
        exit('Webhook processed successfully');
    }

    private function extractJweToken(PaymentLinkGenerator $generator, $requestUrl, $rawInput)
    {
        $jweToken = $generator->extractJWEFromPaymentLink($requestUrl);

        if ($jweToken) {
            return $jweToken;
        }

        $jweToken = Tools::getValue('jwe');
        if ($jweToken) {
            return $jweToken;
        }

        $jweToken = Tools::getValue('token');
        if ($jweToken) {
            return $jweToken;
        }

        $jweToken = Tools::getValue('data');
        if ($jweToken) {
            return $jweToken;
        }

        $decodedJson = json_decode((string) $rawInput, true);
        if (is_array($decodedJson)) {
            foreach (['jwe', 'token', 'data'] as $key) {
                if (!empty($decodedJson[$key])) {
                    return $decodedJson[$key];
                }
            }
        }

        return null;
    }
}