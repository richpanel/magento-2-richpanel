<?php

namespace Richpanel\Analytics\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;

class AdminStoreResolver extends AbstractHelper
{
    public function __construct(Context $context)
    {
        parent::__construct($context);
    }

    /**
     * Get storeId for the current admin request context
     *
     * @return int
     */
    public function getAdminStoreId()
    {
        return (int) $this->_getRequest()->getParam('store', 0);
    }
}
