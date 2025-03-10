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
    private const XML_PATH_ENABLED = 'idangerous_sms/general/enabled';
    private const XML_PATH_MSG_HEADER = 'idangerous_sms/general/msgheader';
    private const XML_PATH_DEBUG_LOGGING = 'idangerous_sms/general/debug_logging';

    // Module-specific configuration paths
    private const XML_PATH_SMS_USERNAME = 'idangerous_sms/general/username';
    private const XML_PATH_SMS_PASSWORD = 'idangerous_sms/general/password';
    private const XML_PATH_SMS_BRAND_CODE = 'idangerous_sms/general/brand_code';
    private const XML_PATH_SMS_APP_KEY = 'idangerous_sms/general/app_key';

    // Fallback configuration paths from netgsm_iys module
    private const XML_PATH_USERNAME = 'netgsm_iys/general/username';
    private const XML_PATH_PASSWORD = 'netgsm_iys/general/password';
    private const XML_PATH_BRAND_CODE = 'netgsm_iys/general/brand_code';
    private const XML_PATH_APP_KEY = 'netgsm_iys/general/app_key';

    /**
     * @var EncryptorInterface
     */
    private $encryptor;

    /**
     * @param Context $context
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
     * Get app key - first check module-specific config, then fall back to IYS module
     *
     * @param int|null $storeId
     * @return string|null
     */
    public function getAppKey(?int $storeId = null): ?string
    {
        // First check module-specific configuration
        $appKey = (string)$this->scopeConfig->getValue(
            self::XML_PATH_SMS_APP_KEY,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        // If not set, fall back to netgsm_iys configuration
        if (empty($appKey)) {
            $appKey = (string)$this->scopeConfig->getValue(
                self::XML_PATH_APP_KEY,
                ScopeInterface::SCOPE_STORE,
                $storeId
            );
        }

        return $appKey;
    }

    /**
     * Get brand code - first check module-specific config, then fall back to IYS module
     *
     * @param int|null $storeId
     * @return string|null
     */
    public function getBrandCode(?int $storeId = null): ?string
    {
        // First check module-specific configuration
        $brandCode = (string)$this->scopeConfig->getValue(
            self::XML_PATH_SMS_BRAND_CODE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        // If not set, fall back to netgsm_iys configuration
        if (empty($brandCode)) {
            $brandCode = (string)$this->scopeConfig->getValue(
                self::XML_PATH_BRAND_CODE,
                ScopeInterface::SCOPE_STORE,
                $storeId
            );
        }

        return $brandCode;
    }

    /**
     * Get username - first check module-specific config, then fall back to IYS module
     *
     * @param int|null $storeId
     * @return string
     */
    public function getUsername(?int $storeId = null): string
    {
        // First check module-specific configuration
        $username = (string)$this->scopeConfig->getValue(
            self::XML_PATH_SMS_USERNAME,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        // If not set, fall back to netgsm_iys configuration
        if (empty($username)) {
            $username = (string)$this->scopeConfig->getValue(
                self::XML_PATH_USERNAME,
                ScopeInterface::SCOPE_STORE,
                $storeId
            );
        }

        return $username;
    }

    /**
     * Get password - first check module-specific config, then fall back to IYS module
     *
     * @param int|null $storeId
     * @return string
     */
    public function getPassword(?int $storeId = null): string
    {
        // First check module-specific configuration (plain text)
        $password = (string)$this->scopeConfig->getValue(
            self::XML_PATH_SMS_PASSWORD,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        // If not set, fall back to netgsm_iys configuration (may be encrypted)
        if (empty($password)) {
            $password = (string)$this->scopeConfig->getValue(
                self::XML_PATH_PASSWORD,
                ScopeInterface::SCOPE_STORE,
                $storeId
            );

            // If password from IYS module is encrypted, decrypt it
            if (!empty($password) && strpos($password, ':') !== false) {
                try {
                    $password = $this->encryptor->decrypt($password);
                } catch (\Exception $e) {
                    if ($this->isDebugLoggingEnabled()) {
                        $this->_logger->error('SMS Module - Failed to decrypt password: ' . $e->getMessage());
                    }
                }
            }
        }

        if ($this->isDebugLoggingEnabled()) {
            $this->_logger->debug('SMS Module - Password retrieval:', [
                'hasPassword' => !empty($password)
            ]);
        }

        if (empty($password)) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('NetGSM password is not configured. Please check your module configuration or IYS module configuration.')
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