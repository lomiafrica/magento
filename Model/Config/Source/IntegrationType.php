<?php

namespace Lomi\Payments\Model\Config\Source;

/**
 * Option source for integration types (legacy; hosted redirect only).
 */
class IntegrationType implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => 'inline', 'label' => __('Inline - (Popup)')],
            ['value' => 'standard', 'label' => __('Standard - (Redirect)')],
        ];
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return ['inline' => __('Inline - (Popup)'), 'standard' => __('Standard - (Redirect)')];
    }
}
