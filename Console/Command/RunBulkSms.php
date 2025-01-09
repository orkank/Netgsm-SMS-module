<?php
declare(strict_types=1);

namespace IDangerous\Sms\Console\Command;

use Magento\Framework\Console\Cli;
use IDangerous\Sms\Cron\ProcessBulkSms;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RunBulkSms extends Command
{
    /**
     * @var ProcessBulkSms
     */
    private $processBulkSms;

    public function __construct(ProcessBulkSms $processBulkSms)
    {
        $this->processBulkSms = $processBulkSms;
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('idangerous:sms:run')
            ->setDescription('Run the bulk SMS processing cron job');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $output->writeln('<info>Starting bulk SMS processing...</info>');

            $result = $this->processBulkSms->execute();

            if (empty($result)) {
                $output->writeln('<comment>No pending bulk SMS jobs found.</comment>');
                return Cli::RETURN_SUCCESS;
            }

            foreach ($result as $jobResult) {
                $output->writeln(sprintf(
                    '<info>Bulk SMS ID: %d</info>',
                    $jobResult['bulk_id']
                ));
                $output->writeln(sprintf(
                    'Total Recipients: %d, Processed: %d, Success: %d, Failed: %d',
                    $jobResult['total_recipients'],
                    $jobResult['processed'],
                    $jobResult['success'],
                    $jobResult['failed']
                ));

                if (!empty($jobResult['errors'])) {
                    $output->writeln('<error>Errors:</error>');
                    foreach ($jobResult['errors'] as $error) {
                        $output->writeln(sprintf(
                            ' - Phone: %s, Error: %s',
                            $error['phone'],
                            $error['message']
                        ));
                    }
                }
            }

            return Cli::RETURN_SUCCESS;
        } catch (\Exception $e) {
            $output->writeln(sprintf(
                '<error>Error: %s</error>',
                $e->getMessage()
            ));
            return Cli::RETURN_FAILURE;
        }
    }
}