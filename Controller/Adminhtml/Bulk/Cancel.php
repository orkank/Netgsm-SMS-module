<?php
declare(strict_types=1);

namespace IDangerous\Sms\Controller\Adminhtml\Bulk;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use IDangerous\Sms\Model\ResourceModel\BulkSms as BulkSmsResource;
use IDangerous\Sms\Model\BulkSmsFactory;

class Cancel extends Action
{
    const ADMIN_RESOURCE = 'IDangerous_Sms::bulk_sms';

    /**
     * @var JsonFactory
     */
    private $resultJsonFactory;

    /**
     * @var BulkSmsResource
     */
    private $bulkSmsResource;

    /**
     * @var BulkSmsFactory
     */
    private $bulkSmsFactory;

    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        BulkSmsResource $bulkSmsResource,
        BulkSmsFactory $bulkSmsFactory
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->bulkSmsResource = $bulkSmsResource;
        $this->bulkSmsFactory = $bulkSmsFactory;
    }

    public function execute()
    {
        $resultJson = $this->resultJsonFactory->create();

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

            if ($bulkSms->getStatus() !== 'pending') {
                throw new \Exception(__('Only pending bulk SMS can be cancelled'));
            }

            $bulkSms->setStatus('cancelled');
            $this->bulkSmsResource->save($bulkSms);

            return $resultJson->setData([
                'success' => true,
                'message' => __('Bulk SMS has been cancelled.')
            ]);
        } catch (\Exception $e) {
            return $resultJson->setData([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
}