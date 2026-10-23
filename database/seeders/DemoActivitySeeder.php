<?php

namespace Database\Seeders;

use App\Enums\ReviewStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\Region;
use App\Models\Review;
use App\Services\OrderService;
use App\Services\OrderWorkflow;
use App\Services\PosService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Two weeks of pretend shop activity for a demo: online orders from Dar and upcountry at every stage,
 * counter (POS) sales and published reviews. Makes the dashboard, charts and order lists look real.
 *
 * Run only on a demo database:  php artisan db:seed --class=DemoActivitySeeder
 */
class DemoActivitySeeder extends Seeder
{
    private const CUSTOMERS = [
        ['Neema Mushi', '0712 345 678'], ['Baraka Juma', '0754 221 908'], ['Rehema Said', '0715 663 120'], ['Emmanuel Mollel', '0687 441 350'],
        ['Grace Kimaro', '0767 902 114'], ['Hamisi Mwinyi', '0656 118 742'], ['Zawadi Lyimo', '0713 558 290'], ['Joseph Massawe', '0744 309 671'],
        ['Fatma Ally', '0622 774 015'], ['Daudi Shirima', '0765 480 236'], ['Upendo Mrema', '0718 236 459'], ['Salim Bakari', '0689 512 873'],
        ['Mwanaisha Omari', '0714 905 382'], ['Peter Swai', '0757 663 908'], ['Halima Kombo', '0626 140 557'], ['Godfrey Minja', '0783 211 694'],
    ];

    private const UPCOUNTRY = [
        ['Arusha', 'Njiro', 'Block C, near Njiro Complex'], ['Mwanza', 'Nyamagana', 'Kenyatta Road, opposite the bank'],
        ['Dodoma', 'Area D', 'Near Area D market'], ['Mbeya', 'Uyole', 'Uyole stand, blue shop'], ['Kilimanjaro', 'Moshi Mjini', 'Near the clock tower'],
        ['Morogoro', 'Kihonda', 'Behind Kihonda petrol station'], ['Tanga', 'Chumbageni', 'Near Chumbageni primary school'],
    ];

    private const DAR_AREAS = ['Sinza', 'Mikocheni', 'Mbezi Beach', 'Kariakoo', 'Tegeta', 'Kijitonyama', 'Masaki', 'Ubungo Maziwa', 'Kigamboni Ferry'];

    private const REVIEWS = [
        [5, 'Arrived the next day in Sinza and works perfectly. Asante Mega Mall!'],
        [5, 'Good quality, exactly like the photos. The rider called before coming.'],
        [4, 'Very good product. Delivery to Mwanza took three days but well packed.'],
        [5, 'Original and good price. Will buy again.'],
        [4, 'Nice, though the box was a bit damaged. Product itself is fine.'],
        [5, 'Paid on delivery, no stress. Recommended.'],
        [3, 'Okay for the price. Size runs a little small.'],
        [5, 'Bought as a gift and my wife loved it.'],
    ];

    public function run(): void
    {
        $orders = app(OrderService::class);
        $flow = app(OrderWorkflow::class);
        $pos = app(PosService::class);
        $dar = Region::where('name', 'Dar es Salaam')->with('districts')->first();

        try {
            for ($daysAgo = 13; $daysAgo >= 0; $daysAgo--) {
                $day = now()->subDays($daysAgo)->startOfDay();

                // 2 to 4 online orders a day
                foreach (range(1, random_int(2, 4)) as $n) {
                    Carbon::setTestNow($day->copy()->addHours(random_int(8, 20))->addMinutes(random_int(0, 59)));
                    $inDar = random_int(1, 100) <= 60;
                    [$name, $phone] = self::CUSTOMERS[array_rand(self::CUSTOMERS)];

                    try {
                        $order = $orders->place($inDar ? [
                            'customer_name' => $name, 'phone' => $phone, 'region_id' => $dar->id,
                            'district_id' => $dar->districts->random()->id,
                            'area' => self::DAR_AREAS[array_rand(self::DAR_AREAS)], 'address' => 'Near the main road, ask for '.strtok($name, ' '),
                            'items' => $this->basket(),
                        ] : $this->upcountry($name, $phone));
                    } catch (Throwable) {
                        continue; // a product ran out of stock; skip this one
                    }

                    $this->advance($order, $daysAgo, $flow);
                }

                // 1 to 3 shop counter sales a day
                foreach (range(1, random_int(1, 3)) as $n) {
                    Carbon::setTestNow($day->copy()->addHours(random_int(9, 18))->addMinutes(random_int(0, 59)));
                    try {
                        $pos->sell($this->basket(), ['cash', 'cash', 'mpesa', 'mixx', 'airtel'][random_int(0, 4)]);
                    } catch (Throwable) {
                        continue;
                    }
                }
            }
        } finally {
            Carbon::setTestNow();
        }

        $this->reviews();
        $this->command?->info('Demo activity added: '.Order::count().' orders.');
    }

