<?php
declare(strict_types=1);

namespace IDangerous\Sms\Cron;

use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;

class CleanupExpiredDetails
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
     * @param ResourceConnection $resourceConnection
     * @param LoggerInterface $logger
     */
    public function __construct(
        ResourceConnection $resourceConnection,
        LoggerInterface $logger
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->logger = $logger;
    }

    /**
     * Execute cleanup
     *
     * @return void
     */
    public function execute()
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $tableName = $this->resourceConnection->getTableName('idangerous_bulk_sms_detail');

            $deletedRows = $connection->delete(
                $tableName,
                ['expires_at <= ?' => new \Zend_Db_Expr('NOW()')]
            );

            $this->logger->info(sprintf('Cleaned up %d expired SMS detail records', $deletedRows));
        } catch (\Exception $e) {
            $this->logger->error('Error cleaning up expired SMS details: ' . $e->getMessage());
        }
    }
}