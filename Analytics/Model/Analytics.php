<?php

namespace Richpanel\Analytics\Model;

use Magento\Framework\DataObject;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Registry;
use Magento\Search\Helper\Data as SearchHelper;
use Magento\Framework\View\Page\Title;
use Richpanel\Analytics\Helper\ImagePathResolver;
use Richpanel\Analytics\Helper\Data as AnalyticsHelper;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Category;

/**
 * Model object holding events data
 *
 * @author Shubhanshu Chouhan <shubhanshu@chouhan.com>
 */
class Analytics extends DataObject
{
    /**
     * @var array
     */
    protected array $events = [];

    /**
     * @var Context
     */
    protected $_context;

    /**
     * @var Registry
     */
    protected $_coreRegistry;

    /**
     * @var SearchHelper
     */
    protected $_searchHelper;

    /**
     * @var Title
     */
    protected $_pageTitle;

    /**
     * @var string|null
     */
    protected $fullActionName;

    /**
     * @var ImagePathResolver
     */
    protected $imagePathResolver;

    /**
     * @var AnalyticsHelper
     */
    protected $helper;

    /**
     * @param Context $context
     * @param Registry $registry
     * @param SearchHelper $searchHelper
     * @param Title $pageTitle
     * @param ImagePathResolver $imagePathResolver
     * @param AnalyticsHelper $helper
     */
    public function __construct(
        Context $context,
        Registry $registry,
        SearchHelper $searchHelper,
        Title $pageTitle,
        ImagePathResolver $imagePathResolver,
        AnalyticsHelper $helper
    ) {
        $this->_context = $context;
        $this->_coreRegistry = $registry;
        $this->_searchHelper = $searchHelper;
        $this->_pageTitle = $pageTitle;
        $this->fullActionName = $this->_context->getRequest()->getFullActionName();
        $this->imagePathResolver = $imagePathResolver;
        $this->helper = $helper;
        
        parent::__construct();
        
        $this->addPageEvents();
    }

    /**
     * Track page views
     *
     * @return void
     */
    public function addPageEvents(): void
    {
        if (!$this->fullActionName || $this->_isRejected($this->fullActionName)) {
            return;
        }

        $userProperties = $this->helper->updateWithUserDetails();

        // Catalog search pages
        if ($this->fullActionName === 'catalogsearch_result_index') {
            $query = $this->_searchHelper->getEscapedQueryText();
            if ($query) {
                $properties = ['query' => $query];
                $this->addEvent('track', 'search', $properties, $userProperties);
                return;
            }
        }

        // category view pages
        if ($this->fullActionName === 'catalog_category_view') {
            /** @var Category|null $category */
            $category = $this->_coreRegistry->registry('current_category');
            if ($category !== null) {
                $data = [
                    'id' => $category->getId(),
                    'name' => $category->getName()
                ];
                $this->addEvent('track', 'view_category', $data, $userProperties);
            }
            return;
        }

        // product view pages
        if ($this->fullActionName === 'catalog_product_view') {
            /** @var Product|null $product */
            $product = $this->_coreRegistry->registry('current_product');
            if ($product === null) {
                return;
            }

            $data = [
                'id' => $product->getId(),
                'sku' => $product->getSku(),
                'name' => $product->getName(),
                'price' => $product->getPrice(),
                'url' => $product->getProductUrl()
            ];

            $imageBasePath = $this->imagePathResolver->getBaseImage($product);
            if (!empty($imageBasePath)) {
                $data['image_url'] = [$imageBasePath];
            }

            $categoryIds = $product->getCategoryIds();
            if (!empty($categoryIds)) {
                $categories = [];
                $collection = $product->getCategoryCollection()
                    ->addAttributeToSelect('id')
                    ->addAttributeToSelect('name');

                foreach ($collection as $category) {
                    if ($category->getId() && $category->getName()) {
                        $categories[] = [
                            'id' => $category->getId(),
                            'name' => $category->getName()
                        ];
                    }
                }
                if (!empty($categories)) {
                    $data['categories'] = $categories;
                }
            }

            $this->addEvent('track', 'view_product', $data, $userProperties);
            return;
        }

        // cart view
        if ($this->fullActionName === 'checkout_cart_index') {
            $this->addEvent('track', 'view_cart', [], $userProperties);
            return;
        }

        // checkout
        if ($this->fullActionName === 'checkout_index_index') {
            $this->addEvent('track', 'checkout_start', [], $userProperties);
            return;
        }

        // CMS and any other pages
        $title = $this->_pageTitle->getShort();
        if ($title) {
            $this->addEvent('track', 'page_view', ['name' => $title], $userProperties);
        }
    }

    /**
     * Events that we don't want to track
     *
     * @param string $action full action name
     * @return bool
     */
    protected function _isRejected(string $action): bool
    {
        $rejected = [
            'catalogsearch_advanced_index',
            'catalogsearch_advanced_result'
        ];
        return in_array($action, $rejected, true);
    }

    /**
     * Add event to queue
     *
     * @param string $method Can be identiy|track
     * @param string $type
     * @param array|false $data
     * @param mixed $userProperties
     * @param mixed|null $customerId
     * @return void
     */
    public function addEvent(
        string $method,
        string $type,
        $data = false,
        $userProperties = false,
        $customerId = null
    ): void {
        if (empty($method) || empty($type)) {
            return;
        }

        $eventToAdd = [
            'method' => $method,
            'type' => $type,
            'properties' => $data ?: [],
            'userProperties' => $userProperties
        ];
        
        $this->events[] = $eventToAdd;
    }

    /**
     * Return events for tracking
     *
     * @return array
     */
    public function getEvents(): array
    {
        return $this->events;
    }
}
