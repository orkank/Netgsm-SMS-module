<?php
declare(strict_types=1);

namespace IDangerous\Sms\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\Encryption\EncryptorInterface;

class Config extends AbstractHelper
{
    private const XML_PATH_ENABLED = 'idangerous_sms/general/enabled';
    private const XML_PATH_MSG_HEADER = 'idangerous_sms/general/msgheader';
    private const XML_PATH_DEBUG_LOGGING = 'idangerous_sms/general/debug_logging';
    private const XML_PATH_USERNAME = 'idangerous_sms/general/username';
    private const XML_PATH_PASSWORD = 'idangerous_sms/general/password';
    private const XML_PATH_BRAND_CODE = 'idangerous_sms/general/brand_code';
    private const XML_PATH_APP_KEY = 'idangerous_sms/general/app_key';

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
        EncryptorInterface $encryptor
    ) {
        parent::__construct($context);
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
     * Get app key
     *
     * @param int|null $storeId
     * @return string|null
     */
    public function getAppKey(?int $storeId = null): ?string
    {
        return (string)$this->scopeConfig->getValue(
            self::XML_PATH_APP_KEY,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get brand code
     *
     * @param int|null $storeId
     * @return string|null
     */
    public function getBrandCode(?int $storeId = null): ?string
    {
        return (string)$this->scopeConfig->getValue(
            self::XML_PATH_BRAND_CODE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get username
     *
     * @param int|null $storeId
     * @return string
     */
    public function getUsername(?int $storeId = null): string
    {
        $username = (string)$this->scopeConfig->getValue(
            self::XML_PATH_USERNAME,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        if (empty($username)) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('NetGSM username is not configured. Please check your module configuration.')
            );
        }

        return $username;
    }

    /**
     * Get password
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

        // If password is encrypted, decrypt it
        if (!empty($password) && strpos($password, ':') !== false) {
            try {
                $password = $this->encryptor->decrypt($password);
            } catch (\Exception $e) {
                if ($this->isDebugLoggingEnabled()) {
                    $this->_logger->error('SMS Module - Failed to decrypt password: ' . $e->getMessage());
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
                __('NetGSM password is not configured. Please check your module configuration.')
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

    /**
     * Get config value
     *
     * @param string $path
     * @param string|null $storeId
     * @return mixed
     */
    private function getConfigValue($path, $storeId = null)
    {
        return $this->scopeConfig->getValue(
            $path,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Validate required configuration
     *
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function validateConfig(): bool
    {
        $this->getUsername();
        $this->getPassword();
        $this->getMsgHeader();
        return true;
    }
}