<?php

namespace Richpanel\Analytics\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Customer\Model\Session;
use Richpanel\Analytics\Helper\Data;
use Richpanel\Analytics\Model\Analytics as AnalyticsModel;
use Exception;

/**
 * Block rendering events to frontend
 *
 * @author Shubhanshu Chouhan <shubhanshu@richpanel.com>
 */
class Analytics extends Template
{
    /**
     * @var Data
     */
    public Data $helper;

    /**
     * @var AnalyticsModel
     */
    private AnalyticsModel $dataModel;

    /**
     * @var Session
     */
    private Session $customerSession;

    /**
     * @param Context $context
     * @param Data $helper
     * @param AnalyticsModel $dataModel
     * @param Session $session
     * @param array $data
     */
    public function __construct(
        Context $context,
        Data $helper,
        AnalyticsModel $dataModel,
        Session $session,
        array $data = []
    ) {
        $this->helper = $helper;
        $this->dataModel = $dataModel;
        $this->customerSession = $session;
        parent::__construct($context, $data);
    }

    /**
     * Get API Token
     *
     * @return string|null
     */
    public function getApiToken(): ?string
    {
        return $this->helper->getApiToken($this->helper->getStoreId());
    }

    /**
     * Get events to track them to Richpanel js api
     *
     * @return array
     */
    public function getEvents(): array
    {
        $sessionEvents = $this->helper->getSessionEvents() ?: [];
        $modelEvents = $this->dataModel->getEvents() ?: [];
        return array_merge($sessionEvents, $modelEvents);
    }

    /**
     * Get Richpanel user data
     *
     * @return array
     */
    public function getRichpanelUserData(): array
    {
        $data = $this->helper->updateWithUserDetails();
        if ($data !== null) {
            $encrypted = $this->encryptData($data, $this->helper->getStoreId());
            return [
                'data' => $encrypted,
                'normalData' => $data
            ];
        }
        return ['data' => null, 'normalData' => null];
    }

    /**
     * Encrypt data using store API secret
     *
     * @param mixed $data
     * @param int|null $storeId
     * @return string|null
     */
    public function encryptData(mixed $data, ?int $storeId = null): ?string
    {
        if ($data === null) {
            return null;
        }
        
        $apiSecret = $this->helper->getApiSecret($storeId);
        if ($apiSecret === null) {
            $this->_logger->error('API secret is missing/null');
            return null;
        }

        $method = 'AES-256-CBC';
        $key = hash('sha256', $apiSecret);
        $jsonData = json_encode($data);
        if ($jsonData === false) {
            $this->_logger->error('Failed to encode data for encryption');
            return null;
        }
        
        return $this->encrypt($jsonData, $key, $method);
    }

    /**
     * Encrypt data using specified method
     *
     * @param string $data
     * @param string $key
     * @param string $method
     * @return string|null
     */
    public function encrypt(string $data, string $key, string $method): ?string
    {
        try {
            $ivSize = openssl_cipher_iv_length($method);
            if ($ivSize === false) {
                return null;
            }
            
            $iv = openssl_random_pseudo_bytes($ivSize);
            if ($iv === false) {
                return null;
            }
            
            $ciphertext = openssl_encrypt($data, $method, $key, OPENSSL_RAW_DATA, $iv);
            if ($ciphertext === false) {
                return null;
            }
            
            $ciphertextHex = bin2hex($ciphertext);
            $ivHex = bin2hex($iv);
            
            return "$ivHex:$ciphertextHex";
        } catch (Exception $e) {
            $this->_logger->error('Encryption error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Render richpanel js if module is enabled
     *
     * @return string
     * @codeCoverageIgnore
     */
    protected function _toHtml(): string
    {
        if (!$this->helper->isEnabled($this->helper->getStoreId())) {
            return '';
        }
        return parent::_toHtml();
    }
}
