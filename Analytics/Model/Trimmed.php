<?php

namespace Richpanel\Analytics\Model;

/**
 * Trimmed configuration value model
 */
class Trimmed extends \Magento\Framework\App\Config\Value
{
    /**
     * Trim whitespace from configuration value before saving
     *
     * @return $this
     */
    public function beforeSave()
    {
        $value = $this->getValue();
        if ($value !== null) {
            $value = trim($value);
            $this->setValue($value);
        }

        return parent::beforeSave();
    }
}
