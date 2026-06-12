<?php

use Magento\Framework\App\Bootstrap;

require '/var/www/html/app/bootstrap.php';

$bootstrap = Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();

$state = $objectManager->get(\Magento\Framework\App\State::class);
$state->setAreaCode('frontend');

$branding = $objectManager->get(\Lomi\Payments\Model\CheckoutBranding::class);

echo 'pay_with: ' . $branding->getPayWithImageUrl() . PHP_EOL;
echo 'icons: ' . json_encode($branding->getPaymentIconUrls(), JSON_PRETTY_PRINT) . PHP_EOL;
