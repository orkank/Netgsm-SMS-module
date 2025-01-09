<?php
declare(strict_types=1);

namespace IDangerous\Sms\Model;

use IDangerous\Sms\Model\ResourceModel\BulkSmsDetail\CollectionFactory;
use IDangerous\Sms\Model\ResourceModel\BulkSms as BulkSmsResource;
use IDangerous\Sms\Model\BulkSmsFactory;
use Psr\Log\LoggerInterface;

class BulkSmsRetryService
{
    /**
     * @var CollectionFactory
     */
    private $detailCollectionFactory;

    /**
     * @var SmsService
     */
    private $smsService;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var BulkSmsFactory
     */
    private $bulkSmsFactory;

    /**
     * @var BulkSmsResource
     */
    private $bulkSmsResource;

    public function __construct(
        CollectionFactory $detailCollectionFactory,
        SmsService $smsService,
        LoggerInterface $logger,
        BulkSmsFactory $bulkSmsFactory,
        BulkSmsResource $bulkSmsResource
    ) {
        $this->detailCollectionFactory = $detailCollectionFactory;
        $this->smsService = $smsService;
        $this->logger = $logger;
        $this->bulkSmsFactory = $bulkSmsFactory;
        $this->bulkSmsResource = $bulkSmsResource;
    }

    public function retryFailed(int $bulkSmsId): array
    {
        // Load the bulk SMS record
        $bulkSms = $this->bulkSmsFactory->create();
        $this->bulkSmsResource->load($bulkSms, $bulkSmsId);

        if (!$bulkSms->getId()) {
            $this->logger->error(sprintf('Bulk SMS with ID %d not found', $bulkSmsId));
            return [
                'total' => 0,
                'success' => 0,
                'failed' => 0
            ];
        }

        // Get all failed messages for this bulk SMS
        $collection = $this->detailCollectionFactory->create();
        $collection->addFieldToFilter('bulk_sms_id', $bulkSmsId)
            ->addFieldToFilter('status', 'error');

        $total = $collection->getSize();
        $success = 0;
        $failed = 0;

        $this->logger->info(sprintf(
            'Found %d failed messages for Bulk SMS ID %d',
            $total,
            $bulkSmsId
        ));

        if ($total === 0) {
            return [
                'total' => 0,
                'success' => 0,
                'failed' => 0
            ];
        }

        foreach ($collection as $detail) {
            try {
                $this->logger->info(sprintf(
                    'Retrying SMS for Detail ID: %d, Phone: %s',
                    $detail->getId(),
                    $detail->getPhone()
                ));

                $result = $this->smsService->send(
                    $detail->getPhone(),
                    $bulkSms->getMessage()
                );

                $detail->setStatus('success')
                    ->setMessageId($result['message_id'])
                    ->setErrorMessage(null)
                    ->save();

                $success++;

                // Update bulk SMS counters
                $bulkSms->setSuccessCount($bulkSms->getSuccessCount() + 1)
                    ->setErrorCount($bulkSms->getErrorCount() - 1);

            } catch (\Exception $e) {
                $detail->setErrorMessage($e->getMessage())
                    ->save();
                $failed++;

                $this->logger->error(sprintf(
                    'Retry failed for Detail ID %d: %s',
                    $detail->getId(),
                    $e->getMessage()
                ));
            }
        }

        // Update bulk SMS status if all messages are now successful
        if ($bulkSms->getErrorCount() === 0) {
            $bulkSms->setStatus('completed');
        }

        $bulkSms->save();

        $this->logger->info(sprintf(
            'Retry process completed for Bulk SMS ID %d. Total: %d, Success: %d, Failed: %d',
            $bulkSmsId,
            $total,
            $success,
            $failed
        ));

        return [
            'total' => $total,
            'success' => $success,
            'failed' => $failed
        ];
    }
}