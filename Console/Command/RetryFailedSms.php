<?php
declare(strict_types=1);

namespace IDangerous\Sms\Console\Command;

use Magento\Framework\Console\Cli;
use IDangerous\Sms\Model\BulkSmsRetryService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RetryFailedSms extends Command
{
    private const BULK_ID_OPTION = 'bulk-id';

    /**
     * @var BulkSmsRetryService
     */
    private $retryService;

    public function __construct(BulkSmsRetryService $retryService)
    {
        $this->retryService = $retryService;
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('idangerous:sms:retry')
            ->setDescription('Retry failed SMS messages')
            ->addOption(
                self::BULK_ID_OPTION,
                null,
                InputOption::VALUE_REQUIRED,
                'Retry failed messages for specific bulk SMS ID'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $bulkId = $input->getOption(self::BULK_ID_OPTION);

        $result = $this->retryService->retryFailed((int)$bulkId);

        $output->writeln(sprintf(
            '<info>Retried %d failed messages. Success: %d, Failed: %d</info>',
            $result['total'],
            $result['success'],
            $result['failed']
        ));

        return Cli::RETURN_SUCCESS;
    }
}