<?php
declare(strict_types=1);

namespace IDangerous\Sms\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class BulkSmsDetail extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('idangerous_bulk_sms_detail', 'entity_id');
    }
}