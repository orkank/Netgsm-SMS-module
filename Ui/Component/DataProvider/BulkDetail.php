<?php
declare(strict_types=1);

namespace IDangerous\Sms\Ui\Component\DataProvider;

use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\SearchCriteriaBuilder;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Element\UiComponent\DataProvider\DataProvider;
use Magento\Framework\View\Element\UiComponent\DataProvider\Reporting;
use IDangerous\Sms\Model\ResourceModel\BulkSmsDetail\CollectionFactory;

class BulkDetail extends DataProvider
{
    /**
     * @var CollectionFactory
     */
    private $collectionFactory;

    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param Reporting $reporting
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param RequestInterface $request
     * @param FilterBuilder $filterBuilder
     * @param CollectionFactory $collectionFactory
     * @param array $meta
     * @param array $data
     */
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        Reporting $reporting,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        RequestInterface $request,
        FilterBuilder $filterBuilder,
        CollectionFactory $collectionFactory,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct(
            $name,
            $primaryFieldName,
            $requestFieldName,
            $reporting,
            $searchCriteriaBuilder,
            $request,
            $filterBuilder,
            $meta,
            $data
        );
        $this->collectionFactory = $collectionFactory;
        $this->request = $request;
    }

    /**
     * Get data
     *
     * @return array
     */
    public function getData()
    {
        $collection = $this->collectionFactory->create();

        // Add bulk_id filter
        $bulkId = $this->request->getParam('bulk_id');

        if ($bulkId) {
          $collection->addFieldToFilter('bulk_sms_id', $bulkId);
        } else {
          // redirect to dashboard
          $this->messageManager->addErrorMessage(__('Bulk ID is required'));
          $this->response->setRedirect($this->urlBuilder->getUrl('*/idangerous_sms/bulk/dashboard'));
        }

        // Get current page and page size from UI parameters
        $paging = $this->request->getParam('paging');
        $curPage = $paging['current'] ?? 1;
        $pageSize = $paging['pageSize'] ?? 20;

        // Set pagination
        $collection->setPageSize($pageSize);
        $collection->setCurPage($curPage);

        // Load the collection
        $collection->load();

        return [
            'totalRecords' => $collection->getSize(),
            'items' => $collection->toArray()['items'] ?? []
        ];
    }
}