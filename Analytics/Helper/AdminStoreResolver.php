<?php

namespace Richpanel\Analytics\Helper;

class AdminStoreResolver extends \Magento\Framework\App\Helper\AbstractHelper
{
    /**
     * @var \Magento\Framework\App\Request\Http
     */
    protected $request;

    public function __construct(
        \Magento\Framework\App\Request\Http $request
    ) {
        $this->request = $request;
    }

    /**
     * Get storeId for the current admin request context
     *
     * @return int
     */
    public function getAdminStoreId()
    {
        return (int) $this->request->getParam('store', 0);
    }
}
