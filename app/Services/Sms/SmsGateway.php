<?php

namespace App\Services\Sms;

/** One SMS provider. A real one (Beem, NextSMS...) implements this and is chosen in config/sms.php. */
interface SmsGateway
{
    /**
     * Send one message to a number in 255XXXXXXXXX format.
     *
     * @return array{status: 'sent'|'logged'|'failed', provider_id?: ?string, error?: ?string}
     */
    public function send(string $to, string $message): array;
}
