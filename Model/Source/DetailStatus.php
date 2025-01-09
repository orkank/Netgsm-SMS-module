<?php
declare(strict_types=1);

namespace IDangerous\Sms\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

class DetailStatus implements OptionSourceInterface
{
    const STATUS_PENDING = 'pending';
    const STATUS_SENT = 'sent';
    const STATUS_FAILED = 'failed';

    /**
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => self::STATUS_PENDING, 'label' => __('Pending')],
            ['value' => self::STATUS_SENT, 'label' => __('Sent')],
            ['value' => self::STATUS_FAILED, 'label' => __('Failed')]
        ];
    }
}