<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Log;

/** No SMS account yet: write the message to the log instead of sending it. */
class LogSmsGateway implements SmsGateway
{
    public function send(string $to, string $message): array
    {
        Log::info("SMS to {$to} (not sent, SMS_DRIVER=log): {$message}");

        return ['status' => 'logged'];
    }
}
