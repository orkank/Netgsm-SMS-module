<?php
declare(strict_types=1);

namespace IDangerous\Sms\Api;

interface SmsServiceInterface
{
    /**
     * Send SMS message
     *
     * @param string $phoneNumber
     * @param string $message
     * @return array
     */
    public function sendSms(string $phoneNumber, string $message): array;
}