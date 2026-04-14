<?php

namespace Richpanel\Analytics\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Richpanel\Analytics\Helper\Data;
use Magento\Quote\Model\Quote\Item as QuoteItem;
use Magento\Catalog\Model\Product;
use Exception;

class RemoveFromCart implements ObserverInterface
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
     * Track remove quote item
     * and send to Richpanel
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        try {
            /** @var QuoteItem|null $item */
            $item = $observer->getEvent()->getQuoteItem();
            
            if (!$item instanceof QuoteItem) {
                return;
            }

            /** @var Product|null $product */
            $product = $item->getProduct();
            
            if (!$product instanceof Product) {
                return;
            }

            $this->helper->addSessionEvent('track', 'remove_from_cart', [
                'id' => $product->getId()
            ]);
        } catch (Exception $e) {
            $this->helper->logError($e);
        }
    }
}
