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

        $mail = (new MailMessage)
            ->subject(($cod ? 'New order ' : 'Paid order ').$order->order_number.' · '.Money::tzs($order->total))
            ->greeting($cod ? 'New pay-on-delivery order' : 'New paid order to send')
            ->line("**{$order->customer_name}** · ".($order->phone ? Phone::display($order->phone) : ''))
            ->line(collect([$order->area, $order->district?->name, $order->region?->name])->filter()->implode(', '));

        foreach ($order->items as $item) {
            $mail->line("{$item->quantity} × {$item->product_name}".($item->variant_name ? " ({$item->variant_name})" : '').' · '.Money::tzs($item->line_total));
        }

        return $mail
            ->line('**Total: '.Money::tzs($order->total).'**'.($cod ? ' (to collect on delivery)' : ' (paid)'))
            ->action('Open the order', OrderResource::getUrl('view', ['record' => $order], panel: 'admin'))
            ->line($cod ? 'Call the customer to confirm, then pack and send.' : 'Pack and send it.');
    }
}
