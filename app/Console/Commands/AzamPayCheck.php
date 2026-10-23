<?php

namespace App\Console\Commands;

use App\Services\Payments\AzamPayGateway;
use App\Support\Phone;
use Illuminate\Console\Command;
use Throwable;

/** php artisan payments:azampay-check 0712345678 --amount=1000 --channel=mpesa */
class AzamPayCheck extends Command
{
    protected $signature = 'payments:azampay-check {phone : Phone to send the test PIN prompt to} {--amount=1000} {--channel=mpesa : mpesa, mixx, airtel or halopesa}';

    protected $description = 'Check the AzamPay credentials in .env by sending one test payment request';

    public function handle(AzamPayGateway $gateway): int
    {
        $phone = Phone::normalize($this->argument('phone'));
        if (! $phone) {
            $this->error('That is not a valid Tanzanian mobile number.');

            return self::FAILURE;
        }

        $this->info('AzamPay environment: '.config('payments.azampay.environment'));

        try {
            $result = $gateway->testCheckout($phone, (int) $this->option('amount'), (string) $this->option('channel'));
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->line('HTTP '.$result['status']);
        $this->line(json_encode($result['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $result['status'] < 300 ? self::SUCCESS : self::FAILURE;
    }
}
