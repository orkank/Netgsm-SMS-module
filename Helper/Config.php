<?php
declare(strict_types=1);

namespace IDangerous\Sms\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\ScopeInterface;
// use IDangerous\NetgsmIYS\Helper\Config as IysConfig;
use Magento\Framework\Encryption\EncryptorInterface;

class Config extends AbstractHelper
{
    // private const XML_PATH_ENABLED = 'idangerous_sms/general/enabled';
    private const XML_PATH_MSG_HEADER = 'idangerous_sms/general/msgheader';
    // private const XML_PATH_DEBUG_LOGGING = 'idangerous_sms/general/debug_logging';
    private const XML_PATH_USERNAME = 'netgsm_iys/general/username';
    private const XML_PATH_PASSWORD = 'netgsm_iys/general/password';
    private const XML_PATH_BRAND_CODE = 'netgsm_iys/general/brand_code';
    private const XML_PATH_APP_KEY = 'netgsm_iys/general/app_key';
    private const XML_PATH_ENABLED = true;

    /**
     * @var EncryptorInterface
     */
    private $encryptor;

    /**
     * @param Context $context
    //  * @param IysConfig $iysConfig
     * @param EncryptorInterface $encryptor
     */
    public function __construct(
        Context $context,
        // IysConfig $iysConfig,
        EncryptorInterface $encryptor
    ) {
        parent::__construct($context);
        // $this->iysConfig = $iysConfig;
        $this->encryptor = $encryptor;
    }

    /**
     * Check if module is enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isEnabled(?int $storeId = null): bool
    {
        return (bool)$this->scopeConfig->getValue(
            self::XML_PATH_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get message header
     *
     * @param int|null $storeId
     * @return string
     */
    public function getMsgHeader(?int $storeId = null): string
    {
        return (string)$this->scopeConfig->getValue(
            self::XML_PATH_MSG_HEADER,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get username from IYS module
     *
     * @param int|null $storeId
     * @return string
     */
    public function getUsername(?int $storeId = null): string
    {
        return (string)$this->scopeConfig->getValue(
            self::XML_PATH_USERNAME,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get password from IYS module
     *
     * @param int|null $storeId
     * @return string
     */
    public function getPassword(?int $storeId = null): string
    {
        $password = (string)$this->scopeConfig->getValue(
            self::XML_PATH_PASSWORD,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        if ($this->isDebugLoggingEnabled()) {
            $this->_logger->debug('SMS Module - Password retrieval:', [
                'hasPassword' => !empty($password)
            ]);
        }

        if (empty($password)) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('NetGSM password is not configured. Please check your IYS module configuration.')
            );
        }

        return $password;
    }

    /**
     * Check if debug logging is enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isDebugLoggingEnabled(?int $storeId = null): bool
    {
        return (bool)$this->scopeConfig->getValue(
            self::XML_PATH_DEBUG_LOGGING,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
}