<?php

namespace App\Notifications;

use App\Enums\PaymentMode;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Support\Money;
use App\Support\Phone;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Email to staff: a new order needs handling. */
class NewOrderForStaff extends Notification
{
    use Queueable;

    public function __construct(public readonly Order $order) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $order = $this->order->loadMissing(['items', 'region', 'district']);
        $cod = $order->payment_mode === PaymentMode::CashOnDelivery;
        $waiting = ! $cod && ! $order->isPaid();

        $mail = (new MailMessage)
            ->subject(($cod || $waiting ? 'New order ' : 'Paid order ').$order->order_number.' · '.Money::tzs($order->total))
            ->greeting(match (true) {
                $cod => 'New pay-on-delivery order',
                $waiting => 'New order, waiting for Lipa Namba payment',
                default => 'New paid order to send',
            })
            ->line("**{$order->customer_name}** · ".($order->phone ? Phone::display($order->phone) : ''))
            ->line(collect([$order->area, $order->district?->name, $order->region?->name])->filter()->implode(', '));

        foreach ($order->items as $item) {
            $mail->line("{$item->quantity} × {$item->product_name}".($item->variant_name ? " ({$item->variant_name})" : '').' · '.Money::tzs($item->line_total));
        }

        return $mail
            ->line('**Total: '.Money::tzs($order->total).'**'.match (true) {
                $cod => ' (to collect on delivery)',
                $waiting => ' (to be paid to your Lipa Namba)',
                default => ' (paid)',
            })
            ->action('Open the order', OrderResource::getUrl('view', ['record' => $order], panel: 'admin'))
            ->line(match (true) {
                $cod => 'Call the customer to confirm, then pack and send.',
                $waiting => 'When the money arrives on the business phone, press "Record payment" on the order and call the customer to confirm.',
                default => 'Pack and send it.',
            });
    }
}
