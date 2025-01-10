<?php
declare(strict_types=1);

namespace IDangerous\Sms\Controller\Adminhtml\Bulk;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use IDangerous\Sms\Model\BulkSmsRetryService;

class RetryFailed extends Action
{
    /**
     * @var BulkSmsRetryService
     */
    private $retryService;

    public function __construct(
        Context $context,
        BulkSmsRetryService $retryService
    ) {
        parent::__construct($context);
        $this->retryService = $retryService;
    }

    public function execute()
    {
        try {
            $bulkId = $this->getRequest()->getParam('id');
            $result = $this->retryService->retryFailed($bulkId ? (int)$bulkId : null);

            $this->messageManager->addSuccessMessage(__(
                'Retried %1 failed messages. Success: %2, Failed: %3',
                $result['total'],
                $result['success'],
                $result['failed']
            ));
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $this->_redirect('*/*/dashboard');
    }

    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('IDangerous_Sms::bulk_sms');
    }
}