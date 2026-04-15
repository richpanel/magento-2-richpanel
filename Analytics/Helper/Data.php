<?php

namespace Richpanel\Analytics\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Customer\Model\Context as CustomerContext;
use Magento\Customer\Model\Session;
use Magento\Customer\Model\SessionFactory;
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\AddressFactory;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\App\ProductMetadata;
use Magento\Framework\Json\Helper\Data as JsonHelper;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Exception;

/**
 * Helper class
 *
 * @author Shubhanshu Chouhan <shubhanshu@richpanel.com>
 */
class Data extends AbstractHelper
{
    const DATA_TAG = 'richpanel_events';

    const MODULE_NAME = 'Richpanel_Analytics';

    public $js_domain = 'api.richpanel.com/v2';
    private $push_domain = 'https://api.richpanel.com/v2';
    
    /**
     * @var CustomerFactory
     */
    protected $_customerFactory;

    /**
     * @var AddressFactory
     */
    protected $_addressFactory;

    /**
     * @var Session
     */
    protected $_customerSession;

    /**
     * @var Session
     */
    private Session $session;

    /**
     * @var JsonHelper
     */
    private JsonHelper $jsonHelper;

    /**
     * @var Client
     */
    private Client $clientHelper;

    /**
     * @var OrderSerializer
     */
    private OrderSerializer $orderSerializer;

    /**
     * @var ShipmentSerializer
     */
    private ShipmentSerializer $shipmentSerializer;

    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @var ProductMetadata
     */
    private ProductMetadata $metaData;

    /**
     * @var ModuleListInterface
     */
    private ModuleListInterface $moduleList;

    /**
     * @var HttpContext
     */
    private HttpContext $authContext;

    /**
     * @var Session
     */
    private Session $customerSessionFactory;

    /**
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $config
     * @param \Magento\Customer\Model\Session                    $session
     * @param \Psr\Log\LoggerInterface                           $logger
     * @param \Magento\Framework\Json\Helper\Data                $jsonHelper
     * @param Client                                             $clientHelper
     * @param OrderSerializer                                    $orderSerializer
     * @param \Magento\Store\Model\StoreManagerInterface         $storeManager
     * @param \Magento\Framework\App\ProductMetadata             $metaData
     * @param \Magento\Framework\Module\ModuleListInterface      $moduleList
     */
    public function __construct(
        Session $session,
        JsonHelper $jsonHelper,
        Client $clientHelper,
        OrderSerializer $orderSerializer,
        ShipmentSerializer $shipmentSerializer,
        StoreManagerInterface $storeManager,
        ProductMetadata $metaData,
        ModuleListInterface $moduleList,
        CustomerFactory $customerFactory,
        AddressFactory $addressFactory,
        SessionFactory $customerSessionFactory,
        Context $context,
        Session $customerSession,
        HttpContext $authContext
    ) {
        $this->session = $session;
        $this->jsonHelper = $jsonHelper;
        $this->clientHelper = $clientHelper;
        $this->orderSerializer = $orderSerializer;
        $this->shipmentSerializer = $shipmentSerializer;
        $this->storeManager = $storeManager;
        $this->metaData = $metaData;
        $this->moduleList = $moduleList;
        $this->_customerFactory = $customerFactory;
        $this->_addressFactory = $addressFactory;
        $this->_customerSession = $customerSession;
        $this->authContext = $authContext;
        $this->customerSessionFactory = $customerSessionFactory->create();
        parent::__construct($context);
    }

