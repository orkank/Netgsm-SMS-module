<?php
declare(strict_types=1);

namespace IDangerous\Sms\Model\ResourceModel\BulkSmsDetail;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use IDangerous\Sms\Model\BulkSmsDetail as Model;
use IDangerous\Sms\Model\ResourceModel\BulkSmsDetail as ResourceModel;

class Collection extends AbstractCollection
{
    protected function _construct()
    {
        $this->_init(Model::class, ResourceModel::class);
    }
}