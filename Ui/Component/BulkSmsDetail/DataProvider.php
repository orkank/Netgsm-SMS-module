<?php
declare(strict_types=1);

namespace IDangerous\Sms\Ui\Component\BulkSmsDetail;

use Magento\Framework\App\RequestInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;
use IDangerous\Sms\Model\ResourceModel\BulkSmsDetail\CollectionFactory;

class DataProvider extends AbstractDataProvider
{
    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param CollectionFactory $collectionFactory
     * @param RequestInterface $request
     * @param array $meta
     * @param array $data
     */
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        RequestInterface $request,
        array $meta = [],
        array $data = []
    ) {
        $this->request = $request;
        $collection = $collectionFactory->create();

        // Get bulk_id from URL
        $bulkId = $this->request->getParam('bulk_id');

        if ($bulkId) {
          $collection->addFieldToFilter('bulk_sms_id', $bulkId);
        }

        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
        $this->collection = $collection;
    }

    /**
     * @inheritDoc
     */
    public function addFilter(\Magento\Framework\Api\Filter $filter)
    {
        if ($filter->getField() === 'bulk_sms_id') {
            $this->collection->addFieldToFilter('bulk_sms_id', $filter->getValue());
        } else {
            parent::addFilter($filter);
        }
    }
}