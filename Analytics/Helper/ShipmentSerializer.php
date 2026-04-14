<?php

namespace Richpanel\Analytics\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Catalog\Model\ProductRepository;
use Magento\Sales\Model\Order\Shipment;
use Magento\Sales\Model\Order\Item as OrderItem;
use Exception;

class ShipmentSerializer extends AbstractHelper
{
    private ProductRepository $productRepository;
    private ImagePathResolver $imagePathResolver;
    private OrderSerializer $orderSerializer;

    public function __construct(
        Context $context,
        ProductRepository $productRepository,
        ImagePathResolver $imagePathResolver,
        OrderSerializer $orderSerializer
    ) {
        $this->productRepository = $productRepository;
        $this->imagePathResolver = $imagePathResolver;
        $this->orderSerializer = $orderSerializer;
        
        parent::__construct($context);
    }

    /**
     * Build individual shipment data
     *
     * @param Shipment $shipment
     * @return array
     */
    public function buildShipmentForSubmission(Shipment $shipment): array
    {
        $identityData = $this->orderSerializer->orderIdentityData($shipment->getOrder());

        $call = [
            'event'         => 'fulfillment',
            'properties'    => $this->prepareShipmentDetails($shipment),
            'userProperties'    => $identityData,
            'time' => ['sentAt' => round(microtime(true) * 1000)]
        ];

        ksort($call);
        return $call;
    }

    /**
     * Get shipment details and sort them for richpanel
     *
     * @param Shipment $shipment
     * @return array
     */
    public function prepareShipmentDetails(Shipment $shipment): array
    {
        try {
            $data = [
                'id'        => $shipment->getIncrementId(),
                'orderId'   => $shipment->getOrder()->getIncrementId(),
                'status'    => $shipment->getShipmentStatus(),
                'quantity'  => (float)$shipment->getTotalQty(),
                'weight'    => (float)$shipment->getTotalWeight()
            ];

            $createdAt = $shipment->getCreatedAt();
            if (!empty($createdAt)) {
                $date = strtotime($createdAt) * 1000;
                $data['createdAt'] = $date;
            }

            $updatedAt = $shipment->getUpdatedAt();
            if (!empty($updatedAt)) {
                $date = strtotime($updatedAt) * 1000;
                $data['updatedAt'] = $date;
            }

            $data['tracking'] = $this->getTrackingNumberByOrderData($shipment);
            $this->orderSerializer->assignBillingInfo($data, $shipment);
            
            $count = 0;
            foreach ($shipment->getItemsCollection() as $item) {
                $data['items'][] = $this->getProductDetails($item->getOrderItem());
                $data['items'][$count]['quantity'] = (float)$item->getQty();
                $count++;
            }

            return $data;
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Get tracking number data from shipment
     *
     * @param Shipment $shipment
     * @return array
     */
    public function getTrackingNumberByOrderData(Shipment $shipment): array
    {
        $trackNumbers = [];
        $tracksCollection = $shipment->getTracksCollection();
        
        if (!empty($tracksCollection)) {
            foreach ($tracksCollection->getItems() as $track) {
                $data = $track->getData();

                $result = [
                    'trackingNumber' => $track->getTrackNumber(),
                ];

                $company = $data['title'] ?? '';
                if (empty($company)) {
                    $company = $data['carrier_code'] ?? '';
                }

                $result['trackingCompany'] = $company;

                if (!empty($data['updated_at'])) {
                    $date = strtotime($data['updated_at']) * 1000;
                    $result['shippingDate'] = $date;
                }

                $carrierCode = $track->getCarrierCode();
                $url = '';
                if ($carrierCode === 'usps') {
                    $url = 'https://tools.usps.com/go/TrackConfirmAction.action?tLabels=' . $track->getTrackNumber();
                } elseif ($carrierCode === 'fedex') {
                    $url = 'https://www.fedex.com/fedextrack/?tracknumbers=' . $track->getTrackNumber();
                } elseif ($carrierCode === 'ups') {
                    $url = 'http://wwwapps.ups.com/WebTracking/processInputRequest?TypeOfInquiryNumber=T&InquiryNumber1=' . $track->getTrackNumber();
                }

                if (!empty($url)) {
                    $result['trackingUrl'] = $url;
                }

                $trackNumbers[] = $result;
            }
        }
        return $trackNumbers;
    }

    /**
     * Get product details from order item
     *
     * @param OrderItem $quoteItem
     * @return array
     */
    private function getProductDetails(OrderItem $quoteItem): array
    {
        $dataItem = [
            'id'        => (int)$quoteItem->getProductId(),
            'price'     => (float)$quoteItem->getBasePrice(),
            'name'      => $quoteItem->getName(),
            'quantity'  => (int)$quoteItem->getQtyOrdered()
        ];

        if ($quoteItem->getProductType() === 'configurable') {
            $options = (array)$quoteItem->getProductOptions();
            $dataItem['option_id'] = $options['simple_sku'] ?? '';
            $dataItem['option_sku'] = $options['simple_sku'] ?? '';
            $dataItem['option_name'] = $options['simple_name'] ?? '';
            $dataItem['option_price'] = (float)$quoteItem->getBasePrice();
        }

        try {
            if ($quoteItem->getProductType() === 'configurable') {
                $parentId = $quoteItem->getProductId();
                $product = $this->productRepository->getById($parentId);
            } else {
                $product = $quoteItem->getProduct();
            }

            if ($product) {
                $imageBasePath = $this->imagePathResolver->getBaseImage($product);
                if (!empty($imageBasePath)) {
                    $dataItem['image_url'] = [$imageBasePath];
                }
                $dataItem['url'] = $product->getProductUrl();
                $dataItem['sku'] = $product->getSku();

                $categoryIds = $product->getCategoryIds();
                if (!empty($categoryIds)) {
                    $categories = [];
                    $collection = $product->getCategoryCollection()
                        ->addAttributeToSelect('id')
                        ->addAttributeToSelect('name');
    
                    foreach ($collection as $category) {
                        $categories[] = [
                            'id' => $category->getId(),
                            'name' => $category->getName()
                        ];
                    }
                    $dataItem['categories'] = $categories;
                }
            }
        } catch (Exception $e) {
            // Silently handle product loading errors
        }

        return $dataItem;
    }
}
