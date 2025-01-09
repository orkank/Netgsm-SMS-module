<?php
declare(strict_types=1);

namespace IDangerous\Sms\Controller\Adminhtml\Bulk;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\App\Action\HttpPostActionInterface;
use IDangerous\Sms\Model\BulkRecipientService;

class GetRecipientCount extends Action implements HttpPostActionInterface
{
    const ADMIN_RESOURCE = 'IDangerous_Sms::bulk_sms';

    /**
     * @var JsonFactory
     */
    private $resultJsonFactory;

    /**
     * @var BulkRecipientService
     */
    private $recipientService;

    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        BulkRecipientService $recipientService
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->recipientService = $recipientService;
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        /** @var \Magento\Framework\Controller\Result\Json $resultJson */
        $resultJson = $this->resultJsonFactory->create();

        if (!$this->getRequest()->isPost()) {
            return $resultJson->setData([
                'success' => false,
                'message' => 'Invalid request method'
            ]);
        }

        try {
            $filters = [];
            $rawFilters = $this->getRequest()->getParam('filters', []);

            if (is_array($rawFilters)) {
                $customerType = $rawFilters['customer_type'] ?? 'all';

                $filters = [
                    'customer_type' => $customerType
                ];

                // Only add other filters if not guest type
                if ($customerType !== 'guest') {
                    $filters['customer_groups'] = !empty($rawFilters['customer_groups']) && is_array($rawFilters['customer_groups'])
                        ? implode(',', $rawFilters['customer_groups'])
                        : '';
                    $filters['order_period'] = $rawFilters['order_period'] ?? '';
                    $filters['min_purchase_count'] = (int)($rawFilters['min_purchase_count'] ?? 0);
                }
            }

            $this->_auth->getAuthStorage()->setIsFirstPageAfterLogin(false);

            $recipients = $this->recipientService->getRecipients($filters);

            return $resultJson->setData([
                'success' => true,
                'count' => count($recipients)
            ]);
        } catch (\Exception $e) {
            return $resultJson->setData([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * @inheritDoc
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed(self::ADMIN_RESOURCE);
    }
}