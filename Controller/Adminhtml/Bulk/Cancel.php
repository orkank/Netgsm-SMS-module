<?php
declare(strict_types=1);

namespace IDangerous\Sms\Controller\Adminhtml\Bulk;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use IDangerous\Sms\Model\ResourceModel\BulkSms as BulkSmsResource;
use IDangerous\Sms\Model\BulkSmsFactory;

class Cancel extends Action
{
    const ADMIN_RESOURCE = 'IDangerous_Sms::bulk_sms';

    /**
     * @var BulkSmsResource
     */
    protected $bulkSmsResource;

    /**
     * @var BulkSmsFactory
     */
    protected $bulkSmsFactory;

    public function __construct(
        Context $context,
        BulkSmsResource $bulkSmsResource,
        BulkSmsFactory $bulkSmsFactory
    ) {
        parent::__construct($context);
        $this->bulkSmsResource = $bulkSmsResource;
        $this->bulkSmsFactory = $bulkSmsFactory;
    }

    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();

        try {
            $bulkId = (int)$this->getRequest()->getParam('bulk_id');
            if (!$bulkId) {
                throw new \Exception(__('Invalid bulk SMS ID'));
            }

            $bulkSms = $this->bulkSmsFactory->create();
            $this->bulkSmsResource->load($bulkSms, $bulkId);

            if (!$bulkSms->getId()) {
                throw new \Exception(__('Bulk SMS not found'));
            }

            if ($bulkSms->getStatus() !== 'pending' && $bulkSms->getStatus() !== 'processing') {
                throw new \Exception(__('Only pending or processing bulk SMS can be cancelled'));
            }

            $bulkSms->setStatus('cancelled');
            $this->bulkSmsResource->save($bulkSms);

            $this->messageManager->addSuccessMessage(__('Bulk SMS has been cancelled successfully.'));

        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $resultRedirect->setPath('*/*/dashboard');
    }
}