<?php

namespace App\Support;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;

/**
 * The SMS customers get as their order moves along. Staff can reword them in Store settings.
 * Placeholders: {first_name} {name} {order} {total} {area} {tracking} {reason} {minutes}
 * {store_name} {store_phone} {track_url} {return_days}
 */
class SmsTemplates
{
    public const EVENTS = [
        'order_placed_cod' => 'Order placed (Dar es Salaam, pay on delivery)',
        'order_placed_prepaid' => 'Order placed (other regions, waiting for payment)',
        'payment_received' => 'Mobile money payment received',
        'order_confirmed' => 'Order confirmed after the call (pay on delivery)',
        'order_shipped' => 'Order sent out',
        'order_delivered' => 'Order delivered',
        'order_cancelled' => 'Order cancelled',
        'back_in_stock' => 'Back in stock (use {product} {price} {url})',
    ];

    public const DEFAULTS = [
        'order_placed_cod' => 'Habari {first_name}! We received your order {order} ({total}). We will call you to confirm, then deliver. You pay when it arrives. Track: {track_url}',
        'order_placed_prepaid' => 'Habari {first_name}! Order {order} ({total}) is reserved for you. Complete the mobile money payment within {minutes} minutes, or it will be cancelled.',
        'payment_received' => 'Asante {first_name}! We received {total} for order {order}. We are preparing it for dispatch and will SMS you when it is on the way.',
        'order_confirmed' => 'Order {order} is confirmed. We will deliver to {area} soon. Pay {total} when it arrives. Questions? Call {store_phone}.',
        'order_shipped' => 'Order {order} is on the way! {tracking} Questions? Call {store_phone}.',
        'order_delivered' => 'Order {order} delivered. Asante for shopping with {store_name}! Any problem? You can return items within {return_days} days.',
        'order_cancelled' => 'Order {order} has been cancelled ({reason}). Questions? Call {store_phone}.',
        'back_in_stock' => 'Good news! {product} is back in stock at {store_name} for {price}. Order before it sells out: {url}',
    ];

    /** @return array<string, string> saved wording, with defaults for anything not set */
    public static function all(): array
    {
        $saved = Setting::getValue('sms_templates', []);

        return array_replace(self::DEFAULTS, array_filter(is_array($saved) ? $saved : [], fn ($text) => filled($text)));
    }

    /** Messages about a product rather than an order (back in stock). */
    public static function renderForProduct(string $event, Product $product, ?ProductVariant $variant = null): string
    {
        $settings = Setting::allValues();
        $values = [
            '{product}' => $product->name.($variant ? " ({$variant->name})" : ''),
            '{price}' => Money::tzs($variant?->price ?? $product->price),
            '{url}' => rtrim((string) config('megamall.frontend_url'), '/').'/product/'.$product->slug,
            '{store_name}' => (string) ($settings['store_name'] ?? config('app.name')),
            '{store_phone}' => (string) ($settings['phone'] ?? ''),
        ];

        return trim(preg_replace('/ {2,}/', ' ', strtr(self::all()[$event] ?? '', $values)));
    }

    public static function render(string $event, Order $order, array $extra = []): string
    {
        $settings = Setting::allValues();
        $template = self::all()[$event] ?? '';

        $values = [
            '{first_name}' => strtok(trim((string) $order->customer_name), ' ') ?: 'Customer',
            '{name}' => (string) $order->customer_name,
            '{order}' => $order->order_number,
            '{total}' => Money::tzs($order->total),
            '{area}' => $order->area ?: ($order->district?->name ?? $order->region?->name ?? 'you'),
            '{tracking}' => filled($order->tracking_note) ? rtrim($order->tracking_note, '. ').'.' : '',
            '{reason}' => 'no reason given',
            '{minutes}' => (string) (int) ($settings['prepaid_order_expiry_minutes'] ?? 60),
            '{store_name}' => (string) ($settings['store_name'] ?? config('app.name')),
            '{store_phone}' => (string) ($settings['phone'] ?? ''),
            '{track_url}' => rtrim((string) config('megamall.frontend_url'), '/').'/track',
            '{return_days}' => (string) (int) ($settings['return_window_days'] ?? 7),
            ...$extra,
        ];

        // Tidy double spaces left by empty placeholders.
        return trim(preg_replace('/ {2,}/', ' ', strtr($template, $values)));
    }
}