    /** Move an order along according to its age: old orders are delivered, today's are still new. */
    private function advance(Order $order, int $daysAgo, OrderWorkflow $flow): void
    {
        $later = fn (int $hours) => Carbon::setTestNow(now()->addHours($hours));
        $prepaid = $order->payment_mode->value === 'prepaid';

        if ($prepaid) {
            if ($daysAgo === 0 && random_int(0, 1)) {
                return; // still waiting for mobile money
            }
            $later(0);
            $flow->recordPayment($order->fresh(), ['mpesa', 'mixx', 'airtel', 'halopesa'][random_int(0, 3)], 'DEMO'.strtoupper(substr(md5((string) $order->id), 0, 8)));
        } elseif ($daysAgo === 0) {
            return; // new: staff still have to call
        } else {
            $later(1);
            $flow->confirm($order->fresh());
        }

        if ($daysAgo <= 1) {
            return; // confirmed, not yet packed
        }
        $later(3);
        $flow->pack($order->fresh());
        $later(2);
        $flow->ship($order->fresh(), $prepaid ? 'Shabiby bus, parcel no. '.random_int(1000, 9999) : 'Rider Juma, 0712 000 111');

        if ($daysAgo <= 3 && $prepaid) {
            return; // still on the bus
        }
        if (random_int(1, 100) <= 6) {
            $flow->cancel($order->fresh(), 'Customer was not reachable');

            return;
        }
        $later($prepaid ? 48 : 5);
        $flow->deliver($order->fresh(), cashCollected: ! $prepaid);
    }

    /** One to three in-stock products, choosing an available size where needed. */
    private function basket(): array
    {
        $products = Product::active()->with('variants')->inRandomOrder()->limit(random_int(1, 3))->get();

        return $products->map(function (Product $product) {
            $variant = $product->variants->where('is_active', true)->where('stock', '>', 1)->shuffle()->first();
            if ($product->variants->isNotEmpty() && ! $variant) {
                return null;
            }
            if (! $variant && $product->stock < 2) {
                return null;
            }

            return ['product_id' => $product->id, 'variant_id' => $variant?->id, 'quantity' => $product->price > 300000 ? 1 : random_int(1, 2)];
        })->filter()->values()->all() ?: throw new \RuntimeException('Nothing in stock');
    }

    private function upcountry(string $name, string $phone): array
    {
        [$regionName, $area, $address] = self::UPCOUNTRY[array_rand(self::UPCOUNTRY)];
        $region = Region::where('name', $regionName)->first();

        return [
            'customer_name' => $name, 'phone' => $phone, 'region_id' => $region->id,
            'area' => $area, 'address' => $address, 'items' => $this->basket(),
        ];
    }

    /** Published reviews on about half of the delivered online orders. */
    private function reviews(): void
    {
        Order::online()->where('status', 'delivered')->with('items')->get()->each(function (Order $order) {
            if (random_int(0, 1) === 0) {
                return;
            }
            $item = $order->items->first();
            if (! $item?->product_id) {
                return;
            }
            [$rating, $comment] = self::REVIEWS[array_rand(self::REVIEWS)];
            Review::firstOrCreate(['product_id' => $item->product_id, 'order_id' => $order->id], [
                'customer_name' => $order->customer_name,
                'phone' => $order->phone,
                'rating' => $rating,
                'comment' => $comment,
                'status' => ReviewStatus::Approved,
                'reply' => $rating <= 3 ? 'Asante for the feedback. Call us and we will help with an exchange.' : null,
            ]);
        });
    }
}