    /**
     * Update user details
     *
     * @param int|null $customerId
     * @return array|null
     */
    public function updateWithUserDetails(?int $customerId = null): ?array
    {
        $this->log('Calling updateWithUserDetails - ' . ($customerId ?? 'null'));
        
        try {
            $customer = null;
            if ($customerId !== null) {
                $this->log('updateWithUserDetails Fetching from customer id');
                $this->log((string)$customerId);
                $customer = $this->_customerFactory->create()->load($customerId);
            } elseif ($this->_customerSession->isLoggedIn()) {
                $this->log('updateWithUserDetails Fetching from session');
                $this->log((string)$this->_customerSession->getId());
                $customer = $this->_customerFactory->create()->load($this->_customerSession->getId());
            } elseif ($this->authContext->getValue(CustomerContext::CONTEXT_AUTH)) {
                $this->log('updateWithUserDetails Fetching from http');
                $customerId = $this->customerSessionFactory->getCustomer()->getId();
                $this->log((string)$customerId);
                $customer = $this->_customerFactory->create()->load($customerId);
            } else {
                $this->log('notlogged in');
                return null;
            }

            if ($customer && $customer->getId()) {
                $userProperties = $customer->getData();
                $data = [
                    'email' => $customer->getEmail(), 
                    'name'  => $customer->getName(),
                    'firstName' => $userProperties['firstname'] ?? '', 
                    'lastName' => $userProperties['lastname'] ?? '',
                    'dob' => $userProperties['dob'] ?? null,
                    'uid'   => $customer->getEmail(),
                    'sourceId'   => (int)$customer->getId()
                ];

                $billingAddressId = $customer->getDefaultBilling();
                if ($billingAddressId) {
                    $address = $this->_addressFactory->create()->load($billingAddressId);
                    if ($address->getId()) {
                        $addressData = $address->getData();
                        $data['billingAddress'] = [
                            'firstName' => $addressData['firstname'] ?? '',
                            'lastName' => $addressData['lastname'] ?? '',
                            'city' => $addressData['city'] ?? '',
                            'state' => $addressData['region'] ?? '',
                            'country' => $addressData['country_id'] ?? '',
                            'postcode' => $addressData['postcode'] ?? '',
                            'phone' => $addressData['telephone'] ?? '',
                            'address1' => $addressData['street'] ?? ''
                        ];
                    }
                }
        
                $shippingAddressId = $customer->getDefaultShipping();
                if ($shippingAddressId) {
                    $address = $this->_addressFactory->create()->load($shippingAddressId);
                    if ($address->getId()) {
                        $addressData = $address->getData();
                        $data['shippingAddress'] = [
                            'firstName' => $addressData['firstname'] ?? '',
                            'lastName' => $addressData['lastname'] ?? '',
                            'city' => $addressData['city'] ?? '',
                            'state' => $addressData['region'] ?? '',
                            'country' => $addressData['country_id'] ?? '',
                            'postcode' => $addressData['postcode'] ?? '',
                            'phone' => $addressData['telephone'] ?? '',
                            'address1' => $addressData['street'] ?? ''
                        ];
                    }
                }

                return $data;
            }
        } catch (Exception $e) {
            $this->log('updateWithUserDetails Error');
            $this->logError($e);
        }
        
        return null;
    }

    /**
     * Get storeId for the current request context
     *
     * @return int
     */
    public function getStoreId(): int
    {
        return (int)$this->storeManager->getStore()->getId();
    }

