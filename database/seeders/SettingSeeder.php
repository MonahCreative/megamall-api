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
            'ticker' => [
                'Pay on delivery in Dar es Salaam',
                'Delivery to all 31 regions of Tanzania',
                'Pay with M-Pesa, Mixx by Yas, Airtel Money or HaloPesa',
                'Call or WhatsApp: +255 700 000 000',
            ],
            // Unpaid mobile money orders are cancelled after this many minutes and stock is released.
            'prepaid_order_expiry_minutes' => 60,
            // Customers can request a return this many days after delivery.
            'return_window_days' => 7,
            // false = staff approve each review before it is shown.
            'auto_approve_reviews' => false,
            // Shown at checkout and on order pages. The shop does not charge delivery.
            'delivery_note' => 'Delivery is not included in our prices. In Dar es Salaam you pay the rider directly when your order arrives. For other regions we send your parcel by bus, and you pay the transport cost when you collect it.',
            // Customer SMS (wording editable in Store settings) and staff alerts.
            'sms_enabled' => true,
            'sms_templates' => \App\Support\SmsTemplates::DEFAULTS,
            'staff_alert_emails' => '',
            'staff_alert_sound' => true,
            // Reorder list: re-order at this stock level, and buy enough for this many days of sales.
            'low_stock_level' => 5,
            'reorder_cover_days' => 30,
            // Shop colours, changed under Store settings > Website colours.
            ...\App\Support\Theme::DEFAULTS,
        ];

        foreach ($settings as $key => $value) {
            // Never overwrite something already changed in Store settings.
            Setting::firstOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
