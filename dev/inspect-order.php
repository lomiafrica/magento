<?php

use Magento\Framework\App\Bootstrap;

require '/var/www/html/app/bootstrap.php';

$incrementId = $argv[1] ?? '000000006';

$bootstrap = Bootstrap::create(BP, $_SERVER);
$om = $bootstrap->getObjectManager();
$om->get(\Magento\Framework\App\State::class)->setAreaCode('adminhtml');

$order = $om->get(\Magento\Sales\Model\OrderFactory::class)->create()->loadByIncrementId($incrementId);
if (!$order->getId()) {
    echo "order_not_found\n";
    exit(1);
}

echo 'increment_id=' . $order->getIncrementId() . PHP_EOL;
echo 'state=' . $order->getState() . PHP_EOL;
echo 'status=' . $order->getStatus() . PHP_EOL;

$payment = $order->getPayment();
echo 'session_id=' . (string) $payment->getAdditionalInformation('lomi_checkout_session_id') . PHP_EOL;
echo 'amount=' . $order->getGrandTotal() . ' ' . $order->getOrderCurrencyCode() . PHP_EOL;

echo "history:\n";
foreach ($order->getStatusHistoryCollection() as $history) {
    $comment = trim((string) $history->getComment());
    echo $history->getCreatedAt() . ' | ' . $history->getStatus() . ' | ' . $comment . PHP_EOL;
}
