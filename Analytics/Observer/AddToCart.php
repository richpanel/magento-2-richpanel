<?php

namespace Richpanel\Analytics\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Richpanel\Analytics\Helper\Data;
use Magento\Catalog\Model\ProductRepository;
use Magento\Framework\App\RequestInterface;
use Magento\Catalog\Model\Product;
use Exception;

class AddToCart implements ObserverInterface
{
    private Data $helper;
    private ProductRepository $productRepository;
    private RequestInterface $request;

    /**
     * @param Data $helper
     * @param ProductRepository $productRepository
     * @param RequestInterface $request
     */
    public function __construct(
        Data $helper,
        ProductRepository $productRepository,
        RequestInterface $request
    ) {
        $this->helper = $helper;
        $this->productRepository = $productRepository;
        $this->request = $request;
    }

    /**
     * Track added products to cart
     * and send to Richpanel
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        try {
            $quoteItem = $observer->getEvent()->getQuoteItem();
            if (!$quoteItem) {
                return;
            }

            $quantity = $quoteItem->getQty();
            $mainProduct = $observer->getEvent()->getProduct();
            if (!$mainProduct) {
                return;
            }

            if ($mainProduct->getTypeId() === 'grouped') {
                $options = $this->request->getParam('super_group');
                if (is_array($options)) {
                    foreach ($options as $productId => $qty) {
                        if ($qty) {
                            try {
                                $product = $this->productRepository->getById((int)$productId);
                                $this->addToCart($product, (int)$qty);
                            } catch (Exception $e) {
                                $this->helper->logError($e);
                            }
                        }
                    }
                }
            } else {
                $this->addToCart($mainProduct, (float)$quantity);
            }
        } catch (Exception $e) {
            $this->helper->logError($e);
        }
    }

    /**
     * Track product to Richpanel
     *
     * @param Product $product
     * @param float|int $quantity
     * @return void
     */
    private function addToCart(Product $product, float|int $quantity): void
    {
        try {
            $data = ['quantity' => $quantity];

            $childProduct = $this->productRepository->get($product->getSku());

            // if configurable
            if ($product->getId() != $childProduct->getId()) {
                $product = $this->productRepository->getById($product->getId());
                // for legacy reasons - we have been passing the SKU as ID for the child products
                $data['option_id'] = $childProduct->getSku();
                $data['option_sku'] = $childProduct->getSku();
                $data['option_name'] = $childProduct->getName();
                $data['option_price'] = (float)$childProduct->getFinalPrice();
            }

            $data['id'] = (string)$product->getId();
            $data['sku'] = $product->getSku();
            $data['name'] = $product->getName();
            $data['price'] = (float)$product->getFinalPrice();
            $data['url'] = $product->getProductUrl();

            $this->helper->addSessionEvent('track', 'add_to_cart', $data);
        } catch (Exception $e) {
            $this->helper->logError($e);
        }
    }
}
