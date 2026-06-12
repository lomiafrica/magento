<?php

use Magento\Framework\App\Bootstrap;

require '/var/www/html/app/bootstrap.php';

$bootstrap = Bootstrap::create(BP, $_SERVER);
$om = $bootstrap->getObjectManager();
$scope = $om->get(\Magento\Framework\App\Config\ScopeConfigInterface::class);
$encryptor = $om->get(\Magento\Framework\Encryption\EncryptorInterface::class);

$raw = (string) $scope->getValue('payment/lomi/test_webhook_secret');
$decrypted = $raw !== '' ? (string) $encryptor->decrypt($raw) : '';

echo 'raw_set=' . ($raw !== '' ? 'yes' : 'no') . PHP_EOL;
echo 'raw_encrypted=' . (preg_match('/^\d+:\d+:/', $raw) ? 'yes' : 'no') . PHP_EOL;
echo 'raw_length=' . strlen($raw) . PHP_EOL;
echo 'decrypted_set=' . ($decrypted !== '' ? 'yes' : 'no') . PHP_EOL;
echo 'raw_whsec=' . (str_starts_with($raw, 'whsec_') ? 'yes' : 'no') . PHP_EOL;
echo 'decrypted_whsec=' . (str_starts_with($decrypted, 'whsec_') ? 'yes' : 'no') . PHP_EOL;
echo 'decrypted_length=' . strlen($decrypted) . PHP_EOL;
$method = $om->get(\Magento\Payment\Helper\Data::class)->getMethodInstance(\Lomi\Payments\Model\Payment\Lomi::CODE);
$apiClient = $om->get(\Lomi\Payments\Gateway\LomiApiClient::class);
$reflection = new ReflectionClass($apiClient);
$webhookSecret = $reflection->getProperty('webhookSecret');
$webhookSecret->setAccessible(true);
$pluginSecret = (string) $webhookSecret->getValue($apiClient);
echo 'plugin_whsec=' . (str_starts_with($pluginSecret, 'whsec_') ? 'yes' : 'no') . PHP_EOL;
echo 'plugin_secret_length=' . strlen($pluginSecret) . PHP_EOL;
