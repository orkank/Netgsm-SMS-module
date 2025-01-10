<?php
declare(strict_types=1);

namespace IDangerous\Sms\Model;

use Magento\Framework\Model\AbstractModel;

class BulkSms extends AbstractModel
{
    protected function _construct()
    {
        $this->_init(\IDangerous\Sms\Model\ResourceModel\BulkSms::class);
    }
}