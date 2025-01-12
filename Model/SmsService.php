<?php
declare(strict_types=1);

namespace IDangerous\Sms\Model;

use IDangerous\Sms\Api\SmsServiceInterface;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use IDangerous\NetgsmIYS\Helper\Config as IysConfig;
use IDangerous\Sms\Helper\Config;
use Magento\Framework\Exception\LocalizedException;

class SmsService implements SmsServiceInterface
{
    /**
     * @var Config
     */
    private $config;

    private const API_URL = 'https://api.netgsm.com.tr/sms/send/get';
    // private const XML_PATH_USERNAME = 'idangerousiys/general/username';
    // private const XML_PATH_PASSWORD = 'idangerousiys/general/password';
    // private const XML_PATH_SENDER = 'idangerousiys/sms/general/msgheader';

    /**
     * @var Curl
     */
    private $curl;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var IysConfig
     */
    private $iysConfig;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    public function __construct(
        Curl $curl,
        ScopeConfigInterface $scopeConfig,
        LoggerInterface $logger,
        StoreManagerInterface $storeManager,
        Config $config,
        IysConfig $iysConfig
    ) {
        $this->curl = $curl;
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;
        $this->storeManager = $storeManager;
        $this->config = $config;
        $this->iysConfig = $iysConfig;
    }

    /**
     * Replace customer variables in message
     *
     * @param string $message
     * @param array $customerData
     * @return string
     */
    private function replaceCustomerVariables(string $message, array $customerData): string
    {
        $variables = [
            '{{customer_name}}' => ($customerData['firstname'] ?? '') . ' ' . ($customerData['lastname'] ?? ''),
            '{{customer_firstname}}' => $customerData['firstname'] ?? '',
            '{{customer_lastname}}' => $customerData['lastname'] ?? '',
            '{{customer_email}}' => $customerData['email'] ?? '',
            '{{customer_dob}}' => $customerData['dob'] ?? '',
            '{{customer_gender}}' => $customerData['gender'] ?? '',
            '{{customer_phone}}' => $customerData['telephone'] ?? ''
        ];

        return str_replace(
            array_keys($variables),
            array_values($variables),
            $message
        );
    }