    /**
     * Check if richpanel module is enabled
     *
     * @param int $storeId
     * @return bool
     */
    public function isEnabled(int $storeId): bool
    {
        // $this->log('Calling isEnabled');
        return (bool)$this->scopeConfig->getValue(
            'richpanel_analytics/general/enable',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get API Token from system configuration
     *
     * @param int $storeId
     * @return string
     */
    public function getApiToken(int $storeId): string
    {
        return (string)$this->scopeConfig->getValue(
            'richpanel_analytics/general/api_key',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get API Secret from system configuration
     *
     * @param int $storeId
     * @return string
     */
    public function getApiSecret(int $storeId): string
    {
        return (string)$this->scopeConfig->getValue(
            'richpanel_analytics/general/api_secret',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get duration selected
     *
     * @param int $storeId
     * @return string
     */
    public function getDurationSelected(int $storeId): string
    {
        // $scope = ScopeConfigInterface::SCOPE_TYPE_DEFAULT;
        return (string)$this->scopeConfig->getValue(
            'richpanel_analytics/general/rp_duration',
            // $scope,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get session data with "richpanel_events" key
     *
     * @return array
     */
    public function getSessionEvents(): array
    {
        $events = [];
        if ($this->session->getData(self::DATA_TAG)) {
            $events = $this->session->getData(self::DATA_TAG, true);
        }
        return $events;
    }

    /**
     * Add event to session
     *
     * @param string  $method
     * @param string  $type
     * @param array   $data
     * @param boolean|string $metaData
     */
    public function addSessionEvent($method, $type, $data = false, $userProperties = false, $customerId = false)
    {
        $this->log('Customer addSessionEvent');
        $events = [];
        if ($this->session->getData(self::DATA_TAG) != '') {
            $events = (array)$this->session->getData(self::DATA_TAG);
        }

        if ($customerId) {
            $userProperties = $this->updateWithUserDetails($customerId);
        } else {
            $tempData = $this->updateWithUserDetails();
            if ($tempData) {
                $userProperties = $tempData;
            }
        }

        $eventToAdd = array(
            'method' => $method,
            'type' => $type,
            'properties' => $data,
            'userProperties' => $userProperties
        );

        // if ($customerId) {
        //     $userProperties = $this->updateWithUserDetails($customerId);
        // } else {
        //     $tempData = $this->updateWithUserDetails();
        //     if ($tempData) {
        //         $userProperties = $tempData;
        //     }
        // }

        array_push($events, $eventToAdd);
        $this->session->setData(self::DATA_TAG, $events);
    }

    /**
     * API call to Richpanel to submit information
     *
     * @param  int $storeId
     * @param  array $orders
     * @return void
     */
    public function callBatchApi($storeId, $orders)
    {
        $this->log('Calling callBatchApi');
        $ordersForSubmission = $this->_buildOrdersForSubmission($orders);
        if (!empty($ordersForSubmission)) {
            $call = $this->_buildCall($storeId, $ordersForSubmission);
            $this->_callRichpanelApi($storeId, $call);
        }
    }

    public function callBatchApiForShipment($storeId, $shipments)
    {
        $this->log('Calling callBatchApiForShipment');
        try {
            $shipmentForSubmission = [];
            foreach ($shipments as $shipment) {
                $this->log($shipment->getIncrementId());
                if ($shipment->getIncrementId()) {
                    // array_push($shipmentForSubmission, $this->shipmentSerializer->buildShipmentForSubmission($shipment));
                    array_push($shipmentForSubmission, $this->orderSerializer->buildOrderForSubmission($shipment->getOrder(), $shipment));
                }
            }
    
            $call = $this->_buildCall($storeId, $shipmentForSubmission);
            $this->_callRichpanelApi($storeId, $call);
        } catch (\Exception $e) {
            $this->logError($e);
        }
    }

    /**
     * Create submition ready arrays from Array of \Magento\Sales\Model\Order
     *
     * @param \Magento\Sales\Model\Order[] $orders
     * @return array
     */
    protected function _buildOrdersForSubmission($orders)
    {
        $this->log('Calling _buildOrdersForSubmission');
        $ordersForSubmission = [];
        foreach ($orders as $order) {
            // $this->log($order->getId());
            if ($order->getId() && $order->getCustomerEmail() !== null && trim($order->getCustomerEmail())) {
                array_push($ordersForSubmission, $this->orderSerializer->buildOrderForSubmission($order));

                $deleteEvent = $this->orderSerializer->buildDeleteEvent($order);
                if (!empty($deleteEvent)) {
                    array_push($ordersForSubmission, $deleteEvent);
                }
            }
        }
        return $ordersForSubmission;
    }

    /**
     * Create call array
     *
     * @param  int $storeId
     * @param  array $ordersForSubmission
     * @return array
     */
    protected function _buildCall($storeId, $ordersForSubmission)
    {
        $this->log('Calling _buildCall');
        return array(
            'appClientId'    => $this->getApiToken($storeId),
            'events'   => $ordersForSubmission,
            // for debugging/support purposes
            'platform' => 'Magento ' . $this->metaData->getEdition() . ' ' . $this->metaData->getVersion(),
            'version'  => $this->moduleList->getOne(self::MODULE_NAME)['setup_version'],
            'event' => 'send_batch'
        );
    }

    /**
     * Submit orders to Richpanel API via post request
     *
     * @param  int $storeId
     * @param  array $call
     * @return void
     */
    protected function _callRichpanelApi($storeId, $call)
    {
        $this->log('Calling _callRichpanelApi');
        ksort($call);
        $basedCall = base64_encode($this->jsonHelper->jsonEncode($call));
        $signature = hash('sha256', $basedCall . $this->getApiSecret($storeId)); //md5($basedCall . $this->getApiSecret($storeId));
        $requestBody = [
            's'   => $signature,
            'hs'  => $basedCall
        ];
        $this->clientHelper->post($this->push_domain . '/bt', $requestBody);
    }

    public function log($value)
    {
        try {
            $line = date('Y-m-d\TH:i:sP') . ' INFO (6): ' . (is_string($value) ? $value : var_export($value, true)) . PHP_EOL;
            @file_put_contents(BP . '/var/log/richpanel.log', $line, FILE_APPEND);
        } catch (\Exception $e) {
            // swallow — logging must never break request flow
        }
    }

    /**
     * Log error to logs
     *
     * @param  \Exception $exception
     * @return void
     */
    public function logError($exception)
    {
        if ($exception instanceof \Exception) {
            $this->log($exception->getMessage());
        } else {
            $this->log($exception);
        }
    }
}
