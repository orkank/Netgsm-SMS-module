<?php
declare(strict_types=1);

namespace IDangerous\Sms\Cron;

use Psr\Log\LoggerInterface;
use IDangerous\Sms\Model\ResourceModel\BulkSms\CollectionFactory;
use IDangerous\Sms\Model\BulkRecipientService;
use Magento\Framework\Serialize\Serializer\Json;
use IDangerous\Sms\Model\Source\Status;
use IDangerous\Sms\Model\SmsService;
use IDangerous\Sms\Model\BulkSmsDetailFactory;
use IDangerous\Sms\Model\ResourceModel\BulkSmsDetail as BulkSmsDetailResource;
use Magento\Framework\Lock\LockManagerInterface;

class ProcessBulkSms
{
    private const LOCK_NAME = 'idangerous_sms_bulk_processing';
    private const LOCK_TIMEOUT = 7200; // 2 hours
    private const LOCK_REFRESH_INTERVAL = 300; // 5 minutes in seconds
    private $lastLockRefresh;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var CollectionFactory
     */
    private $bulkSmsCollectionFactory;

    /**
     * @var BulkRecipientService
     */
    private $recipientService;

    /**
     * @var Json
     */
    private $json;

    /**
     * @var SmsService
     */
    private $smsService;

    /**
     * @var BulkSmsDetailFactory
     */
    private $bulkSmsDetailFactory;

    /**
     * @var BulkSmsDetailResource
     */
    private $bulkSmsDetailResource;

    /**
     * @var LockManagerInterface
     */
    private $lockManager;

    /**
     * @param LoggerInterface $logger
     * @param CollectionFactory $bulkSmsCollectionFactory
     * @param BulkRecipientService $recipientService
     * @param Json $json
     * @param SmsService $smsService
     * @param BulkSmsDetailFactory $bulkSmsDetailFactory
     * @param BulkSmsDetailResource $bulkSmsDetailResource
     * @param LockManagerInterface $lockManager
     */
    public function __construct(
        LoggerInterface $logger,
        CollectionFactory $bulkSmsCollectionFactory,
        BulkRecipientService $recipientService,
        Json $json,
        SmsService $smsService,
        BulkSmsDetailFactory $bulkSmsDetailFactory,
        BulkSmsDetailResource $bulkSmsDetailResource,
        LockManagerInterface $lockManager
    ) {
        $this->logger = $logger;
        $this->bulkSmsCollectionFactory = $bulkSmsCollectionFactory;
        $this->recipientService = $recipientService;
        $this->json = $json;
        $this->smsService = $smsService;
        $this->bulkSmsDetailFactory = $bulkSmsDetailFactory;
        $this->bulkSmsDetailResource = $bulkSmsDetailResource;
        $this->lockManager = $lockManager;
    }

    /**
     * Execute cron job
     *
     * @return void
     */
    public function execute()
    {
        // Try to acquire lock
        if (!$this->lockManager->lock(self::LOCK_NAME, self::LOCK_TIMEOUT)) {
            $this->logger->info('Bulk SMS processing is already running. Skipping this execution.');
            return;
        }

        $this->lastLockRefresh = time();

        try {
            $this->logger->info('Starting bulk SMS processing...');

            // Get pending bulk SMS jobs
            $collection = $this->bulkSmsCollectionFactory->create();
            $collection->addFieldToFilter('status', ['in' => [Status::STATUS_PENDING, Status::STATUS_PROCESSING]]);

            foreach ($collection as $bulkSms) {
                try {
                    $this->processBulkSms($bulkSms);

                    // Refresh lock if needed
                    $this->refreshLockIfNeeded();

                } catch (\Exception $e) {
                    $this->logger->error('Error processing bulk SMS ID: ' . $bulkSms->getId() . ' - ' . $e->getMessage());

                    // Update job status to failed
                    $bulkSms->setStatus(Status::STATUS_FAILED);
                    $bulkSms->save();
                }
            }

            $this->logger->info('Bulk SMS processing completed.');
        } catch (\Exception $e) {
            $this->logger->error('Error in bulk SMS cron: ' . $e->getMessage());
        } finally {
            // Always release the lock when done
            $this->lockManager->unlock(self::LOCK_NAME);
        }
    }

    /**
     * Refresh lock if needed
     *
     * @return void
     */
    private function refreshLockIfNeeded()
    {
        $currentTime = time();

        if (($currentTime - $this->lastLockRefresh) >= self::LOCK_REFRESH_INTERVAL) {
            $this->logger->info('Refreshing bulk SMS processing lock...');

            // Release and re-acquire the lock
            $this->lockManager->unlock(self::LOCK_NAME);

            if (!$this->lockManager->lock(self::LOCK_NAME, self::LOCK_TIMEOUT)) {
                throw new \RuntimeException('Failed to refresh bulk SMS processing lock.');
            }

            $this->lastLockRefresh = $currentTime;
            $this->logger->info('Bulk SMS processing lock refreshed successfully.');
        }
    }

