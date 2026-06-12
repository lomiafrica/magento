<?php

namespace Lomi\Payments\Block\System\Config\Form\Field;

use Magento\Framework\View\Helper\SecureHtmlRenderer;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Store\Model\StoreManagerInterface;

class Webhook extends \Magento\Config\Block\System\Config\Form\Field
{
    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        StoreManagerInterface $storeManager,
        array $data = [],
        ?SecureHtmlRenderer $secureRenderer = null
    ) {
        $this->storeManager = $storeManager;
        parent::__construct($context, $data, $secureRenderer);
    }

    /**
     * @param AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        $store = $this->storeManager->getStore();
        $webhookUrl = $store->getBaseUrl(
            \Magento\Framework\UrlInterface::URL_TYPE_LINK,
            true
        ) . 'lomi/payment/webhook';
        $dashboardUrl = 'https://dashboard.lomi.africa';

        $html = '<div class="lomi-webhook-hint">';
        $html .= '<p><strong>' . __('Webhook URL for your store:') . '</strong></p>';
        $html .= '<p><code>' . $this->escapeHtml($webhookUrl) . '</code></p>';
        $html .= '<p>' . __(
            'Create a webhook in the lomi. dashboard (Developers → Webhooks) with this URL. Subscribe at least to %1 and %2.',
            '<code>PAYMENT_SUCCEEDED</code>',
            '<code>REFUND_COMPLETED</code>'
        ) . '</p>';
        $html .= '<p>' . __(
            'Open the dashboard: %1',
            '<a href="' . $this->escapeUrl($dashboardUrl) . '" target="_blank" rel="noopener">' . $this->escapeHtml($dashboardUrl) . '</a>'
        ) . '</p>';
        $html .= '</div>';

        return $html;
    }
}
