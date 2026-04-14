<?php

namespace Richpanel\Analytics\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Richpanel\Analytics\Helper\Data;
use Magento\Customer\Model\Customer;
use Exception;

class CustomerLogin implements ObserverInterface
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
     * Track customer login and trigger "identify" to Richpanel
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        try {
            $this->helper->log('Customer Login Event');
            
            /** @var Customer|null $customer */
            $customer = $observer->getData('customer');
            
            if (!$customer instanceof Customer) {
                return;
            }

            $data = [
                'uid'       => $customer->getEmail(),
                'email'     => $customer->getEmail(),
                'name'      => $customer->getName(),
                'firstName' => $customer->getFirstname(),
                'lastName'  => $customer->getLastname(),
            ];

            $this->helper->addSessionEvent(
                'identify',
                'identify',
                false,
                $data,
                $customer->getId()
            );
        } catch (Exception $e) {
            $this->helper->logError($e);
        }
    }
}
