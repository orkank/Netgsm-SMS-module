<?php
declare(strict_types=1);

namespace IDangerous\Sms\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Select;
use Psr\Log\LoggerInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;

class BulkRecipientService
{
    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

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
     * @param LoggerInterface $logger
     * @param TimezoneInterface $timezone
     */
    public function __construct(
        ResourceConnection $resourceConnection,
        LoggerInterface $logger,
        TimezoneInterface $timezone
    ) {
        $this->resourceConnection = $resourceConnection;
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
            $connection = $this->resourceConnection->getConnection();
            $iysTable = $this->resourceConnection->getTableName('iys_data');
            $customerTable = $this->resourceConnection->getTableName('customer_entity');
            $salesOrderTable = $this->resourceConnection->getTableName('sales_order');

            // Start with base query
            $select = $connection->select()
                ->from(
                    ['main_table' => $iysTable],
                    [
                        'subscriber_phone' => 'value', // Keep this alias for compatibility
                        'customer_id' => 'userid'
                    ]
                )
                ->where('main_table.type = ?', 'sms')
                ->where('main_table.status = ?', 1)
                ->where('main_table.iys_status = ?', 1)
                ->where('main_table.value IS NOT NULL')
                ->where('main_table.value != ?', '')
                ->group('main_table.value'); // Always group by phone number to prevent duplicates

            // Add customer type filter
            $customerType = $filters['customer_type'] ?? 'all';

            // First, handle customer group filter
            if (!empty($filters['customer_groups']) && $customerType !== 'guest') {
                $customerGroups = explode(',', (string)$filters['customer_groups']);
                if (!empty($customerGroups)) {
                    $select->joinLeft(
                        ['customer' => $customerTable],
                        'main_table.userid = customer.entity_id',
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
                    )
                    ->where('customer.group_id IN (?)', $customerGroups);
                }
            }

            // Then handle customer type
            if ($customerType === 'registered') {
                if (!isset($select->getPart(\Magento\Framework\DB\Select::FROM)['customer'])) {
                    $select->joinLeft(
                        ['customer' => $customerTable],
                        'main_table.userid = customer.entity_id',
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
                }
                $select->where('main_table.userid IS NOT NULL')
                      ->where('customer.entity_id IS NOT NULL');

                // Add DOB join
                $select->joinLeft(
                    ['eav_attribute' => $this->resourceConnection->getTableName('eav_attribute')],
                    "eav_attribute.attribute_code = 'dob' AND eav_attribute.entity_type_id = 1",
                    []
                )
                ->joinLeft(
                    ['customer_dob' => $this->resourceConnection->getTableName('customer_entity_datetime')],
                    "customer.entity_id = customer_dob.entity_id AND customer_dob.attribute_id = eav_attribute.attribute_id",
                    ['dob' => new \Zend_Db_Expr('DATE_FORMAT(customer_dob.value, "%d/%m/%Y")')]
                );
            } elseif ($customerType === 'guest') {
                $select->where('main_table.userid IS NULL OR main_table.userid = ?', '');
            }

            // Check if we need to join sales_order table
            $needsSalesOrderJoin = ($customerType !== 'guest') &&
                (!empty($filters['order_period']) || !empty($filters['min_purchase_count']));

            if ($needsSalesOrderJoin) {
                if (!isset($select->getPart(\Magento\Framework\DB\Select::FROM)['customer'])) {
                    $select->joinLeft(
                        ['customer' => $customerTable],
                        'main_table.userid = customer.entity_id',
                        []
                    );
                }

                $select->joinLeft(
                    ['sales_order' => $salesOrderTable],
                    'customer.entity_id = sales_order.customer_id',
                    []
                );

                // Apply order period filter
                if (!empty($filters['order_period'])) {
                    $now = $this->timezone->date();

                    switch ($filters['order_period']) {
                      case '7':
                        $date = $now->modify('-7 days');
                        break;

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

            // Debug the generated SQL
            $this->logger->debug('SQL Query: ' . $select->__toString());

            return $connection->fetchAll($select);

        } catch (\Exception $e) {
            $this->logger->error('Error getting recipients: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Count recipients based on filters
     *
     * @param array $filters
     * @return int
     */
    public function countRecipients(array $filters): int
    {
      $filters['customer_groups'] = implode(',', $filters['customer_groups']);

        try {
            $connection = $this->resourceConnection->getConnection();
            $recipients = $this->getRecipients($filters);
            return count($recipients);
        } catch (\Exception $e) {
            $this->logger->error('Error counting recipients: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * For debugging purposes
     *
     * @param array $filters
     * @return string
     */
    public function getDebugSql(array $filters): string
    {
        $select = $this->getRecipients($filters);
        return $select->__toString();
    }
}