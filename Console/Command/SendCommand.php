<?php
declare(strict_types=1);

namespace IDangerous\Sms\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use IDangerous\Sms\Api\SmsServiceInterface;

class SendCommand extends Command
{
    private const PHONE_OPTION = 'phone';
    private const MESSAGE_OPTION = 'message';

    /**
     * @var SmsServiceInterface
     */
    private $smsService;

    /**
     * @param SmsServiceInterface $smsService
     * @param string|null $name
     */
    public function __construct(
        SmsServiceInterface $smsService,
        string $name = null
    ) {
        parent::__construct($name);
        $this->smsService = $smsService;
    }

    /**
     * @inheritDoc
     */
    protected function configure()
    {
        $this->setName('idangerous:sms:send')
            ->setDescription('Send an SMS message')
            ->addOption(
                self::PHONE_OPTION,
                'p',
                InputOption::VALUE_REQUIRED,
                'Phone number to send SMS to'
            )
            ->addOption(
                self::MESSAGE_OPTION,
                'm',
                InputOption::VALUE_REQUIRED,
                'Message content'
            );

        parent::configure();
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $phone = $input->getOption(self::PHONE_OPTION);
        $message = $input->getOption(self::MESSAGE_OPTION);

        if (!$phone || !$message) {
            $output->writeln('<error>Phone number and message are required options.</error>');
            return Command::FAILURE;
        }

        $result = $this->smsService->sendSms($phone, $message);

        if ($result['success']) {
            $output->writeln('<info>SMS sent successfully!</info>');
            $output->writeln('<info>Response: ' . $result['response'] . '</info>');
            return Command::SUCCESS;
        } else {
            $output->writeln('<error>Failed to send SMS: ' . $result['message'] . '</error>');
            return Command::FAILURE;
        }
    }
}