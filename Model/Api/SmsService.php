<?php
declare(strict_types=1);

namespace IDangerous\Sms\Model\Api;

use IDangerous\Sms\Api\SmsServiceInterface;
use IDangerous\Sms\Helper\Config;
use Magento\Framework\HTTP\Client\Curl;
use Psr\Log\LoggerInterface;

class SmsService implements SmsServiceInterface
{
    private const API_ENDPOINT = 'https://api.netgsm.com.tr/sms/send/get';
    private const XML_API_ENDPOINT = 'https://api.netgsm.com.tr/sms/send/xml';

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

    /**
     * Send same SMS to multiple recipients (1:n)
     *
     * @param array $phones Array of phone numbers
     * @param string $message Message to send
     * @param string|null $startDate Optional start date (format: ddMMyyyyHHmm)
     * @param string|null $stopDate Optional stop date (format: ddMMyyyyHHmm)
     * @return array
     */
    public function sendBulkSms(array $phones, string $message, ?string $startDate = null, ?string $stopDate = null): array
    {
        try {
            if (empty($phones) || empty($message)) {
                return [
                    'success' => false,
                    'message' => __('Phone numbers and message are required.')
                ];
            }

            // Clean and format phone numbers
            $formattedPhones = array_map(function($phone) {
                $phone = preg_replace('/[^0-9+]/', '', $phone);
                if (!str_starts_with($phone, '+90')) {
                    $phone = '+90' . ltrim($phone, '+0');
                }
                return $phone;
            }, $phones);

            // Build XML
            $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><mainbody/>');

            $header = $xml->addChild('header');
            $company = $header->addChild('company', 'Netgsm');
            $company->addAttribute('dil', 'TR');
            $header->addChild('usercode', $this->config->getUsername());
            $header->addChild('password', $this->config->getPassword());
            $header->addChild('type', '1:n');
            $header->addChild('msgheader', $this->config->getMsgHeader());

            if ($startDate) {
                $header->addChild('startdate', $startDate);
            }
            if ($stopDate) {
                $header->addChild('stopdate', $stopDate);
            }

            $body = $xml->addChild('body');
            $msg = $body->addChild('msg');
            $msg->addCData($message);

            foreach ($formattedPhones as $phone) {
                $body->addChild('no', $phone);
            }

            $response = $this->sendXmlRequest($xml->asXML());
            return $this->parseResponse($response);

        } catch (\Exception $e) {
            $this->logger->error('Bulk SMS Service Error: ' . $e->getMessage(), [
                'exception' => $e,
                'phones' => $phones,
                'message' => $message
            ]);

            return [
                'success' => false,
                'message' => __('Failed to send bulk SMS: %1', $e->getMessage())
            ];
        }
    }

    /**
     * Send different messages to different recipients (n:n)
     *
     * @param array $messages Array of ['phone' => string, 'message' => string]
     * @param string|null $startDate Optional start date (format: ddMMyyyyHHmm)
     * @param string|null $stopDate Optional stop date (format: ddMMyyyyHHmm)
     * @return array
     */
    public function sendMultipleSms(array $messages, ?string $startDate = null, ?string $stopDate = null): array
    {
        try {
            if (empty($messages)) {
              return [
                'success' => false,
                'message' => __('Messages array is required.')
              ];
            }

            // Build XML
            $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><mainbody/>');

            $header = $xml->addChild('header');
            $company = $header->addChild('company', 'Netgsm');
            $company->addAttribute('dil', 'TR');
            $header->addChild('usercode', $this->config->getUsername());
            $header->addChild('password', $this->config->getPassword());
            $header->addChild('type', 'n:n');
            $header->addChild('msgheader', $this->config->getMsgHeader());

            if ($startDate) {
                $header->addChild('startdate', $startDate);
            }
            if ($stopDate) {
                $header->addChild('stopdate', $stopDate);
            }

            $body = $xml->addChild('body');

            foreach ($messages as $messageData) {
                if (empty($messageData['phone']) || empty($messageData['message'])) {
                    continue;
                }

                $phone = preg_replace('/[^0-9+]/', '', $messageData['phone']);
                if (!str_starts_with($phone, '+90')) {
                    $phone = '+90' . ltrim($phone, '+0');
                }

                $mp = $body->addChild('mp');
                $msg = $mp->addChild('msg');
                $msg->addCData($messageData['message']);
                $mp->addChild('no', $phone);
            }

            $response = $this->sendXmlRequest($xml->asXML());
            return $this->parseResponse($response);

        } catch (\Exception $e) {
            $this->logger->error('Multiple SMS Service Error: ' . $e->getMessage(), [
                'exception' => $e,
                'messages' => $messages
            ]);

            return [
                'success' => false,
                'message' => __('Failed to send multiple SMS: %1', $e->getMessage())
            ];
        }
    }

    /**
     * Send XML request to API
     *
     * @param string $xmlData
     * @return string
     */
    private function sendXmlRequest(string $xmlData): string
    {
        $this->curl->addHeader('Content-Type', 'text/xml');
        $this->curl->setOption(CURLOPT_SSL_VERIFYHOST, 2);
        $this->curl->setOption(CURLOPT_SSL_VERIFYPEER, 0);
        $this->curl->setOption(CURLOPT_TIMEOUT, 30);

        $this->curl->post(self::XML_API_ENDPOINT, $xmlData);

        return $this->curl->getBody();
    }

    /**
     * Parse API response
     *
     * @param string $response
     * @return array
     */
    private function parseResponse(string $response): array
    {
        list($code, $bulkId) = array_pad(explode(' ', trim($response)), 2, null);

        switch ($code) {
            case '00':
                return [
                    'success' => true,
                    'message' => __('SMS sent successfully'),
                    'bulkId' => $bulkId,
                    'code' => $code
                ];
            case '30':
                return [
                    'success' => false,
                    'message' => __('Invalid username or password'),
                    'code' => $code
                ];
            case '40':
                return [
                    'success' => false,
                    'message' => __('Invalid message header'),
                    'code' => $code
                ];
            case '70':
                return [
                    'success' => false,
                    'message' => __('Invalid phone number format'),
                    'code' => $code
                ];
            default:
                return [
                    'success' => false,
                    'message' => __('Error code: %1', $code),
                    'code' => $code
                ];
        }
    }
}