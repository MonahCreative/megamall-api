<?php

namespace Database\Seeders;

use App\Models\Banner;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Sample catalogue so the shop isn't empty while we build.
 * Every product gets a generated placeholder image; replace them with real
 * photos in the admin panel. Delete sample products before going live.
 */
class CatalogSeeder extends Seeder
{
    /** category => [colour, products[name, brand, price, was-price|null, stock, short description]] */
    private const CATALOG = [
        'Fashion' => ['#B4472F', [
            ['Men\'s Kitenge Print Shirt', 'Mega Wear', 38000, 45000, 40, 'Breathable cotton shirt with a bold kitenge print. Sizes S to XXL.'],
            ['Women\'s Maxi Dress', 'Mega Wear', 55000, null, 25, 'Flowing maxi dress for weddings, church and evenings out.'],
            ['Men\'s Leather Sneakers', 'StepUp', 78000, 95000, 18, 'Genuine leather sneakers with a cushioned sole. Sizes 40 to 45.'],
            ['Women\'s Handbag', 'Lulu', 42000, null, 30, 'Structured handbag with zip pocket and shoulder strap.'],
        ]],
        'Phones & Accessories' => ['#1D6FA3', [
            ['Smartphone 6.7" 128GB Dual SIM', 'Nova', 385000, 420000, 14, 'Large display, 50MP camera and 5000mAh battery. Works on all Tanzanian networks.'],
            ['20,000mAh Power Bank', 'Volt', 45000, 55000, 60, 'Charges a phone up to 4 times. Fast charging, two USB ports.'],
            ['Wireless Earbuds', 'Sauti', 35000, null, 50, 'Bluetooth 5.3 earbuds with charging case and 24-hour total battery.'],
        ]],
        'Electronics' => ['#2E5C8A', [
            ['43" Full HD Smart TV', 'Vision', 690000, 750000, 6, 'Smart TV with YouTube and Netflix, two HDMI ports and free-to-air tuner.'],
            ['Bluetooth Party Speaker', 'Sauti', 120000, null, 12, 'Loud portable speaker with lights, microphone input and FM radio.'],
            ['Smart Watch with Heart-Rate Monitor', 'Nova', 72000, 85000, 20, 'Tracks steps, sleep and heart rate. Shows calls and WhatsApp messages.'],
        ]],
        'Home & Kitchen' => ['#A06A00', [
            ['1.5L Blender with Grinder', 'Jikoni', 89000, null, 15, 'Blends juice and grinds spices. 600W motor, two jars included.'],
            ['Non-Stick Cooking Pot Set (5 pcs)', 'Jikoni', 115000, 140000, 10, 'Five pots with glass lids. Works on gas and electric cookers.'],
            ['Electric Kettle 1.8L', 'Jikoni', 32000, null, 35, 'Stainless steel kettle with auto shut-off.'],
            ['Steam Iron 2200W', 'Pasi', 45000, null, 22, 'Steam and dry ironing with non-stick soleplate.'],
        ]],
        'Furniture' => ['#7A5230', [
            ['3-Seater Fabric Sofa', 'Nyumba', 1250000, 1400000, 3, 'Comfortable sofa with solid wood frame. Delivered assembled in Dar es Salaam.'],
            ['Office Chair with Lumbar Support', 'Ofisi', 210000, null, 8, 'Adjustable height, mesh back and armrests.'],
            ['Plastic Shoe Rack (5 Tier)', 'Nyumba', 28000, null, 40, 'Holds up to 15 pairs. Easy to assemble.'],
        ]],
        'Beauty & Personal Care' => ['#0F6B4A', [
            ['Shea Butter Body Lotion 400ml', 'Asili', 18000, null, 80, 'Deep moisture for dry skin, made with natural shea butter.'],
            ['Hair Clipper Set', 'Kinyozi', 48000, 60000, 25, 'Cordless clipper with 4 guide combs. 2-hour battery.'],
            ['Perfume for Women 100ml', 'Marashi', 65000, null, 20, 'Long-lasting floral fragrance.'],
        ]],
        'Health & Fitness' => ['#2F8F6B', [
            ['24-Head Fascia Massage Gun', 'Afya', 139000, 179000, 7, 'Relieves muscle pain after work or training. 6 speeds and 24 heads.'],
            ['Digital Blood Pressure Monitor', 'Afya', 68000, null, 15, 'Upper-arm monitor with memory for two users.'],
            ['Adjustable Dumbbell Set 20kg', 'Nguvu', 145000, null, 9, 'Two dumbbells with removable plates for home workouts.'],
        ]],
        'Groceries & Supermarket' => ['#5E8C2F', [
            ['Basmati Rice 5kg', 'Shamba', 22000, null, 100, 'Long grain basmati rice.'],
            ['Sunflower Cooking Oil 5L', 'Shamba', 38000, 42000, 60, 'Pure sunflower oil, made in Tanzania.'],
            ['Kilimanjaro Ground Coffee 500g', 'Kahawa', 16000, null, 70, 'Medium roast Arabica coffee from the slopes of Kilimanjaro.'],
        ]],
        'Baby & Kids' => ['#C2618B', [
            ['Baby Diapers Size 4 (64 pcs)', 'Mtoto', 36000, null, 50, 'Soft, leak-protected diapers for babies 9 to 14kg.'],
            ['Kids School Backpack', 'Shule', 25000, 30000, 45, 'Padded straps and three compartments.'],
            ['Baby Stroller', 'Mtoto', 260000, null, 5, 'Foldable stroller with sun canopy and storage basket.'],
        ]],
        'Automotive' => ['#3D4A55', [
            ['Car Phone Holder', 'Gari', 15000, null, 60, 'Dashboard and vent holder for phones up to 7 inches.'],
            ['Car Vacuum Cleaner', 'Gari', 42000, 55000, 20, 'Cordless vacuum for car seats and mats.'],
            ['Tyre Inflator Pump', 'Gari', 75000, null, 12, 'Digital pressure gauge, plugs into the car charger socket.'],
        ]],
        'Tools & Hardware' => ['#8A6D1F', [
            ['Cordless Drill 21V', 'Fundi', 135000, 160000, 10, 'Two batteries, charger and drill bits in a carry case.'],
            ['High-Power LED Tactical Flashlight', 'Mwanga', 88000, 95000, 25, 'Super bright long-range torch, rechargeable, waterproof.'],
            ['50W Solar Panel Kit', 'Mwanga', 180000, null, 8, 'Panel, controller and bulbs. Lights a small home without TANESCO.'],
        ]],
        'Books & Stationery' => ['#4B4E8C', [
            ['A4 Exercise Books (10 pcs)', 'Shule', 12000, null, 120, 'Ruled 96-page exercise books.'],
            ['Scientific Calculator', 'Hesabu', 35000, null, 40, 'Approved for NECTA exams. 240 functions.'],
        ]],
    ];

