<?php

namespace App\Notifications;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Payment;
use App\Support\Money;
use App\Support\PaymentChannels;
use App\Support\Phone;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Email to staff: a customer says they paid to the Lipa Namba. Check the money arrived, then confirm. */
class PaymentToCheckForStaff extends Notification
{
    use Queueable;

    public function __construct(public readonly Payment $payment) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $payment = $this->payment;
        $order = $payment->order;

        return (new MailMessage)
            ->subject("Payment to check: {$order->order_number} · ".Money::tzs($payment->amount))
            ->greeting('A customer says they paid to your Lipa Namba')
            ->line("**{$order->customer_name}** · order {$order->order_number}")
            ->line('Amount expected: **'.Money::tzs($payment->amount).'**')
            ->line('Paid with: '.PaymentChannels::label($payment->channel).' from '.($payment->phone ? Phone::display($payment->phone) : 'unknown number'))
            ->line("Transaction ID: **{$payment->external_id}**")
            ->action('Check and confirm', OrderResource::getUrl('view', ['record' => $order], panel: 'admin'))
            ->line('Find this transaction in your mobile money messages. If the amount is right, press "Confirm payment". If not, press "Payment not found".');
    }
}
