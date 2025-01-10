<?php
declare(strict_types=1);

namespace IDangerous\Sms\Controller\Adminhtml\Bulk;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use IDangerous\Sms\Model\SmsService;

class GetMessageStatus extends Action
{
    const ADMIN_RESOURCE = 'IDangerous_Sms::bulk_sms';

    /**
     * @var JsonFactory
     */
    private $resultJsonFactory;

    /**
     * @var SmsService
     */
    private $smsService;

    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        SmsService $smsService
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->smsService = $smsService;
    }

    public function execute()
    {
        $resultJson = $this->resultJsonFactory->create();
        try {
            $messageId = $this->getRequest()->getParam('message_id');
            if (!$messageId) {
                throw new \Exception(__('Message ID is required'));
            }

            $status = $this->smsService->getMessageStatus($messageId);
            return $resultJson->setData([
                'success' => true,
                'status' => $status
            ]);
        } catch (\Exception $e) {
            return $resultJson->setData([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
}