<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentMode;
use App\Models\Order;
use App\Models\Setting;
use App\Notifications\NewOrderForStaff;
use App\Services\Sms\SmsService;
use App\Support\SmsTemplates;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Tells customers (SMS) and staff (email) what happened to an order.
 * Messages go out after the database change is saved, and a failure never blocks the order itself.
 * Shop-counter (POS) sales send nothing.
 */
class OrderNotifier
{
    public function __construct(private readonly SmsService $sms) {}

    public function orderPlaced(Order $order): void
    {
        $cod = $order->payment_mode === PaymentMode::CashOnDelivery;
        $this->sms($order, $cod ? 'order_placed_cod' : 'order_placed_prepaid');

        if ($cod) {
            $this->alertStaff($order); // prepaid orders alert staff once they are paid
        }
    }

    public function paymentReceived(Order $order, bool $alertStaff = true): void
    {
        if ($order->status === OrderStatus::Cancelled) {
            return;
        }
        $this->sms($order, 'payment_received');
        if ($alertStaff) {
            $this->alertStaff($order);
        }
    }

    public function confirmed(Order $order): void
    {
        if ($order->payment_mode === PaymentMode::CashOnDelivery) {
            $this->sms($order, 'order_confirmed');
        }
    }

    public function shipped(Order $order): void
    {
        $this->sms($order, 'order_shipped');
    }

    public function delivered(Order $order): void
    {
        $this->sms($order, 'order_delivered');
    }

    public function cancelled(Order $order, string $reason): void
    {
        $this->sms($order, 'order_cancelled', ['{reason}' => rtrim($reason, '.')]);
    }

    private function sms(Order $order, string $event, array $extra = []): void
    {
        if ($order->isPos() || ! Setting::getValue('sms_enabled', true)) {
            return;
        }

        DB::afterCommit(function () use ($order, $event, $extra) {
            try {
                $order = $order->fresh(['region', 'district']) ?? $order;
                $this->sms->send($event, $order->phone, SmsTemplates::render($event, $order, $extra), $order);
            } catch (Throwable $exception) {
                report($exception);
            }
        });
    }

    private function alertStaff(Order $order): void
    {
        $emails = collect(preg_split('/[\s,;]+/', (string) Setting::getValue('staff_alert_emails', '')))
            ->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values()
            ->all();

        if ($order->isPos() || $emails === []) {
            return;
        }

        DB::afterCommit(function () use ($order, $emails) {
            try {
                Notification::route('mail', $emails)->notify(new NewOrderForStaff($order->fresh() ?? $order));
            } catch (Throwable $exception) {
                report($exception); // e.g. email server down: the order is still fine
            }
        });
    }
}