    /**
     * Process individual bulk SMS job
     *
     * @param \IDangerous\Sms\Model\BulkSms $bulkSms
     * @return void
     */
    private function processBulkSms($bulkSms)
    {
        // Update status to processing
        if ($bulkSms->getStatus() === Status::STATUS_PENDING) {
            $bulkSms->setStatus(Status::STATUS_PROCESSING);
            $bulkSms->save();
        }

        // Get recipients
        $filters = $this->json->unserialize($bulkSms->getFilters());
        $filters['customer_groups'] = implode(',', $filters['customer_groups']);
        $recipients = $this->recipientService->getRecipients($filters);

        $successCount = 0;
        $errorCount = 0;
        $processedCount = 0;
        $processedPhones = []; // Track processed phone numbers to prevent duplicates

        foreach ($recipients as $recipient) {
            try {
                // Skip if phone number was already processed in this batch
                $phone = $recipient['subscriber_phone'];
                if (in_array($phone, $processedPhones)) {
                    $this->logger->info(sprintf(
                        'Skipping duplicate phone number: %s',
                        $phone
                    ));
                    continue;
                }
                $processedPhones[] = $phone;

                // Refresh lock if needed during recipient processing
                $this->refreshLockIfNeeded();

                // Check if SMS was already sent to this phone number in this bulk SMS job
                $connection = $this->bulkSmsDetailResource->getConnection();
                $existingRecord = $connection->fetchRow(
                    $connection->select()
                        ->from($this->bulkSmsDetailResource->getMainTable())
                        ->where('bulk_sms_id = ?', $bulkSms->getId())
                        ->where('phone = ?', $recipient['subscriber_phone'])
                        ->where('status IN (?)', ['success', 'pending', 'processing'])
                        ->limit(1)
                );

                if ($existingRecord) {
                    $this->logger->info(sprintf(
                        'SMS already sent or being processed for phone %s in bulk SMS %d (status: %s), skipping',
                        $recipient['subscriber_phone'],
                        $bulkSms->getId(),
                        $existingRecord['status']
                    ));
                    continue;
                }

                // Create detail record first
                $detail = $this->bulkSmsDetailFactory->create();

                $detail->setData([
                    'bulk_sms_id' => $bulkSms->getId(),
                    'phone' => $recipient['subscriber_phone'],
                    'status' => 'pending'
                ]);

                $this->bulkSmsDetailResource->save($detail);

                $this->logger->info(sprintf(
                    'Sending SMS to %s',
                    $recipient['subscriber_phone']
                ));

                $recipient = array_merge($recipient, [
                  'firstname' => '',
                  'lastname' => '',
                  'email' => '',
                  'dob' => '',
                  'gender' => ''
                ]);

                $customerData = [
                  'firstname' => $recipient['firstname'],
                  'lastname' => $recipient['lastname'],
                  'email' => $recipient['email'],
                  'dob' => $recipient['dob'] ?? '',
                  'gender' => $recipient['gender'] ?? '',
                  'telephone' => $recipient['subscriber_phone']
                ];


                $result = $this->smsService->send(
                    $recipient['subscriber_phone'],
                    $bulkSms->getMessage(),
                    $customerData
                );

                echo sprintf(
                  'Sending SMS to %s',
                  $recipient['subscriber_phone']
                ) . PHP_EOL;

                // Update detail record with success
                $detail->setStatus('success');
                $detail->setMessageId($result['message_id']);
                $this->bulkSmsDetailResource->save($detail);

                $successCount++;
            } catch (\Exception $e) {
                $errorCount++;
                $errors[] = [
                    'phone' => $recipient['subscriber_phone'],
                    'message' => $e->getMessage()
                ];

                if ($detail && $detail->getId()) {
                    $detail->setStatus('error');
                    $detail->setErrorMessage($e->getMessage());
                    $this->bulkSmsDetailResource->save($detail);
                }

                $error = sprintf(
                    'Failed to send SMS to %s: %s',
                    $recipient['subscriber_phone'],
                    $e->getMessage()
                );

                echo $error . PHP_EOL;

                $this->logger->error($error);
            }

            $processedCount++;

            // Update progress periodically
            // if ($processedCount % 100 === 0) {
            $this->updateProgress($bulkSms, $processedCount, $successCount, $errorCount);
            // }
        }

        // Final update
        $this->updateProgress($bulkSms, $processedCount, $successCount, $errorCount);

        // Mark as completed
        $bulkSms->setStatus(Status::STATUS_COMPLETED);
        $bulkSms->save();
    }

    /**
     * Update job progress
     *
     * @param \IDangerous\Sms\Model\BulkSms $bulkSms
     * @param int $processedCount
     * @param int $successCount
     * @param int $errorCount
     * @return void
     */
    private function updateProgress($bulkSms, $processedCount, $successCount, $errorCount)
    {
        $bulkSms->setProcessedCount($processedCount);
        $bulkSms->setSuccessCount($successCount);
        $bulkSms->setErrorCount($errorCount);
        $bulkSms->save();
    }
}