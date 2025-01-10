<?php
declare(strict_types=1);

namespace IDangerous\Sms\Model\ResourceModel\BulkSms;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use IDangerous\Sms\Model\BulkSms;
use IDangerous\Sms\Model\ResourceModel\BulkSms as BulkSmsResource;

class Collection extends AbstractCollection
{
    protected function _construct()
    {
        $this->_init(BulkSms::class, BulkSmsResource::class);
    }
}