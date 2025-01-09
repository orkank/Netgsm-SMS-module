<?php
declare(strict_types=1);

namespace IDangerous\Sms\Block\Adminhtml\Sms;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use IDangerous\Sms\Helper\Config;

class Send extends Template
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @param Context $context
     * @param Config $config
     * @param array $data
     */
    public function __construct(
        Context $context,
        Config $config,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->config = $config;
    }

    /**
     * Get form action URL
     *
     * @return string
     */
    public function getFormAction(): string
    {
        return $this->getUrl('*/*/sendPost');
    }

    /**
     * Check if module is enabled
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->config->isEnabled();
    }

    /**
     * Get message header
     *
     * @return string
     */
    public function getMsgHeader(): string
    {
        return $this->config->getMsgHeader();
    }
}