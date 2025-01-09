<?php
declare(strict_types=1);

namespace IDangerous\Sms\Model\Api;

use IDangerous\Sms\Api\SmsServiceInterface;
use IDangerous\Sms\Helper\Config;
use Magento\Framework\HTTP\Client\Curl;
use Psr\Log\LoggerInterface;

class SmsService implements SmsServiceInterface
{
    private const API_ENDPOINT = 'https://api.idangerous.com.tr/sms/send/get';

    /**
     * @var Config
     */
    private $config;

    /**
     * @var Curl
     */
    private $curl;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param Config $config
     * @param Curl $curl
     * @param LoggerInterface $logger
     */
    public function __construct(
        Config $config,
        Curl $curl,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->curl = $curl;
        $this->logger = $logger;
    }

    /**
     * Send SMS
     *
     * @param string $phone
     * @param string $message
     * @return array
     */
    public function sendSms(string $phone, string $message): array
    {
        try {
            if (empty($phone) || empty($message)) {
                return [
                    'success' => false,
                    'message' => __('Phone number and message are required.')
                ];
            }

            // Clean phone number
            $phone = preg_replace('/[^0-9+]/', '', $phone);

            // Ensure phone starts with +90
            if (!str_starts_with($phone, '+90')) {
                $phone = '+90' . ltrim($phone, '+0');
            }

            $params = [
                'usercode' => $this->config->getUsername(),
                'password' => $this->config->getPassword(),
                'msgheader' => $this->config->getMsgHeader(),
                'gsmno' => $phone,
                'message' => $message,
                'dil' => 'TR'
            ];

            $url = self::API_ENDPOINT . '?' . http_build_query($params);

            $this->curl->get($url);
            $response = $this->curl->getBody();

            if ($this->config->isDebugLoggingEnabled()) {
                $this->logger->debug('IDangerous SMS API Response', ['response' => $response]);
            }

            // Parse response
            $responseCode = strtok(trim($response), ' ');

            switch ($responseCode) {
                case '00':
                    return [
                        'success' => true,
                        'message' => __('SMS sent successfully'),
                        'code' => $responseCode
                    ];
                case '30':
                    return [
                        'success' => false,
                        'message' => __('Invalid username or password'),
                        'code' => $responseCode
                    ];
                case '40':
                    return [
                        'success' => false,
                        'message' => __('Invalid message header'),
                        'code' => $responseCode
                    ];
                case '70':
                    return [
                        'success' => false,
                        'message' => __('Invalid phone number format'),
                        'code' => $responseCode
                    ];
                default:
                    return [
                        'success' => false,
                        'message' => __('Error code: %1', $responseCode),
                        'code' => $responseCode
                    ];
            }
        } catch (\Exception $e) {
            $this->logger->error('SMS Service Error: ' . $e->getMessage(), [
                'exception' => $e,
                'phone' => $phone,
                'message' => $message
            ]);

            return [
                'success' => false,
                'message' => __('Failed to send SMS: %1', $e->getMessage()),
                'error_details' => [
                    'message' => $e->getMessage(),
                    'code' => $e->getCode(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]
            ];
        }
    }
}