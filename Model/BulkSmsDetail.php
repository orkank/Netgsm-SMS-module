<?php
declare(strict_types=1);

namespace IDangerous\Sms\Model;

use Magento\Framework\Model\AbstractModel;

class BulkSmsDetail extends AbstractModel
{
    protected function _construct()
    {
        $this->_init(\IDangerous\Sms\Model\ResourceModel\BulkSmsDetail::class);
    }

    /**
     * Before save operations
     *
     * @return $this
     */
    protected function _beforeSave()
    {
        parent::_beforeSave();

        if ($this->isObjectNew() && !$this->getExpiresAt()) {
            $this->setExpiresAt(
                date('Y-m-d H:i:s', strtotime('+2 months'))
            );
        }

        return $this;
    }
}