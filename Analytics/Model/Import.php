<?php
/**
 * @author Shubhanshu Chouhan <shubhanshu@richpanel.com>
 */

namespace Richpanel\Analytics\Model;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\ResourceModel\Order\Collection;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Richpanel\Analytics\Helper\AdminStoreResolver;
use Richpanel\Analytics\Helper\Data;
use Exception;

/**
 * Model getting orders by chunks for Richpanel import
 *
 * @author Shubhanshu Chouhan <shubhanshu@richpanel.com>
 */
class Import
{
    /**
     * @var int
     */
    private int $ordersTotal = 0;

    /**
     * @var int
     */
    private int $totalChunks = 0;

    /**
     * @var int
     */
    private int $chunkItems = 50;

    /**
     * @var Data
     */
    private Data $data;

    /**
     * @var CollectionFactory
     */
    private CollectionFactory $orderCollection;

    /**
     * @var AdminStoreResolver
     */
    private AdminStoreResolver $resolver;

    /**
     * @param CollectionFactory $orderCollection
     * @param AdminStoreResolver $resolver
     * @param Data $data
     */
    public function __construct(
        CollectionFactory $orderCollection,
        AdminStoreResolver $resolver,
        Data $data
    ) {
        $this->orderCollection = $orderCollection;
        $this->resolver = $resolver;
        $this->data = $data;
    }

    /**
     * @return int
     */
    public function getTotalChunks(): int
    {
        return $this->totalChunks;
    }

    /**
     * Get chunk orders
     *
     * @param int $storeId
     * @param int $chunkId
     * @param string $duration
     * @param bool $isCron
     * @return Collection
     */
    public function getOrders(int $storeId, int $chunkId, string $duration, bool $isCron): Collection
    {
        return $this->getOrderQuery($storeId, $duration, $isCron)
            ->setPageSize($this->chunkItems)
            ->setCurPage($chunkId + 1);
    }

    /**
     * Get chunk orders for cron
     *
     * @param int $storeId
     * @param int $chunkId
     * @param string $duration
     * @return Collection
     */
    public function getOrdersForCron(int $storeId, int $chunkId, string $duration): Collection
    {
        return $this->getOrderQueryForCron($storeId, $duration)
            ->setPageSize($this->chunkItems)
            ->setCurPage($chunkId + 1);
    }

    /**
     * Chunks array
     *
     * @param int $storeId
     * @param string $duration
     * @param bool $isCron
     * @return int
     */
    public function getChunks(int $storeId = 0, string $duration = '-2 minutes', bool $isCron = false): int
    {
        try {
            $storeTotal = $this->getOrderQuery($storeId, $duration, $isCron)->getSize();
            return (int) ceil($storeTotal / $this->chunkItems);
        } catch (Exception $e) {
            $this->logError($e);
            return 0;
        }
    }

    /**
     * Get chunks for cron
     *
     * @param int $storeId
     * @param string $duration
     * @return int
     */
    public function getChunksForCron(int $storeId = 0, string $duration = '-2 minutes'): int
    {
        try {
            $storeTotal = $this->getOrderQueryForCron($storeId, $duration)->getSize();
            return (int) ceil($storeTotal / $this->chunkItems);
        } catch (Exception $e) {
            $this->logError($e);
            return 0;
        }
    }

    /**
     * Get contextual store id
     *
     * @return int
     */
    public function getStoreId(): int
    {
        return (int) $this->resolver->getAdminStoreId();
    }

    /**
     * Get order query
     *
     * @param int $storeId
     * @param string $duration
     * @param bool $isCron
     * @return Collection
     */
    protected function getOrderQuery(int $storeId = 0, string $duration = '-2 minutes', bool $isCron = false): Collection
    {
        $this->log('Calling getOrderQuery : ' . $duration . ', isCron: ' . ($isCron ? 'true' : 'false'));
        
        try {
            $dateMinusDuration = date("Y-m-d H:i:s", strtotime($duration) ?: time());
            $durationSelected = $this->getDurationSelected($storeId);
            if ($durationSelected !== 'resume' && $durationSelected !== '') {
                $dateMinusDuration = date("Y-m-d H:i:s", strtotime($durationSelected) ?: time());
            }

            return $this->orderCollection->create()
                ->addAttributeToFilter('store_id', $storeId)
                ->addAttributeToFilter('updated_at', ['gteq' => $dateMinusDuration])
                ->setOrder('created_at', 'desc');
        } catch (Exception $e) {
            $this->logError($e);
            return $this->orderCollection->create();
        }
    }

