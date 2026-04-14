<?php

namespace Richpanel\Analytics\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Richpanel\Analytics\Helper\Data;
use Magento\Sales\Model\Order as MagentoOrder;
use Exception;

class Order implements ObserverInterface
{
    private Data $helper;

    /**
     * @param Data $helper
     */
    public function __construct(
        Data $helper
    ) {
        $this->helper = $helper;
    }

    /**
     * Trigger on save Order
     *
     * @param Observer $observer
     * @return void
     */
   public function execute(Observer $observer): void
    {
        try {
            $this->helper->log('Order Event');
            
            /** @var MagentoOrder|null $order */
            $order = $observer->getEvent()->getOrder();
            
            if (!$order instanceof MagentoOrder) {
                return;
            }
            
            $storeId = $order->getStoreId();

            if (!$this->helper->isEnabled($storeId)) {
                $this->helper->log('Order - Store disabled');
                return;
            }

            $this->helper->callBatchApi($storeId, [$order]);

            // If order is made from the FrontEnd
            $remoteIp = $order->getRemoteIp();
            $customerEmail = $order->getCustomerEmail();
            
            if ($remoteIp && !empty(trim((string)$customerEmail))) {
                $this->helper->log('Order - Identify');
                
                $billingAddress = $order->getBillingAddress();
                if (!$billingAddress) {
                    return;
                }

                $data = [
                    'uid'      => $customerEmail,
                    'email'    => $customerEmail,
                    'name'     => $billingAddress->getName(),
                    'firstName' => $billingAddress->getFirstname(),
                    'lastName'  => $billingAddress->getLastname()
                ];
                
                $this->helper->addSessionEvent('identify', 'identify', false, $data);
            }
        } catch (Exception $e) {
            $this->helper->logError($e);
        }
    }
}
