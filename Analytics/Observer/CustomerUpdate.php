<?php

namespace Richpanel\Analytics\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Richpanel\Analytics\Helper\Data;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Exception;

class CustomerUpdate implements ObserverInterface
{
    private Data $helper;
    private CustomerRepositoryInterface $customerRepository;

    /**
     * @param Data $helper
     * @param CustomerRepositoryInterface $customerRepository
     */
    public function __construct(
        Data $helper,
        CustomerRepositoryInterface $customerRepository
    ) {
        $this->helper = $helper;
        $this->customerRepository = $customerRepository;
    }

    /**
     * Track customer update profile information
     * and trigger "identify" to Richpanel
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        try {
            $this->helper->log('Customer Update Event');
            $email = $observer->getEvent()->getEmail();
            
            if (empty($email)) {
                return;
            }

            try {
                /** @var CustomerInterface $customer */
                $customer = $this->customerRepository->get($email);
                
                if (!$customer) {
                    return;
                }

                $data = [
                    'uid'   => $customer->getEmail(),
                    'email' => $customer->getEmail()
                ];
                
                if (method_exists($customer, 'getName')) {
                    $data['name'] = $customer->getName();
                }
                if (method_exists($customer, 'getFirstname')) {
                    $data['firstName'] = $customer->getFirstname();
                }
                if (method_exists($customer, 'getLastname')) {
                    $data['lastName'] = $customer->getLastname();
                }

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
        } catch (Exception $e) {
            $this->helper->logError($e);
        }
    }
}
