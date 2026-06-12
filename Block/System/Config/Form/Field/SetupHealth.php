<?php

namespace Lomi\Payments\Block\System\Config\Form\Field;

use Lomi\Payments\Gateway\Exception\ApiException;
use Lomi\Payments\Gateway\LomiApiClient;
use Lomi\Payments\Model\Payment\Lomi as LomiMethod;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Store\Model\StoreManagerInterface;

class SetupHealth extends \Magento\Config\Block\System\Config\Form\Field
{
    /**
     * @var PaymentHelper
     */
    private $paymentHelper;

    /**
     * @var LomiApiClient
     */
    private $lomiApiClient;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        PaymentHelper $paymentHelper,
        LomiApiClient $lomiApiClient,
        StoreManagerInterface $storeManager,
        array $data = [],
        ?SecureHtmlRenderer $secureRenderer = null
    ) {
        $this->paymentHelper = $paymentHelper;
        $this->lomiApiClient = $lomiApiClient;
        $this->storeManager = $storeManager;
        parent::__construct($context, $data, $secureRenderer);
    }

    /**
     * @param AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        $method = $this->paymentHelper->getMethodInstance(LomiMethod::CODE);
        $testMode = (bool) $method->getConfigData('test_mode');
        $secretConfigured = $testMode
            ? (string) $method->getConfigData('test_secret_key') !== ''
            : (string) $method->getConfigData('live_secret_key') !== '';
        $webhookConfigured = $testMode
            ? (string) $method->getConfigData('test_webhook_secret') !== ''
            : (string) $method->getConfigData('live_webhook_secret') !== '';

        $store = $this->storeManager->getStore();
        $isSecure = $store->isCurrentlySecure();

        $checks = [
            [
                'label' => __('API secret key'),
                'status' => $secretConfigured ? 'ok' : 'error',
                'message' => $secretConfigured
                    ? __('Configured')
                    : __('Missing — add your test or live secret key below.'),
            ],
            [
                'label' => __('Webhook signing secret'),
                'status' => $webhookConfigured ? 'ok' : 'warning',
                'message' => $webhookConfigured
                    ? __('Configured')
                    : __('Recommended — required to verify PAYMENT_SUCCEEDED and REFUND_COMPLETED webhooks.'),
            ],
            [
                'label' => __('HTTPS'),
                'status' => $isSecure ? 'ok' : 'warning',
                'message' => $isSecure
                    ? __('Enabled')
                    : __('Not detected — use HTTPS in production.'),
            ],
            [
                'label' => __('Test mode'),
                'status' => $testMode ? 'warning' : 'ok',
                'message' => $testMode ? __('Sandbox API active') : __('Live API active'),
            ],
        ];

        if ($secretConfigured) {
            try {
                $this->lomiApiClient->testConnection();
                $checks[] = [
                    'label' => __('API connection'),
                    'status' => 'ok',
                    'message' => __('GET /me succeeded'),
                ];
            } catch (ApiException $exception) {
                $checks[] = [
                    'label' => __('API connection'),
                    'status' => 'error',
                    'message' => $exception->getMessage(),
                ];
            }
        }

        $html = '<div class="lomi-setup-health">';
        $html .= '<table class="admin__table-secondary" style="max-width:720px;margin-bottom:1.5em;">';
        $html .= '<thead><tr><th>' . __('Check') . '</th><th>' . __('Status') . '</th><th>' . __('Details') . '</th></tr></thead><tbody>';

        foreach ($checks as $check) {
            $statusLabel = __('OK');
            if ($check['status'] === 'error') {
                $statusLabel = __('Action required');
            } elseif ($check['status'] === 'warning') {
                $statusLabel = __('Warning');
            }

            $html .= '<tr>';
            $html .= '<td>' . $this->escapeHtml((string) $check['label']) . '</td>';
            $html .= '<td>' . $this->escapeHtml((string) $statusLabel) . '</td>';
            $html .= '<td>' . $this->escapeHtml((string) $check['message']) . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table></div>';

        return $html;
    }
}
