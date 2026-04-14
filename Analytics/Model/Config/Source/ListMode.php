<?php

namespace Richpanel\Analytics\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;
use Magento\Framework\Phrase;

class ListMode implements ArrayInterface
{
    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => 'resume', 'label' => __('Resume Last Sync')],
            ['value' => '-1 Months', 'label' => __('Last 30 days')],
            ['value' => '-2 Months', 'label' => __('Last 60 days')],
            ['value' => '-3 Months', 'label' => __('Last 3 months')],
            ['value' => '-6 Months', 'label' => __('Last 6 months')],
            ['value' => '-12 Months', 'label' => __('Last 12 months')]
        ];
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        $optionArray = $this->toOptionArray();
        $options = [];
        foreach ($optionArray as $option) {
            if (isset($option['value']) && isset($option['label'])) {
                $options[$option['value']] = $option['label'];
            }
        }
        return $options;
    }
}