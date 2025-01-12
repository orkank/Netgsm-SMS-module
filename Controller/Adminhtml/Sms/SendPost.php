<?php
declare(strict_types=1);

namespace IDangerous\Sms\Controller\Adminhtml\Sms;

use IDangerous\Sms\Model\SmsSession;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use IDangerous\Sms\Model\Api\SmsService;
use Magento\Framework\Controller\ResultFactory;

class SendPost extends Action
{
    /**
     * @var SmsSession
     */
    private $smsSession;

    /**
     * @var SmsService
     */
    private $smsService;

    /**
     * @var PageFactory
     */
    private $resultPageFactory;

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param SmsService $smsService
     * @param SmsSession $smsSession
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        SmsService $smsService,
        SmsSession $smsSession
    ) {
      parent::__construct($context);
      $this->resultPageFactory = $resultPageFactory;
      $this->smsService = $smsService;
      $this->smsSession = $smsSession;
    }

    /**
     * Execute action
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        try {
            $phone = $this->getRequest()->getParam('phone'); // Changed from 'phone' to 'phone_number'
            $message = $this->getRequest()->getParam('message');

            if (empty($phone) || empty($message)) {
              $this->messageManager->addErrorMessage(__('Phone number and message are required.'));
              return $resultRedirect->setPath('*/*/send');
            }

            $result = $this->smsService->sendSms($phone, $message);

            if (!$result['success']) {
                $this->messageManager->addErrorMessage($result['message'] ?? __('Failed to send SMS.'));
                return $resultRedirect->setPath('*/*/send');
            }

            $this->messageManager->addSuccessMessage(__('SMS sent successfully.'));
            return $resultRedirect->setPath('*/*/send');

        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage($e, __('An error occurred while sending SMS.'));
            return $resultRedirect->setPath('*/*/send');
        }
    }

    /**
     * @return bool
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('IDangerous_Sms::send_sms');
    }
}