    /**
     * Get order query for cron
     *
     * @param int $storeId
     * @param string $duration
     * @return Collection
     */
    protected function getOrderQueryForCron(int $storeId = 0, string $duration = '-2 minutes'): Collection
    {
        $this->log('Calling getOrderQueryForCron : ' . $duration);

        try {
            return $this->orderCollection->create()
                ->addAttributeToFilter('store_id', $storeId)
                ->addAttributeToFilter('updated_at', ['gteq' => $duration])
                ->setOrder('created_at', 'desc');
        } catch (Exception $e) {
            $this->logError($e);
            return $this->orderCollection->create();
        }
    }

    /**
     * Get duration selected
     *
     * @param int $storeId
     * @return string
     */
    public function getDurationSelected(int $storeId): string
    {
        $selectedOption = $this->data->getDurationSelected($storeId);
        return $selectedOption ?? '';
    }

    /**
     * Get last run time
     *
     * @return int|null
     */
    public function getLastRunTime(): ?int
    {
        $this->log('Calling getLastRunTime');
        try {
            $objectManager = ObjectManager::getInstance();
            $resource = $objectManager->get(ResourceConnection::class);
            $connection = $resource->getConnection();
            $tableName = $resource->getTableName('richpanel_data');
            
            if (!$connection->isTableExists($tableName)) {
                return null;
            }

            $sql = "SELECT last_sync_time FROM " . $tableName;
            $result = $connection->query($sql);

            if ($result && $result->rowCount() > 0) {
                $row = $result->fetch();
                return isset($row['last_sync_time']) ? (int)$row['last_sync_time'] : null;
            }
        } catch (Exception $e) {
            $this->logError($e);
        }
        return null;
    }

    /**
     * Update last run time
     *
     * @param string $time
     * @param bool $createNew
     * @return void
     * @throws LocalizedException
     */
    public function updateLastRunTime(string $time, bool $createNew = false): void
    {
        $this->log('Calling updateLastRunTime: $time - ' . $time . ', $createNew - ' . ($createNew ? 'true' : 'false'));
        if (empty($time)) {
            return;
        }

        try {
            $epoch = strtotime($time);
            if ($epoch === false) {
                $this->log('Invalid time format provided');
                throw new LocalizedException(__('Invalid time format provided'));
            }

            $objectManager = ObjectManager::getInstance();
            $resource = $objectManager->get(ResourceConnection::class);
            $connection = $resource->getConnection();
            $tableName = $resource->getTableName('richpanel_data');

            if (!$connection->isTableExists($tableName)) {
                if (!$createNew) {
                    return;
                }

                $table = $connection->newTable($tableName)
                    ->addColumn(
                        'last_sync_time',
                        \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                        null,
                        ['nullable' => false],
                        'Last Sync Time'
                    );
                $connection->createTable($table);
            }

            $connection->insertOnDuplicate(
                $tableName,
                ['last_sync_time' => $epoch],
                ['last_sync_time']
            );
        } catch (Exception $e) {
            $this->logError($e);
            throw new LocalizedException(__('Failed to update last run time: %1', $e->getMessage()));
        }
    }

    /**
     * Log message
     *
     * @param string $message
     * @return void
     */
    private function log(string $message): void
    {
        $this->data->log($message);
    }

    /**
     * Log error
     *
     * @param Exception $e
     * @return void
     */
    private function logError(Exception $e): void
    {
        $this->data->log('Error in Import.php: ' . $e->getMessage());
    }
}