    /** Fashion items get size variants. */
    private const VARIANTS = [
        'Men\'s Kitenge Print Shirt' => ['Size' => ['S', 'M', 'L', 'XL', 'XXL']],
        'Women\'s Maxi Dress' => ['Size' => ['S', 'M', 'L', 'XL']],
        'Men\'s Leather Sneakers' => ['Size' => ['40', '41', '42', '43', '44', '45']],
    ];

    public function run(): void
    {
        $sort = 0;
        foreach (self::CATALOG as $categoryName => [$colour, $products]) {
            $category = Category::updateOrCreate(
                ['slug' => Str::slug($categoryName)],
                ['name' => $categoryName, 'sort_order' => $sort++, 'is_active' => true],
            );

            foreach ($products as $i => [$name, $brand, $price, $was, $stock, $short]) {
                $slug = Str::slug($name);
                $product = Product::updateOrCreate(['slug' => $slug], [
                    'category_id' => $category->id,
                    'name' => $name,
                    'brand' => $brand,
                    'sku' => 'MM-'.strtoupper(Str::substr(Str::slug($categoryName, ''), 0, 3)).'-'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
                    'short_description' => $short,
                    'description' => "<p>{$short}</p><ul><li>Genuine product, checked before dispatch</li><li>Pay on delivery in Dar es Salaam</li><li>Delivered to all regions of Tanzania</li></ul>",
                    'price' => $price,
                    'compare_at_price' => $was,
                    'stock' => $stock,
                    'is_active' => true,
                    'is_featured' => $was !== null && $i === 0,
                    'sold_count' => random_int(3, 250),
                ]);

                if (! $product->images()->exists()) {
                    $path = "products/{$slug}.svg";
                    Storage::disk('public')->put($path, $this->placeholder($name, $categoryName, $colour));
                    $product->images()->create(['path' => $path, 'sort_order' => 0]);
                }

                if (isset(self::VARIANTS[$name]) && ! $product->variants()->exists()) {
                    foreach (self::VARIANTS[$name] as $option => $values) {
                        foreach ($values as $value) {
                            $product->variants()->create([
                                'name' => "{$option}: {$value}",
                                'options' => [$option => $value],
                                'stock' => intdiv($stock, count($values)) ?: 1,
                            ]);
                        }
                    }
                }
            }
        }

        $this->seedBanners();
    }

    private function seedBanners(): void
    {
        $picks = [
            ['high-power-led-tactical-flashlight', 'Video demo'],
            ['24-head-fascia-massage-gun', 'Best seller'],
            ['43-full-hd-smart-tv', 'New arrival'],
            ['non-stick-cooking-pot-set-5-pcs', 'Kitchen deal'],
        ];

        foreach ($picks as $order => [$slug, $badge]) {
            $product = Product::where('slug', $slug)->first();
            if (! $product) {
                continue;
            }
            Banner::updateOrCreate(['product_id' => $product->id], [
                'title' => $product->name,
                'subtitle' => $product->short_description,
                'badge' => $badge,
                'button_text' => 'Buy now',
                'link' => '/product/'.$product->slug,
                'sort_order' => $order,
                'is_active' => true,
            ]);
        }
    }

    /** A simple square SVG with the product name, so the grid looks real before photos are uploaded. */
    private function placeholder(string $name, string $category, string $colour): string
    {
        $lines = explode("\n", wordwrap($name, 18, "\n", true));
        $y = 400 - (count($lines) - 1) * 32;
        $text = '';
        foreach ($lines as $n => $line) {
            $text .= '<text x="400" y="'.($y + $n * 64).'" text-anchor="middle" font-family="Arial, sans-serif" font-size="52" font-weight="700" fill="#fff">'
                .htmlspecialchars($line, ENT_XML1).'</text>';
        }
        $cat = htmlspecialchars(strtoupper($category), ENT_XML1);

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 800" width="800" height="800">
  <rect width="800" height="800" fill="{$colour}"/>
  <circle cx="680" cy="120" r="220" fill="#ffffff" opacity="0.08"/>
  <circle cx="90" cy="720" r="180" fill="#000000" opacity="0.08"/>
  <text x="400" y="170" text-anchor="middle" font-family="Arial, sans-serif" font-size="26" letter-spacing="6" fill="#fff" opacity="0.8">{$cat}</text>
  {$text}
  <text x="400" y="700" text-anchor="middle" font-family="Arial, sans-serif" font-size="24" fill="#fff" opacity="0.7">MEGA MALL TANZANIA · SAMPLE PHOTO</text>
</svg>
SVG;
    }
}
