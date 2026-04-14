<?php

namespace Richpanel\Analytics\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Richpanel\Analytics\Helper\Data;
use Magento\Sales\Model\Order\Shipment as MagentoShipment;
use Exception;

class Shipment implements ObserverInterface
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
     * Trigger on save Shipment
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        try {
            $this->helper->log('Shipment Event');
            
            /** @var MagentoShipment|null $shipment */
            $shipment = $observer->getEvent()->getShipment();
            
            if (!$shipment instanceof MagentoShipment) {
                return;
            }

            $storeId = $shipment->getStoreId();

            if (!$this->helper->isEnabled($storeId)) {
                $this->helper->log('Shipment - Store disabled');
                return;
            }

            // Process shipment items
            $itemsCollection = $shipment->getItemsCollection();
            if ($itemsCollection) {
                foreach ($itemsCollection as $item) {
                    $orderItem = $item->getOrderItem();
                    if ($orderItem) {
                        $id = $orderItem->getProductId();
                        // ID is collected but not used in the original code
                    }
                }
            }

            $this->helper->callBatchApiForShipment($storeId, [$shipment]);
        } catch (Exception $e) {
            $this->helper->logError($e);
        }
    }
}