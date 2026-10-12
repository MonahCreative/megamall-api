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
     * Fees and days are starting values; change them later in the admin panel.
     * [name, delivery fee TZS, min days, max days]
     */
    private const REGIONS = [
        ['Dar es Salaam', 3000, 0, 1],
        ['Pwani', 7000, 1, 2],
        ['Morogoro', 8000, 1, 2],
        ['Tanga', 10000, 1, 3],
        ['Dodoma', 10000, 1, 3],
        ['Arusha', 10000, 1, 3],
        ['Kilimanjaro', 10000, 1, 3],
        ['Manyara', 12000, 2, 4],
        ['Iringa', 10000, 1, 3],
        ['Mbeya', 12000, 2, 3],
        ['Njombe', 12000, 2, 4],
        ['Songwe', 12000, 2, 4],
        ['Singida', 12000, 2, 3],
        ['Tabora', 12000, 2, 4],
        ['Mwanza', 12000, 2, 3],
        ['Shinyanga', 12000, 2, 3],
        ['Simiyu', 12000, 2, 4],
        ['Geita', 12000, 2, 4],
        ['Mara', 13000, 2, 4],
        ['Lindi', 12000, 2, 4],
        ['Mtwara', 12000, 2, 4],
        ['Ruvuma', 13000, 2, 4],
        ['Kagera', 15000, 3, 5],
        ['Kigoma', 15000, 3, 5],
        ['Katavi', 15000, 3, 5],
        ['Rukwa', 15000, 3, 5],
        ['Mjini Magharibi', 12000, 1, 3],
        ['Kaskazini Unguja', 13000, 2, 3],
        ['Kusini Unguja', 13000, 2, 3],
        ['Kaskazini Pemba', 15000, 2, 4],
        ['Kusini Pemba', 15000, 2, 4],
    ];

    /** Dar es Salaam districts with their own fees. */
    private const DAR_DISTRICTS = [
        ['Ilala', 3000],
        ['Kinondoni', 3000],
        ['Ubungo', 3000],
        ['Temeke', 4000],
        ['Kigamboni', 5000],
    ];

    public function run(): void
    {
        foreach (self::REGIONS as [$name, $fee, $min, $max]) {
            Region::updateOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    // THE RULE: only Dar es Salaam pays on delivery.
                    'payment_mode' => $name === 'Dar es Salaam' ? PaymentMode::CashOnDelivery : PaymentMode::Prepaid,
                    'delivery_fee' => $fee,
                    'delivery_days_min' => $min,
                    'delivery_days_max' => $max,
                    'is_active' => true,
                ],
            );
        }

        $dar = Region::where('slug', 'dar-es-salaam')->first();
        foreach (self::DAR_DISTRICTS as [$name, $fee]) {
            $dar->districts()->updateOrCreate(['name' => $name], ['delivery_fee' => $fee, 'is_active' => true]);
        }
    }
}
