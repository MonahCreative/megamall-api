<?php

namespace Database\Seeders;

use App\Enums\PaymentMode;
use App\Models\Region;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RegionSeeder extends Seeder
{
    /**
     * All 31 regions of Tanzania (26 mainland + 5 Zanzibar).
     * Delivery days are starting values; change them later in the admin panel.
     * Delivery itself is paid by the customer to the rider or bus company, not through the shop.
     * [name, min days, max days]
     */
    private const REGIONS = [
        ['Dar es Salaam', 0, 1],
        ['Pwani', 1, 2],
        ['Morogoro', 1, 2],
        ['Tanga', 1, 3],
        ['Dodoma', 1, 3],
        ['Arusha', 1, 3],
        ['Kilimanjaro', 1, 3],
        ['Manyara', 2, 4],
        ['Iringa', 1, 3],
        ['Mbeya', 2, 3],
        ['Njombe', 2, 4],
        ['Songwe', 2, 4],
        ['Singida', 2, 3],
        ['Tabora', 2, 4],
        ['Mwanza', 2, 3],
        ['Shinyanga', 2, 3],
        ['Simiyu', 2, 4],
        ['Geita', 2, 4],
        ['Mara', 2, 4],
        ['Lindi', 2, 4],
        ['Mtwara', 2, 4],
        ['Ruvuma', 2, 4],
        ['Kagera', 3, 5],
        ['Kigoma', 3, 5],
        ['Katavi', 3, 5],
        ['Rukwa', 3, 5],
        ['Mjini Magharibi', 1, 3],
        ['Kaskazini Unguja', 2, 3],
        ['Kusini Unguja', 2, 3],
        ['Kaskazini Pemba', 2, 4],
        ['Kusini Pemba', 2, 4],
    ];

    /** Dar es Salaam districts, so riders know which part of the city to go to. */
    private const DAR_DISTRICTS = ['Ilala', 'Kinondoni', 'Ubungo', 'Temeke', 'Kigamboni'];

    public function run(): void
    {
        foreach (self::REGIONS as [$name, $min, $max]) {
            Region::updateOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    // THE RULE: only Dar es Salaam pays on delivery.
                    'payment_mode' => $name === 'Dar es Salaam' ? PaymentMode::CashOnDelivery : PaymentMode::Prepaid,
                    'delivery_days_min' => $min,
                    'delivery_days_max' => $max,
                    'is_active' => true,
                ],
            );
        }

        $dar = Region::where('slug', 'dar-es-salaam')->first();
        foreach (self::DAR_DISTRICTS as $name) {
            $dar->districts()->updateOrCreate(['name' => $name], ['is_active' => true]);
        }
    }
}
