<?php

use Magento\Framework\App\Bootstrap;

require '/var/www/html/app/bootstrap.php';

$incrementId = $argv[1] ?? '000000006';

$bootstrap = Bootstrap::create(BP, $_SERVER);
$om = $bootstrap->getObjectManager();
$om->get(\Magento\Framework\App\State::class)->setAreaCode('frontend');

$orderFactory = $om->get(\Magento\Sales\Model\OrderFactory::class);
$order = $orderFactory->create()->loadByIncrementId($incrementId);
if (!$order->getId()) {
    echo "order_not_found\n";
    exit(1);
}

$sessionId = (string) $order->getPayment()->getAdditionalInformation('lomi_checkout_session_id');
echo 'session_id=' . $sessionId . PHP_EOL;

try {
    $client = $om->get(\Lomi\Payments\Gateway\LomiApiClient::class);
    $session = $client->fetchCheckoutSession($sessionId);
    echo 'api_status=' . (string) ($session->status ?? 'unknown') . PHP_EOL;
    echo 'api_amount=' . (string) ($session->amount ?? '') . PHP_EOL;
    echo 'api_currency=' . (string) ($session->currency_code ?? '') . PHP_EOL;
    echo 'api_updated_at=' . (string) ($session->updated_at ?? '') . PHP_EOL;
    echo 'api_success_url=' . (string) ($session->success_url ?? '') . PHP_EOL;
    echo 'order_amount=' . $client->getOrderAmountMinorUnits($order) . PHP_EOL;

    if (($argv[2] ?? '') === '--apply') {
        $verifier = $om->get(\Lomi\Payments\Model\CheckoutSessionVerifier::class);
        $ok = $verifier->verifyAndDispatch($order, $session);
        $order = $orderFactory->create()->loadByIncrementId($incrementId);
        echo 'verify_ok=' . ($ok ? 'yes' : 'no') . PHP_EOL;
        echo 'new_state=' . $order->getState() . PHP_EOL;
        echo 'new_status=' . $order->getStatus() . PHP_EOL;
    }
} catch (\Throwable $e) {
    echo 'error=' . $e->getMessage() . PHP_EOL;
    exit(1);
}
