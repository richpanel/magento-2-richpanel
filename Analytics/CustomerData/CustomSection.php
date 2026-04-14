<?php

namespace Richpanel\Analytics\CustomerData;

use Magento\Customer\CustomerData\SectionSourceInterface;
use Richpanel\Analytics\Block\Analytics;

/**
 * Catalog Product Compare Widget
 */
class CustomSection implements SectionSourceInterface
{
    /**
     * @var Analytics
     */
    private Analytics $analytics;

    /**
     * @param Analytics $analytics
     */
    public function __construct(
        Analytics $analytics
    ) {
        $this->analytics = $analytics;
    }

    /**
     * Get section data for customer
     *
     * @return array
     */
    public function getSectionData(): array
    {
        return $this->analytics->getRichpanelUserData();
    }
}