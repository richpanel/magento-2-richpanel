<?php

namespace Richpanel\Analytics\Cron;

use Magento\Store\Model\StoreManagerInterface;
use Richpanel\Analytics\Helper\Data;
use Richpanel\Analytics\Model\Import;
use Exception;

class OrderUpdateCheck
{
    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @var Data
     */
    private Data $helper;

    /**
     * @var Import
     */
    private Import $import;

    /**
     * @param StoreManagerInterface $storeManager
     * @param Data $helper
     * @param Import $import
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        Data $helper,
        Import $import
    ) {
        $this->helper = $helper;
        $this->import = $import;
        $this->storeManager = $storeManager;
    }

    /**
     * Execute 2-minute sync
     *
     * @return void
     */
    public function execute(): void
    {
        $this->helper->log('calling sync 2mins');
        $this->sync('-2 minutes');
    }

    /**
     * Execute daily sync
     *
     * @return void
     */
    public function executeDaily(): void
    {
        $this->helper->log('calling sync 24h');
        $this->sync('-24 hours');
    }

    /**
     * Sync orders based on duration
     *
     * @param string $duration Duration string (e.g., '-2 minutes', '-24 hours')
     * @return $this
     */
    public function sync(string $duration): self
    {
        try {
            $this->cronLog(__METHOD__);

            $storeManagerDataList = $this->storeManager->getStores();
            if (empty($storeManagerDataList)) {
                return $this;
            }

            $isNew = false;
            $currentTime = time();
            $dateMinusDuration = date("Y-m-d H:i:s", strtotime($duration, $currentTime));
            $dateMinusDurationForUpdate = $dateMinusDuration;

            $lastRunTime = $this->import->getLastRunTime();
            if (!empty($lastRunTime)) {
                $dateMinusDuration = date("Y-m-d H:i:s", $lastRunTime);
            } else {
                $isNew = true;
            }

            foreach ($storeManagerDataList as $storeId => $value) {
                if ($storeId === null) {
                    continue;
                }

                $chunks = $this->import->getChunksForCron($storeId, $dateMinusDuration) ?: 0;
                
                for ($chunkId = 0; $chunkId <= $chunks; $chunkId++) {
                    $this->helper->log('doing order sync from cron');
                    $orders = $this->import->getOrdersForCron($storeId, $chunkId, $dateMinusDuration) ?: [];
                    if (!empty($orders)) {
                        $this->helper->callBatchApi($storeId, $orders, false);
                    }
                }
            }

           $this->import->updateLastRunTime($dateMinusDurationForUpdate, $isNew);
        } catch (Exception $e) {
            $this->cronLog('ERROR: ' . $e->getMessage());
        }
        return $this;
    }

    /**
     * Append a line to var/log/cron.log (same destination as the previous Zend_Log writer).
     */
    private function cronLog(string $message): void
    {
        try {
            $line = date('Y-m-d\TH:i:sP') . ' INFO (6): ' . $message . PHP_EOL;
            @file_put_contents(BP . '/var/log/cron.log', $line, FILE_APPEND);
        } catch (Exception $e) {
            // swallow
        }
    }
}