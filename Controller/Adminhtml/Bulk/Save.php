<?php
declare(strict_types=1);

namespace IDangerous\Sms\Controller\Adminhtml\Bulk;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use IDangerous\Sms\Model\BulkSmsFactory;
use IDangerous\Sms\Model\BulkRecipientService;
use Magento\Framework\Serialize\Serializer\Json;

class Save extends Action
{
    /**
     * @var BulkSmsFactory
     */
    private $bulkSmsFactory;

    /**
     * @var BulkRecipientService
     */
    private $recipientService;

    /**
     * @var Json
     */
    private $json;

    /**
     * @param Context $context
     * @param BulkSmsFactory $bulkSmsFactory
     * @param BulkRecipientService $recipientService
     * @param Json $json
     */
    public function __construct(
        Context $context,
        BulkSmsFactory $bulkSmsFactory,
        BulkRecipientService $recipientService,
        Json $json
    ) {
        parent::__construct($context);
        $this->bulkSmsFactory = $bulkSmsFactory;
        $this->recipientService = $recipientService;
        $this->json = $json;
    }

    /**
     * Save bulk SMS job
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();

        try {
            $data = $this->getRequest()->getPostValue();

            if (empty($data['message'])) {
                throw new \InvalidArgumentException(__('Message is required.')->render());
            }

            // Prepare filters
            $filters = [
                'customer_groups' => $data['customer_groups'] ?? [],
                'customer_type' => $data['customer_type'] ?? 'all',
                'order_period' => $data['order_period'] ?? null,
                'min_purchase_count' => $data['min_purchase_count'] ?? 0
            ];

            // Count recipients
            $totalRecipients = $this->recipientService->countRecipients($filters);

            if ($totalRecipients === 0) {
                throw new \InvalidArgumentException(__('No recipients found matching the selected criteria.')->render());
            }

            // Create bulk SMS job
            $bulkSms = $this->bulkSmsFactory->create();
            $bulkSms->setData([
                'message' => $data['message'],
                'status' => 'pending',
                'filters' => $this->json->serialize($filters),
                'total_recipients' => $totalRecipients,
                'processed_count' => 0,
                'success_count' => 0,
                'error_count' => 0
            ]);

            $bulkSms->save();

            $this->messageManager->addSuccessMessage(
                __('Bulk SMS job has been created for %1 recipients.', $totalRecipients)
            );

            return $resultRedirect->setPath('*/*/index');

        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            return $resultRedirect->setPath('*/*/index');
        }
    }

    /**
     * @return bool
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('IDangerous_Sms::bulk_sms');
    }
}