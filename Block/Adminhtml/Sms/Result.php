<?php
declare(strict_types=1);

namespace IDangerous\Sms\Block\Adminhtml\Sms;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Psr\Log\LoggerInterface;
use IDangerous\Sms\Model\SmsSession;

class Result extends Template
{
  private $smsSession;

  /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param Context $context
     * @param LoggerInterface $logger
     * @param array $data
     */
    public function __construct(
        Context $context,
        LoggerInterface $logger,
        SmsSession $smsSession,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->logger = $logger;
        $this->smsSession = $smsSession;
    }

    /**
     * Get SMS result data
     *
     * @return array|null
     */
    public function getSmsResult(): ?array
    {
        $result = $this->smsSession->getSmsResult();

        // Debug log
        $this->logger->debug('Retrieved SMS Result from session', [
            'hasResult' => !empty($result),
            'result' => $result
        ]);

        return $result;
    }

    /**
     * Clear session data after retrieving
     */
    protected function _afterToHtml($html)
    {
        $this->smsSession->unsSmsResult();
        return parent::_afterToHtml($html);
    }
}