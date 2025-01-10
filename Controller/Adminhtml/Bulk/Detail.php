<?php
declare(strict_types=1);

namespace IDangerous\Sms\Controller\Adminhtml\Bulk;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use IDangerous\Sms\Model\BulkSmsFactory;
use IDangerous\Sms\Model\ResourceModel\BulkSms as BulkSmsResource;

class Detail extends Action
{
    const ADMIN_RESOURCE = 'IDangerous_Sms::bulk_sms';

    /**
     * @var PageFactory
     */
    private $resultPageFactory;

    /**
     * @var BulkSmsFactory
     */
    private $bulkSmsFactory;

    /**
     * @var BulkSmsResource
     */
    private $bulkSmsResource;

    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        BulkSmsFactory $bulkSmsFactory,
        BulkSmsResource $bulkSmsResource
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
        $this->bulkSmsFactory = $bulkSmsFactory;
        $this->bulkSmsResource = $bulkSmsResource;
    }

    public function execute()
    {
        $bulkId = (int)$this->getRequest()->getParam('bulk_id');
        $bulkSms = $this->bulkSmsFactory->create();
        $this->bulkSmsResource->load($bulkSms, $bulkId);

        if (!$bulkSms->getId()) {
          $this->messageManager->addErrorMessage(__('Bulk SMS not found.'));
          return $this->_redirect('*/*/index');
        }

        $resultPage = $this->resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->prepend(__('Bulk SMS Details'));
        return $resultPage;
    }
}