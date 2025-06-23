<?php
declare(strict_types=1);

namespace IDangerous\Sms\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

class DetailStatus implements OptionSourceInterface
{
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_SUCCESS = 'success';
    const STATUS_ERROR = 'error';

    /**
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => self::STATUS_PENDING, 'label' => __('Pending')],
            ['value' => self::STATUS_PROCESSING, 'label' => __('Processing')],
            ['value' => self::STATUS_SUCCESS, 'label' => __('Success')],
            ['value' => self::STATUS_ERROR, 'label' => __('Error')]
        ];
    }
}