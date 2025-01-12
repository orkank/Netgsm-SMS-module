<?php
declare(strict_types=1);

namespace IDangerous\Sms\Controller\Adminhtml\Sms;

use IDangerous\Sms\Model\SmsSession;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use IDangerous\Sms\Model\Api\SmsService;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;

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
     * @return ResultInterface
     */
    public function execute()
    {
        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();

        try {
            $data = $this->getRequest()->getPostValue();

            if (empty($data['phone']) || empty($data['message'])) {
                throw new LocalizedException(__('Phone number and message are required.'));
            }

            // Check if this is an OTP SMS
            $isOtp = !empty($data['is_otp']);

            if ($isOtp) {
                $result = $this->smsService->sendOtpSms(
                    $data['phone'],
                    $data['message']
                );
            } else {
                $result = $this->smsService->sendSms(
                    $data['phone'],
                    $data['message']
                );
            }

            if (!$result['success']) {
                throw new LocalizedException(__($result['message']));
            }

            $this->messageManager->addSuccessMessage(
                __('SMS sent successfully') .
                ($isOtp ? ' ' . __('(OTP)') : '') .
                (isset($result['jobId']) ? ' JobID: ' . $result['jobId'] : '')
            );

            return $resultRedirect->setPath('*/*/send');

        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage($e, __('An error occurred while sending the SMS.'));
        }

        return $resultRedirect->setPath('*/*/send');
    }

    /**
     * @return bool
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('IDangerous_Sms::send_sms');
    }
}