    /**
     * Send SMS with customer variables
     *
     * @param string $phone
     * @param string $message
     * @param array $customerData
     * @return array
     */
    public function sendSms(string $phone, string $message, array $customerData = []): array
    {
        // Replace variables if customer data is provided
        if (!empty($customerData)) {
            $message = $this->replaceCustomerVariables($message, $customerData);
        }

        try {
            $username = $this->iysConfig->getUsername();
            $password = $this->iysConfig->getPassword();
            $brandCode = $this->iysConfig->getBrandCode();

            if (empty($username) || empty($password)) {
                throw new LocalizedException(__('IDangerous credentials are not configured.'));
            }

            // Clean phone number
            $phone = preg_replace('/[^0-9]/', '', $phone);

            // Add country code if not present
            if (substr($phone, 0, 2) !== '90') {
                $phone = '90' . ltrim($phone, '0');
            }

            // Prepare API parameters
            $params = [
                'usercode' => $username,
                'password' => $password,
                'msgheader' => $brandCode,
                'gsmno' => $phone,
                'message' => $message,
                'dil' => 'TR'
            ];
            // Set curl options
            $this->curl->setOptions([
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false
            ]);

            // Make API request
            $this->curl->post('https://api.netgsm.com.tr/sms/send/get', $params);

            $response = $this->curl->getBody();

            // Log the response for debugging
            $this->logger->debug('IDangerous API Response', ['response' => $response]);

            // Parse response
            $responseCode = explode(' ', $response)[0];

            // Check response codes
            switch ($responseCode) {
                case '00':
                case '01':
                case '02':
                    return [
                        'success' => true,
                        'message' => __('SMS sent successfully'),
                        'response' => $response
                    ];

                case '20':
                    return [
                        'success' => false,
                        'message' => __('Invalid message content'),
                        'response' => $response
                    ];
                case '30':
                    return [
                        'success' => false,
                        'message' => __('Invalid username/password'),
                        'response' => $response
                    ];
                case '40':
                    return [
                        'success' => false,
                        'message' => __('Insufficient balance'),
                        'response' => $response
                    ];
                case '70':
                    return [
                        'success' => false,
                        'message' => __('Invalid parameter error'),
                        'response' => $response
                    ];
                default:
                    return [
                        'success' => false,
                        'message' => __('Unknown error: %1', $response),
                        'response' => $response
                    ];
            }

        } catch (\Exception $e) {
            $this->logger->error('SMS sending failed: ' . $e->getMessage(), [
                'phone' => $phone,
                'exception' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'response' => null
            ];
        }
    }

    public function send(string $phone, string $message, array $customerData = []): array
    {
        // Replace variables if customer data is provided
        if (!empty($customerData)) {
          $message = $this->replaceCustomerVariables($message, $customerData);
        }

        $sender = $this->config->getMsgHeader();

        $username = $this->iysConfig->getUsername();
        $password = $this->iysConfig->getPassword();
        $sender = $this->config->getMsgHeader();

        try {
            $storeId = $this->storeManager->getStore()->getId();

            if (empty($username) || empty($password)) {
                $this->logger->error('IDangerous credentials not found', [
                    'username_path' => self::XML_PATH_USERNAME,
                    'password_path' => self::XML_PATH_PASSWORD,
                    'store_id' => $storeId
                ]);

                throw new \Exception('API credentials not configured');
            }

            $params = [
              'usercode' => $username,
              'password' => $password,
              'gsmno' => $phone,
              'message' => $message,
              'msgheader' => $sender,
              'dil' => 'TR'
            ];

            $this->logger->info('API Parameters:', [
              'username' => $username,
              'sender' => $sender,
              'phone' => $phone,
              'store_id' => $storeId
            ]);

            $url = self::API_URL . '?' . http_build_query($params);

            $this->curl->get($url);
            $response = $this->curl->getBody();

            $this->logger->info('IDangerous API response', ['response' => $response]);

            // Parse response
            $responseData = explode(' ', $response);
            $code = $responseData[0] ?? '';

            if ($code === '00') {
                $messageId = $responseData[1] ?? '';
                return [
                    'success' => true,
                    'message_id' => $messageId
                ];
            }

            $errorMessage = $this->getErrorMessage($code);
            throw new \Exception($errorMessage);
        } catch (\Exception $e) {
            $this->logger->error('SMS sending failed: ' . $e->getMessage());
            throw $e;
        }
    }

    private function getErrorMessage(string $code): string
    {
        $errors = [
            '20' => 'Post error. Message text is empty',
            '30' => 'Authentication error',
            '40' => 'System error',
            '50' => 'Incorrect number of parameters',
            '51' => 'Parameter format error',
            '70' => 'Invalid sender',
            '85' => 'Invalid message type',
            '100' => 'System error',
            '101' => 'System error'
        ];

        return $errors[$code] ?? 'Unknown error';
    }

    public function getMessageStatus(string $messageId): array
    {
        try {
            $username = $this->iysConfig->getUsername();
            $password = $this->iysConfig->getPassword();

            if (empty($username) || empty($password)) {
                throw new \Exception('API credentials not configured');
            }

            $params = [
                'usercode' => $username,
                'password' => $password,
                'bulkid' => $messageId,
                'type' => '0',
                'version' => '2'
            ];

            $url = 'https://api.netgsm.com.tr/sms/report/?' . http_build_query($params);

            $this->curl->post($url, $params);
            $response = $this->curl->getBody();

            $this->logger->info('Status API response', ['response' => $response]);

            $responseData = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid JSON response');
            }

            if (!isset($responseData['response']['job'][0])) {
                throw new \Exception('No status data found');
            }

            $jobData = $responseData['response']['job'][0];

            return [
                'phone' => $jobData['telno'],
                'status' => $this->getStatusMessage((string)$jobData['status']),
                'operator' => $this->getOperatorName((string)$jobData['operator']),
                'message_count' => $jobData['msglen'],
                'delivery_date' => $jobData['deliveredDate'],
                'error_code' => $jobData['errorCode'] ? $this->getErrorMessage((string)$jobData['errorCode']) : null,
                'job_id' => $jobData['jobid']
            ];

        } catch (\Exception $e) {
            $this->logger->error('SMS status check failed: ' . $e->getMessage());
            throw $e;
        }
    }

    private function getStatusMessage(string $code): string
    {
        $statuses = [
            '0' => 'Pending',
            '1' => 'Delivered',
            '2' => 'Timeout',
            '3' => 'Invalid Number',
            '4' => 'Not Sent to Operator',
            '11' => 'Not Accepted by Operator',
            '12' => 'Sending Error',
            '13' => 'Duplicate',
            '15' => 'Blacklisted',
            '16' => 'IYS Rejected',
            '17' => 'IYS Error'
        ];

        return $statuses[$code] ?? 'Unknown Status';
    }

    private function getOperatorName(string $code): string
    {
        $operators = [
            '10' => 'Vodafone',
            '20' => 'Türk Telekom',
            '30' => 'Turkcell',
            '40' => 'IDangerous STH',
            '41' => 'IDangerous Mobile',
            '60' => 'Türk Telekom Fixed',
            '70' => 'Unknown Operator',
            '160' => 'KKTC Vodafone',
            '212' => 'International',
            '213' => 'International',
            '214' => 'International',
            '215' => 'International',
            '880' => 'KKTC Turkcell'
        ];

        return $operators[$code] ?? 'Unknown Operator';
    }
}