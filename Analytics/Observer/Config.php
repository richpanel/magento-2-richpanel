<?php

namespace Richpanel\Analytics\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Message\ManagerInterface;
use Richpanel\Analytics\Helper\Data;
use Richpanel\Analytics\Helper\AdminStoreResolver;

class Config implements ObserverInterface
{
    private Data $helper;
    private ManagerInterface $messageManager;
    private AdminStoreResolver $resolver;

    public function __construct(
        Data $helper,
        ManagerInterface $messageManager,
        AdminStoreResolver $resolver
    ) {
        $this->messageManager = $messageManager;
        $this->resolver = $resolver;
        $this->helper = $helper;
    }

    public function execute(Observer $observer): void
    {
        // $storeId = $this->resolver->getAdminStoreId();
        // if (!$this->helper->createActivity($storeId, 'integrated')) {
        //     $this->messageManager->addError('The API Token and/or API Secret you have entered are invalid. You can find the correct ones in Settings -> Installation in your Richpanel account.');
        // }
    }
}
