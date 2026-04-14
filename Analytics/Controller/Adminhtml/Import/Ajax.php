<?php

namespace Richpanel\Analytics\Controller\Adminhtml\Import;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Richpanel\Analytics\Helper\Data;
use Richpanel\Analytics\Model\Import;
use Exception;

/**
 * AJAX Controller for sending chunks to Richpanel
 *
 * @author Shubhanshu Chouhan <shubhanshu@richpanel.com>
 */
class Ajax extends Action
{
    private Data $helper;
    private Import $import;
    private Http $request;
    private JsonFactory $resultJsonFactory;

    /**
     * @param \Magento\Backend\App\Action\Context              $context
     * @param \Richpanel\Analytics\Helper\Data                   $helper
     * @param \Richpanel\Analytics\Model\Import                  $import
     * @param \Magento\Framework\App\Request\Http              $request
     * @param \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
     */
     # TODO: Ask Miro why \Magento\Framework|App\Action|Context won't compile
    public function __construct(
        Context $context,
        Data $helper,
        Import $import,
        Http $request,
        JsonFactory $resultJsonFactory
    ) {
        parent::__construct($context);
        $this->helper = $helper;
        $this->import = $import;
        $this->request = $request;
        $this->resultJsonFactory = $resultJsonFactory;
    }

    /**
     * Import orders history by chunks
     *
     * @return Json
     */
    public function execute(): Json
    {
        try {
            $jsonFactory = $this->resultJsonFactory->create();
            $result = ['success' => false];

            $storeId = (int)$this->request->getParam('storeId');
            $chunkId = (int)$this->request->getParam('chunkId');
            $duration = $this->request->getParam('duration') ?: '';
            // $totalChunks = (int)$this->request->getParam('totalChunks');

            // if ($chunkId == 0) {
            //     $this->helper->createActivity($storeId, 'import_start');
            // }

            // Get orders from the Database
            $orders = $this->import->getOrders($storeId, $chunkId, $duration, FALSE);
            // Send orders via API helper method
            $this->helper->callBatchApi($storeId, $orders, false);
            $result['success'] = true;

            // if ($chunkId == $totalChunks - 1) {
            //     $this->helper->createActivity($storeId, 'import_end');
            // }

            return $jsonFactory->setData($result);
        } catch (Exception $e) {
            return $jsonFactory->setData([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
}
