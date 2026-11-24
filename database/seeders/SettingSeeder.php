<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            'store_name' => 'Mega Mall Tanzania',
            'tagline' => 'Everything you need, delivered across Tanzania',
            // Replace with your real numbers. WhatsApp number in international format without "+".
            'phone' => '+255 700 000 000',
            'whatsapp' => '255700000000',
            'email' => 'support@megamall.co.tz',
            // Unpaid mobile money orders are cancelled after this many minutes and stock is released.
            'prepaid_order_expiry_minutes' => 60,
            // Returns accepted this many days after delivery (shown in the shop footer and on invoices).
            'return_window_days' => 7,
            // Printed at the bottom of invoices.
            'delivery_note' => 'Delivery is free in Dar es Salaam. Other regions: we send your parcel by bus and you pay the bus fare when you collect it.',
            // Staff alerts for new orders and payments to check.
            'staff_alert_emails' => '',
            // Staff phones that get a WhatsApp for each new order (CallMeBot), set under Store settings.
            'staff_whatsapp' => [],
            'staff_alert_sound' => true,
            // Reorder list: re-order at this stock level, and buy enough for this many days of sales.
            'low_stock_level' => 5,
            'reorder_cover_days' => 30,
            // Upcountry orders are paid to these till numbers; staff confirm each payment.
            'lipa_numbers' => [],
            'lipa_note' => \App\Support\LipaNamba::DEFAULT_NOTE,
            // Shop colours, changed under Store settings > Website colours.
            ...\App\Support\Theme::DEFAULTS,
        ];

        foreach ($settings as $key => $value) {
            // Never overwrite something already changed in Store settings.
            Setting::firstOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
