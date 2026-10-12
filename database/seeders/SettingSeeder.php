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
        ];

        foreach ($settings as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
