<?php

namespace Richpanel\Analytics\Block\System\Config\Button;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\App\ProductMetadataInterface;
use Richpanel\Analytics\Helper\Data;
use Richpanel\Analytics\Model\Import as ImportModel;

/**
 * Block for import button in richpanel configuration
 *
 * @author Shubhanshu Chouhan <shubhanshu@richpanel.com>
 */
class Import extends Field
{
    /**
     * Path to block template
     */
    private const CHECK_TEMPLATE = 'system/config/button/import.phtml';

    /**
     * @var Data
     */
    private Data $helper;

    /**
     * @var ImportModel
     */
    private ImportModel $import;

    /**
     * @var ProductMetadataInterface
     */
    private ProductMetadataInterface $productMetadata;

    /**
     * Import constructor.
     *
     * @param Context $context
     * @param Data $helper
     * @param ImportModel $import
     * @param ProductMetadataInterface $productMetadata
     * @param array $data
     */
    public function __construct(
        Context $context,
        Data $helper,
        ImportModel $import,
        ProductMetadataInterface $productMetadata,
        array $data = []
    ) {
        $this->helper = $helper;
        $this->import = $import;
        $this->productMetadata = $productMetadata;
        parent::__construct($context, $data);
    }

    /**
     * Set template to itself
     *
     * @return $this
     */
    protected function _prepareLayout(): Import
    {
        if (version_compare($this->getMagentoVersion(), '2.4.7', '>=')) {
            $this->setTemplate('Richpanel_Analytics::system/config/button/import.phtml');
        } else {
            parent::_prepareLayout();
        }
        return $this;
    }

    /**
     * Get Magento version
     *
     * @return string
     */
    private function getMagentoVersion(): string
    {
        return (string)$this->productMetadata->getVersion();
    }

    /**
     * Render button and remove scope label
     *
     * @param AbstractElement $element
     * @return string
     */
    public function render(AbstractElement $element): string
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }

    /**
     * Get block custom template html for the button
     *
     * @param AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element): string
    {
        $originalData = $element->getOriginalData();
        $buttonUrl = $originalData['button_url'] ?? '';
        $this->addData(
            [
                'intern_url' => $this->getUrl($buttonUrl),
                'html_id' => $element->getHtmlId(),
            ]
        );
        return $this->_toHtml();
    }

    /**
     * Check if button is enabled
     *
     * @return bool
     */
    public function buttonEnabled(): bool
    {
        $storeId = $this->import->getStoreId();
        return $this->helper->isEnabled((int)$storeId)
            && $this->helper->getApiToken((int)$storeId)
            && $this->helper->getApiSecret((int)$storeId);
    }

    /**
     * Generate URL for AJAX import controller
     *
     * @return string
     */
    public function getAjaxUrl(): string
    {
        return $this->getUrl('richpanel/import/ajax');
    }

    /**
     * Import model
     *
     * @return ImportModel
     */
    public function getImport(): ImportModel
    {
        return $this->import;
    }
}
