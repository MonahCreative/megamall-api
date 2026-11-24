<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Setting;
use App\Notifications\NewOrderForStaff;
use App\Notifications\PaymentToCheckForStaff;
use Closure;
use Illuminate\Notifications\Notification as BaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Tells staff (email and WhatsApp) when an order needs them.
 * Customers are kept informed by staff phone calls and the order page; the shop sends them no SMS.
 * Messages go out after the database change is saved, and a failure never blocks the order itself.
 */
class OrderNotifier
{
    public function orderPlaced(Order $order): void
    {
        // Pay-on-delivery orders need a confirmation call; pay-first orders need staff to watch for the Lipa Namba money.
        $this->alertStaff($order);
    }

    public function paymentReceived(Order $order, bool $alertStaff = true): void
    {
        if ($order->status === OrderStatus::Cancelled || ! $alertStaff) {
            return;
        }
        $this->alertStaff($order);
    }

    /** A customer sent a Lipa Namba transaction ID: staff must check it. */
    public function paymentToCheck(Payment $payment): void
    {
        $this->emailStaff($payment->order, fn () => new PaymentToCheckForStaff($payment->fresh(['order']) ?? $payment));
    }

    private function alertStaff(Order $order): void
    {
        $this->emailStaff($order, fn () => new NewOrderForStaff($order->fresh() ?? $order));
        app(StaffWhatsApp::class)->notifyOrder($order);
    }

    /** @param  Closure(): BaseNotification  $make  built after the database change is saved, so it shows the saved data */
    private function emailStaff(Order $order, Closure $make): void
    {
        $emails = collect(preg_split('/[\s,;]+/', (string) Setting::getValue('staff_alert_emails', '')))
            ->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values()
            ->all();

        if ($emails === []) {
            return;
        }

        DB::afterCommit(function () use ($emails, $make) {
            try {
                Notification::route('mail', $emails)->notify($make());
            } catch (Throwable $exception) {
                report($exception); // e.g. email server down: the order is still fine
            }
        });
    }
}
