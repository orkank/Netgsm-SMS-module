<?php
declare(strict_types=1);

namespace IDangerous\Sms\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Framework\App\ResourceConnection;

class CheckDuplicates extends Command
{
    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    public function __construct(
        ResourceConnection $resourceConnection,
        string $name = null
    ) {
        $this->resourceConnection = $resourceConnection;
        parent::__construct($name);
    }

    protected function configure()
    {
        $this->setName('idangerous:sms:check-duplicates')
            ->setDescription('Check for duplicate phone numbers in bulk SMS jobs')
            ->addOption(
                'bulk-id',
                'b',
                InputOption::VALUE_OPTIONAL,
                'Check specific bulk SMS job ID'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $connection = $this->resourceConnection->getConnection();
        $detailTable = $this->resourceConnection->getTableName('idangerous_bulk_sms_detail');
        $bulkTable = $this->resourceConnection->getTableName('idangerous_bulk_sms');

        $bulkId = $input->getOption('bulk-id');

        if ($bulkId) {
            // Check specific bulk SMS job
            $output->writeln("<info>Checking bulk SMS job ID: $bulkId</info>");

            $duplicates = $connection->fetchAll(
                "SELECT phone, COUNT(*) as count, GROUP_CONCAT(entity_id) as detail_ids, GROUP_CONCAT(status) as statuses
                 FROM {$detailTable}
                 WHERE bulk_sms_id = ?
                 GROUP BY phone
                 HAVING count > 1
                 ORDER BY count DESC",
                [$bulkId]
            );

            if (empty($duplicates)) {
                $output->writeln("<info>No duplicate phone numbers found in bulk SMS job $bulkId</info>");
            } else {
                $output->writeln("<error>Found " . count($duplicates) . " phone numbers with duplicates:</error>");
                foreach ($duplicates as $duplicate) {
                    $output->writeln(sprintf(
                        "Phone: %s | Count: %d | Detail IDs: %s | Statuses: %s",
                        $duplicate['phone'],
                        $duplicate['count'],
                        $duplicate['detail_ids'],
                        $duplicate['statuses']
                    ));
                }
            }
        } else {
            // Check all bulk SMS jobs
            $output->writeln("<info>Checking all bulk SMS jobs for duplicates...</info>");

            $duplicates = $connection->fetchAll(
                "SELECT d.bulk_sms_id, b.created_at, d.phone, COUNT(*) as count,
                        GROUP_CONCAT(d.entity_id) as detail_ids, GROUP_CONCAT(d.status) as statuses
                 FROM {$detailTable} d
                 JOIN {$bulkTable} b ON d.bulk_sms_id = b.entity_id
                 GROUP BY d.bulk_sms_id, d.phone
                 HAVING count > 1
                 ORDER BY d.bulk_sms_id DESC, count DESC"
            );

            if (empty($duplicates)) {
                $output->writeln("<info>No duplicate phone numbers found in any bulk SMS jobs</info>");
            } else {
                $output->writeln("<error>Found " . count($duplicates) . " duplicate phone numbers across all jobs:</error>");
                $currentBulkId = null;
                foreach ($duplicates as $duplicate) {
                    if ($currentBulkId !== $duplicate['bulk_sms_id']) {
                        $currentBulkId = $duplicate['bulk_sms_id'];
                        $output->writeln("\n<comment>Bulk SMS ID: {$duplicate['bulk_sms_id']} (Created: {$duplicate['created_at']})</comment>");
                    }
                    $output->writeln(sprintf(
                        "  Phone: %s | Count: %d | Detail IDs: %s | Statuses: %s",
                        $duplicate['phone'],
                        $duplicate['count'],
                        $duplicate['detail_ids'],
                        $duplicate['statuses']
                    ));
                }
            }

            // Summary statistics
            $totalDetails = $connection->fetchOne("SELECT COUNT(*) FROM {$detailTable}");
            $totalBulkJobs = $connection->fetchOne("SELECT COUNT(*) FROM {$bulkTable}");
            $uniquePhones = $connection->fetchOne("SELECT COUNT(DISTINCT CONCAT(bulk_sms_id, '-', phone)) FROM {$detailTable}");

            $output->writeln("\n<info>Summary:</info>");
            $output->writeln("Total bulk SMS jobs: $totalBulkJobs");
            $output->writeln("Total detail records: $totalDetails");
            $output->writeln("Unique bulk_id-phone combinations: $uniquePhones");
            $output->writeln("Potential duplicates: " . ($totalDetails - $uniquePhones));
        }

        return Command::SUCCESS;
    }
}