<?php

use App\Models\ContentPage;
use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;

/**
 * Clean-up after removing the POS, purchases, damages, reviews, online returns, customer SMS,
 * the top bar messages and AzamPay.
 *
 *  - Deletes the store settings those features used (nothing reads them any more).
 *  - Updates the starter shop pages and invoice delivery note: no more SMS, free delivery in Dar,
 *    returns arranged by phone or WhatsApp. Only the exact starter sentences are replaced, so
 *    anything staff already rewrote under Shop pages or Store settings is left as it is.
 *
 * No tables or columns are dropped: old orders, refunds and stock history stay readable.
 */
return new class extends Migration
{
    private const OLD_SETTINGS = ['ticker', 'sms_enabled', 'sms_templates', 'auto_approve_reviews', 'payment_method'];

    private const PAGE_TEXT = [
        '(it starts with MM and is in your SMS)'
            => '(it starts with MM and is shown when you place your order)',
        'When you place an order we send you an order number by SMS. We may call you to confirm it.'
            => 'When you place an order you get an order number on the screen. We call you to confirm it.',
        'Delivery costs are not included in our prices. In Dar es Salaam you pay the rider directly. For other regions we send your parcel by bus, and you pay the transport cost when you collect it.'
            => 'Delivery in Dar es Salaam is free. For other regions we send your parcel by bus, and you pay the bus fare when you collect it.',
        'including SMS updates about your order'
            => 'including phone calls and WhatsApp messages about your order',
        'the mobile money and payment providers that process your payment, and the SMS provider that sends your order updates.'
            => 'and the mobile money providers that process your payment.',
        '<li>Go to <strong>Return an item</strong> on our website, enter your order number and phone number, and choose what you are returning and why. You can also call or WhatsApp us on {phone}.</li>'
            => '<li>Call or WhatsApp us on {phone} with your order number, what you are returning and why.</li>',
        'Refunds are sent by mobile money (or in cash at our shop) for'
            => 'Refunds are sent by mobile money for',
    ];

    private const OLD_DELIVERY_NOTE = 'Delivery is not included in our prices. In Dar es Salaam you pay the rider directly when your order arrives. For other regions we send your parcel by bus, and you pay the transport cost when you collect it.';

    private const NEW_DELIVERY_NOTE = 'Delivery is free in Dar es Salaam. Other regions: we send your parcel by bus and you pay the bus fare when you collect it.';

    public function up(): void
    {
        Setting::whereIn('key', self::OLD_SETTINGS)->delete();
        Cache::forget('settings.all'); // a bulk delete skips the model events that clear the cache

        $note = Setting::where('key', 'delivery_note')->first();
        if ($note && $note->value === self::OLD_DELIVERY_NOTE) {
            $note->update(['value' => self::NEW_DELIVERY_NOTE]);
        }

        foreach (ContentPage::all() as $page) {
            $body = strtr((string) $page->body, self::PAGE_TEXT);
            if ($body !== $page->body) {
                $page->update(['body' => $body]);
            }
        }
    }

    public function down(): void
    {
        // Nothing to undo: settings return with their defaults if a feature is added back,
        // and page texts can be edited under Shop pages.
    }
};
