<?php
declare(strict_types=1);

namespace IDangerous\Sms\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Select;
use Magento\Newsletter\Model\ResourceModel\Subscriber\CollectionFactory;
use Psr\Log\LoggerInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;

class BulkRecipientService
{
    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * @var CollectionFactory
     */
    private $subscriberCollectionFactory;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var TimezoneInterface
     */
    private $timezone;

    /**
     * @param ResourceConnection $resourceConnection
     * @param CollectionFactory $subscriberCollectionFactory
     * @param LoggerInterface $logger
     * @param TimezoneInterface $timezone
     */
    public function __construct(
        ResourceConnection $resourceConnection,
        CollectionFactory $subscriberCollectionFactory,
        LoggerInterface $logger,
        TimezoneInterface $timezone
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->subscriberCollectionFactory = $subscriberCollectionFactory;
        $this->logger = $logger;
        $this->timezone = $timezone;
    }

    /**
     * Get recipients based on filters
     *
     * @param array $filters
     * @return array
     */
    public function getRecipients(array $filters = []): array
    {
        try {
            $collection = $this->subscriberCollectionFactory->create();

            // Start with base query
            $collection->addFieldToSelect([
                'subscriber_id',
                'subscriber_email',
                'subscriber_phone',
                'customer_id'
            ]);

            $collection->addFieldToFilter('subscriber_phone', ['notnull' => true])
                      ->addFieldToFilter('sms_status', ['eq' => 1]);

            // Add customer type filter
            $customerType = $filters['customer_type'] ?? 'all';

            // Join customer table if needed for registered customers
            if ($customerType === 'registered' || (!empty($filters['customer_groups']) && $customerType !== 'guest')) {
                $collection->getSelect()
                    ->join(
                        ['customer' => $collection->getTable('customer_entity')],
                        'main_table.customer_id = customer.entity_id',
                        [
                            'firstname',
                            'lastname',
                            'email',
                            'gender' => new \Zend_Db_Expr('CASE
                                WHEN customer.gender = 1 THEN "Erkek"
                                WHEN customer.gender = 2 THEN "Kadın"
                                ELSE ""
                            END')
                        ]
                    );

                // Updated DOB join with explicit attribute fetch
                $collection->getSelect()
                    ->joinLeft(
                        ['eav_attribute' => $collection->getTable('eav_attribute')],
                        "eav_attribute.attribute_code = 'dob' AND eav_attribute.entity_type_id = 1",
                        []
                    )
                    ->joinLeft(
                        ['customer_dob' => $collection->getTable('customer_entity_datetime')],
                        "customer.entity_id = customer_dob.entity_id AND customer_dob.attribute_id = eav_attribute.attribute_id",
                        ['dob' => new \Zend_Db_Expr('DATE_FORMAT(customer_dob.value, "%d/%m/%Y")')]
                    );

                // For debugging
                $this->logger->debug('Generated SQL: ' . $collection->getSelect()->__toString());
            }

            // Apply customer group filter
            if (!empty($filters['customer_groups']) && $customerType !== 'guest') {
                $customerGroups = explode(',', (string)$filters['customer_groups']);
                if (!empty($customerGroups)) {
                    $collection->getSelect()
                        ->where('customer.group_id IN (?)', $customerGroups);
                }
            }

            // Check if we need to join sales_order table
            $needsSalesOrderJoin = ($customerType !== 'guest') &&
                (!empty($filters['order_period']) || !empty($filters['min_purchase_count']));

            if ($needsSalesOrderJoin) {
                $collection->getSelect()
                    ->join(
                        ['sales_order' => $collection->getTable('sales_order')],
                        'customer.entity_id = sales_order.customer_id',
                        []
                    )
                    ->group('main_table.subscriber_id');

                // Apply order period filter
                if (!empty($filters['order_period'])) {
                    $now = $this->timezone->date();

                    switch ($filters['order_period']) {
                        case '30':
                            $date = $now->modify('-30 days');
                            break;
                        case '90':
                            $date = $now->modify('-90 days');
                            break;
                        case '180':
                            $date = $now->modify('-180 days');
                            break;
                        case '365':
                            $date = $now->modify('-365 days');
                            break;
                        default:
                            $date = null;
                    }

                    if ($date) {
                        $collection->getSelect()
                            ->where('sales_order.created_at >= ?', $date->format('Y-m-d H:i:s'));
                    }
                }

                // Apply minimum purchase count filter
                if (!empty($filters['min_purchase_count'])) {
                    $minCount = (int)$filters['min_purchase_count'];
                    if ($minCount > 0) {
                        $collection->getSelect()
                            ->having('COUNT(DISTINCT sales_order.entity_id) >= ?', $minCount);
                    }
                }
            }

            // For debugging
            $this->logger->debug('Generated SQL: ' . $collection->getSelect()->__toString());
            // echo 'Generated SQL: ' . $collection->getSelect()->__toString();
            // die();

            return $collection->getItems();

        } catch (\Exception $e) {
            $this->logger->error('Error getting recipients: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Add order period filter to collection
     *
     * @param \Magento\Newsletter\Model\ResourceModel\Subscriber\Collection $collection
     * @param string $period
     */
    private function addOrderPeriodFilter($collection, string $period): void
    {
      $now = $this->timezone->date();

      switch ($period) {
          case '30':
              $date = $now->modify('-30 days');
              break;
          case '90':
              $date = $now->modify('-90 days');
              break;
          case '180':
              $date = $now->modify('-180 days');
              break;
          case '365':
              $date = $now->modify('-365 days');
              break;
          default:
              return;
      }

        $collection->getSelect()
            ->join(
                ['sales_order' => $collection->getTable('sales_order')],
                'customer.entity_id = sales_order.customer_id',
                []
            )
            ->where('sales_order.created_at >= ?', $date->format('Y-m-d H:i:s'))
            ->group('main_table.subscriber_id');

    }

    /**
     * Add purchase count filter to collection
     *
     * @param \Magento\Newsletter\Model\ResourceModel\Subscriber\Collection $collection
     * @param int $minCount
     */
    private function addPurchaseCountFilter($collection, int $minCount): void
    {
        $collection->getSelect()
            ->join(
                ['sales_order' => $collection->getTable('sales_order')],
                'customer.entity_id = sales_order.customer_id',
                []
            )
            ->group('main_table.subscriber_id')
            ->having('COUNT(sales_order.entity_id) >= ?', $minCount);
    }

    /**
     * Count recipients based on filters
     *
     * @param array $filters
     * @return int
     */
    public function countRecipients(array $filters): int
    {
        $connection = $this->resourceConnection->getConnection();
        $select = $this->buildRecipientSelect($filters);

        $countSelect = $connection->select()
            ->from(
                ['main_table' => $select],
                [new \Zend_Db_Expr('COUNT(*)')]
            );

        return (int)$connection->fetchOne($countSelect);
    }

    /**
     * Build base select query for recipients
     *
     * @param array $filters
     * @return Select
     */
    private function buildRecipientSelect(array $filters): Select
    {
        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from(
                ['subscriber' => $this->resourceConnection->getTableName('newsletter_subscriber')],
                ['subscriber_phone']
            )
            // ->where('subscriber.subscriber_status = ?', \Magento\Newsletter\Model\Subscriber::STATUS_SUBSCRIBED)
            ->where('subscriber.subscriber_phone IS NOT NULL')
            ->where('subscriber.sms_status = ?', 1);

        // Add customer type filter
        $customerType = $filters['customer_type'] ?? 'all';

        // Join customer table if needed for registered customers
        if ($customerType === 'registered' || (!empty($filters['customer_groups']) && $customerType !== 'guest')) {
            $select->join(
                ['customer' => $this->resourceConnection->getTableName('customer_entity')],
                'subscriber.customer_id = customer.entity_id',
                []
            );
        } elseif ($customerType === 'guest') {
            $select->where('subscriber.customer_id = ?', 0);
        }

        // Apply customer group filter
        if (!empty($filters['customer_groups']) && $customerType !== 'guest') {
            $customerGroups = implode(',', $filters['customer_groups']);
            if (!empty($customerGroups)) {
                $select->where('customer.group_id IN (?)', $customerGroups);
            }
        }

        // Check if we need to join sales_order table
        $needsSalesOrderJoin = ($customerType !== 'guest') &&
            (!empty($filters['order_period']) || !empty($filters['min_purchase_count']));

        if ($needsSalesOrderJoin) {
            $select->join(
                ['sales_order' => $this->resourceConnection->getTableName('sales_order')],
                'customer.entity_id = sales_order.customer_id',
                []
            )
            ->group('subscriber.subscriber_id');

            // Apply order period filter
            if (!empty($filters['order_period'])) {
                $now = $this->timezone->date();
                switch ($filters['order_period']) {
                    case '30days':
                        $date = $now->modify('-30 days');
                        break;
                    case '90days':
                        $date = $now->modify('-90 days');
                        break;
                    case '180days':
                        $date = $now->modify('-180 days');
                        break;
                    case '365days':
                        $date = $now->modify('-365 days');
                        break;
                    default:
                        $date = null;
                }

                if ($date) {
                    $select->where('sales_order.created_at >= ?', $date->format('Y-m-d H:i:s'));
                }
            }

            // Apply minimum purchase count filter
            if (!empty($filters['min_purchase_count'])) {
                $minCount = (int)$filters['min_purchase_count'];
                if ($minCount > 0) {
                    $select->having('COUNT(DISTINCT sales_order.entity_id) >= ?', $minCount);
                }
            }
        }

        // For debugging
        // echo $select->__toString();
        // exit;

        return $select;
    }

    /**
     * For debugging purposes
     *
     * @param array $filters
     * @return string
     */
    public function getDebugSql(array $filters): string
    {
        $select = $this->buildRecipientSelect($filters);
        return $select->__toString();
    }
}