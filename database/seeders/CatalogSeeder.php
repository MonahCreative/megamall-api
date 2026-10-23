<?php

namespace Database\Seeders;

use App\Models\Banner;
use App\Models\Category;
use App\Models\Product;
use App\Models\Promotion;
use App\Services\StockService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Demo catalogue for showing the shop: 27 everyday products across 12 categories,
 * with real photos from Unsplash (free to use under the Unsplash License, https://unsplash.com/license).
 *
 * Photos are downloaded into storage/app/public/products, so they can be edited in the admin like any upload.
 * If a download fails (no internet, or PHP missing SSL certificates), the product links to the photo online instead.
 * Replace prices, stock and photos with your real products before going live.
 */
class CatalogSeeder extends Seeder
{
    /** Category name => sort order. */
    private const CATEGORIES = [
        'Fashion', 'Phones & Accessories', 'Electronics', 'Home & Kitchen', 'Furniture', 'Beauty & Personal Care',
        'Health & Fitness', 'Groceries & Supermarket', 'Baby & Kids', 'Automotive', 'Tools & Hardware', 'Books & Stationery',
    ];

    /**
     * [category, name, brand, price, old price|null, stock, Unsplash photo id, short description, details[], sizes[]|null]
     */
    private const PRODUCTS = [
        ['Fashion', "Men's Kitenge Print Shirt", 'Mega Wear', 38000, 45000, 40, '1776435303185-95be02310cff',
            'Breathable cotton shirt with a bold African print. Made for Dar heat, smart enough for Friday.',
            ['100% cotton, soft after washing', 'Short sleeves, regular fit', 'Machine wash at 30°C'], ['S', 'M', 'L', 'XL', 'XXL']],
        ['Fashion', "Women's Printed Maxi Dress", 'Mega Wear', 55000, null, 24, '1671848633245-79cc98b0dbe8',
            'Flowing full-length dress in a colourful print, for weddings, church and evenings out.',
            ['Light, breathable fabric', 'Elastic waist for a comfortable fit', 'Length: ankle'], ['S', 'M', 'L', 'XL']],
        ['Fashion', 'Classic White Sneakers', 'StepUp', 78000, 95000, 30, '1544441892-794166f1e3be',
            'Clean low-top sneakers that go with jeans, shorts or a kitenge shirt.',
            ['Synthetic leather upper, easy to wipe clean', 'Cushioned insole for all-day walking', 'Rubber sole with good grip'], ['40', '41', '42', '43', '44', '45']],
        ['Fashion', "Women's Leather Handbag", 'Lulu', 65000, null, 18, '1624687943971-e86af76d57de',
            'Structured brown leather handbag with a zip pocket and adjustable strap.',
            ['Genuine leather', 'Inside zip pocket and phone pocket', 'Fits an A4 notebook']],

        ['Phones & Accessories', 'Smartphone 6.7" 128GB Dual SIM', 'Nova', 385000, 420000, 14, '1480694313141-fce5e697ee25',
            'Big 6.7" screen, 50MP camera and a 5000mAh battery that lasts all day. Works on Vodacom, Airtel, Yas and Halotel.',
            ['6.7" HD+ display', '128GB storage, 6GB RAM', '50MP main camera, 8MP selfie camera', 'Dual SIM, 4G', '1-year warranty']],
        ['Phones & Accessories', '20,000mAh Power Bank', 'Volt', 45000, 55000, 60, '1566554738544-d962991c3fee',
            'Charges a smartphone up to four times. Ideal for travel and power cuts.',
            ['20,000mAh capacity', 'Two USB outputs, fast charging', 'Battery level display']],
        ['Phones & Accessories', 'Wireless Bluetooth Earbuds', 'Sauti', 35000, null, 50, '1606220588913-b3aacb4d2f46',
            'True wireless earbuds with a pocket charging case and clear calls.',
            ['Bluetooth 5.3', 'Up to 24 hours with the case', 'Touch controls, built-in microphone']],

        ['Electronics', '43" Full HD Smart TV', 'Vision', 690000, 750000, 8, '1593359677879-a4bb92f829d1',
            'Stream YouTube and Netflix or watch free-to-air channels on a sharp 43" Full HD screen.',
            ['43" Full HD (1920 × 1080)', 'YouTube, Netflix and apps built in', '2 HDMI, 2 USB, Wi-Fi', 'Free-to-air digital tuner', '2-year warranty']],
        ['Electronics', 'Portable Bluetooth Speaker', 'Sauti', 120000, 140000, 15, '1589003077984-894e133dabab',
            'Loud, deep sound in a speaker you can carry to the beach or the party.',
            ['Up to 12 hours of play', 'Splash-resistant', 'Bluetooth, USB and AUX input']],
        ['Electronics', 'Smart Watch with Heart-Rate Monitor', 'Nova', 72000, 85000, 25, '1624096104992-9b4fa3a279dd',
            'Tracks your steps, sleep and heart rate, and shows calls and WhatsApp messages.',
            ['1.8" colour touch screen', 'Heart rate, steps, sleep tracking', 'Up to 7 days battery', 'Works with Android and iPhone']],

        ['Home & Kitchen', '5L Stand Mixer', 'Jikoni', 289000, 320000, 10, '1564940735784-b15466e8dc09',
            'Mix cake batter, knead dough for chapati and mandazi, and whip cream with one machine.',
            ['5 litre stainless steel bowl', '6 speeds plus pulse', 'Whisk, beater and dough hook included']],
        ['Home & Kitchen', 'Stainless Steel Cooking Pot Set (5 pcs)', 'Jikoni', 115000, 140000, 20, '1604414499020-f9ac575bc5ec',
            'Five stainless steel pots with lids for pilau, maharage and everything in between.',
            ['5 sizes from 1.5L to 6L', 'Works on gas, electric and charcoal', 'Easy to clean, rust-free']],
        ['Home & Kitchen', 'Stainless Steel Kettle 2.5L', 'Jikoni', 32000, null, 35, '1594213114663-d94db9b17125',
            'Classic stovetop kettle for chai every morning.',
            ['2.5 litre capacity', 'Heat-safe handle', 'Works on gas and electric stoves']],

        ['Furniture', '3-Seater Fabric Sofa', 'Nyumba', 1250000, 1400000, 4, '1555041469-a586c61ea9bc',
            'Comfortable three-seater sofa in soft green fabric with a solid wood frame.',
            ['Solid hardwood frame', 'High-density foam cushions', 'W 210 cm × D 85 cm × H 80 cm', 'Delivered assembled in Dar es Salaam']],
        ['Furniture', 'Ergonomic Office Chair', 'Ofisi', 210000, 245000, 12, '1612372606404-0ab33e7187ee',
            'Adjustable office chair with lumbar support for long working days.',
            ['Height adjustable', 'Breathable mesh back', 'Smooth-rolling wheels, 360° swivel']],

        ['Beauty & Personal Care', 'Shea Butter Body Lotion 400ml', 'Asili', 18000, null, 80, '1620916566398-39f1143ab7be',
            'Rich lotion with natural shea butter for soft, moisturised skin all day.',
            ['400ml', 'With shea butter and vitamin E', 'For dry and normal skin']],
        ['Beauty & Personal Care', 'Eau de Parfum 100ml', 'Marashi', 65000, 80000, 20, '1594035910387-fea47794261f',
            'Warm, long-lasting fragrance for day and evening.',
            ['100ml spray bottle', 'Lasts 6 to 8 hours', 'Gift box included']],

        ['Health & Fitness', 'Adjustable Dumbbell Set 20kg', 'Nguvu', 145000, null, 9, '1623874228601-f4193c7b1818',
            'Two dumbbells with removable plates for a full home workout.',
            ['20kg total (2 × 10kg)', 'Removable plates with secure collars', 'Non-slip grip']],

        ['Groceries & Supermarket', 'Tanzanian Arabica Coffee Beans 1kg', 'Kahawa', 28000, null, 70, '1562051036-e0eea191d42f',
            'Medium-roast Arabica from the slopes of Kilimanjaro, roasted in Tanzania.',
            ['1kg whole beans', 'Medium roast, chocolate and citrus notes', 'Grown on Kilimanjaro and Mbeya farms']],
        ['Groceries & Supermarket', 'Premium Basmati Rice 5kg', 'Shamba', 24000, 27000, 100, '1568347355280-d33fdf77d42a',
            'Long-grain basmati that cooks fluffy every time, perfect for pilau and biryani.',
            ['5kg bag', 'Long grain, aromatic', 'Cleaned and sorted']],

        ['Baby & Kids', 'Foldable Baby Stroller', 'Mtoto', 260000, 295000, 6, '1559135141-2bea6465fccf',
            'Light, foldable stroller with a sun canopy and storage basket.',
            ['Folds with one hand', 'Adjustable backrest, 5-point harness', 'For babies 0 to 3 years']],
        ['Baby & Kids', 'Kids School Backpack', 'Shule', 25000, 30000, 45, '1726726192148-af52008ff663',
            'Bright, sturdy school bag with padded straps and three compartments.',
            ['Water-resistant fabric', 'Padded back and straps', 'Fits A4 exercise books']],

        ['Automotive', 'Car Phone Holder (Dashboard Mount)', 'Gari', 15000, null, 60, '1764347923709-fc48487f2486',
            'Keep your phone in view for maps and calls while you drive.',
            ['Fits phones up to 7"', 'Dashboard and windscreen mount', '360° rotation']],

        ['Tools & Hardware', 'Cordless Drill 21V', 'Fundi', 135000, 160000, 10, '1622044939413-0b829c342434',
            'Drill into wood, metal and walls without looking for a socket.',
            ['21V lithium battery, 2 batteries included', '2 speeds, 25 torque settings', 'Charger and drill bits in a carry case']],
        ['Tools & Hardware', 'High-Power LED Tactical Flashlight', 'Mwanga', 88000, 95000, 25, '1561916960-dea3b9b0355a',
            'Very bright, long-range torch for security, night travel and power cuts.',
            ['Rechargeable by USB', 'Zoom beam, 5 light modes', 'Waterproof aluminium body']],
        ['Tools & Hardware', '50W Solar Panel Kit', 'Mwanga', 180000, null, 8, '1558449028-b53a39d100fc',
            'Panel, controller and bulbs to light a small home and charge phones without TANESCO.',
            ['50W solar panel', 'Charge controller with USB ports', '4 LED bulbs and cables included']],

        ['Books & Stationery', 'A4 Hardcover Notebooks (3 pack)', 'Shule', 15000, null, 120, '1636014724389-270c4e9c0100',
            'Three ruled hardcover notebooks for school, university or the office.',
            ['A4, 192 pages each', 'Ruled lines', 'Strong hard cover']],
    ];

    /** Home page slider: product name => badge. */
    private const BANNERS = [
        '43" Full HD Smart TV' => 'New arrival',
        '3-Seater Fabric Sofa' => 'Best seller',
        'Smartphone 6.7" 128GB Dual SIM' => 'Hot deal',
        '5L Stand Mixer' => 'Kitchen week',
    ];

    public function run(): void
    {
        foreach (self::CATEGORIES as $order => $name) {
            Category::updateOrCreate(['slug' => Str::slug($name)], ['name' => $name, 'sort_order' => $order, 'is_active' => true]);
        }

        $count = [];
        foreach (self::PRODUCTS as $row) {
            [$categoryName, $name, $brand, $price, $was, $stock, $photo, $short, $details] = $row;
            $sizes = $row[9] ?? null;
            $category = Category::where('slug', Str::slug($categoryName))->first();
            $count[$categoryName] = ($count[$categoryName] ?? 0) + 1;
            $slug = Str::slug($name);

            $product = Product::updateOrCreate(['slug' => $slug], [
                'category_id' => $category->id,
                'name' => $name,
                'brand' => $brand,
                'sku' => 'MM-'.strtoupper(Str::substr(Str::slug($categoryName, ''), 0, 3)).'-'.str_pad((string) $count[$categoryName], 3, '0', STR_PAD_LEFT),
                'short_description' => $short,
                'description' => '<p>'.e($short).'</p><ul>'.collect($details)->map(fn ($line) => '<li>'.e($line).'</li>')->join('')
                    .'</ul><p>Pay on delivery in Dar es Salaam. Delivered to all regions of Tanzania.</p>',
                'price' => $price,
                'compare_at_price' => $was,
                'cost_price' => (int) round($price * 0.68, -2),
                'stock' => $sizes ? 0 : $stock,
                'is_active' => true,
                'is_featured' => $was !== null,
                'sold_count' => random_int(5, 180),
            ]);

            if (! $product->images()->exists()) {
                $product->images()->create(['path' => $this->photo($photo, $slug), 'sort_order' => 0]);
            }

            if ($sizes && ! $product->variants()->exists()) {
                foreach ($sizes as $size) {
                    $product->variants()->create([
                        'name' => (is_numeric($size) ? 'Size: EU ' : 'Size: ').$size,
                        'options' => ['Size' => $size],
                        'stock' => max(2, intdiv($stock, count($sizes))),
                        'is_active' => true,
                    ]);
                }
            }

            app(StockService::class)->recordOpeningStock($product);
        }

        $this->seedBanners();
        $this->seedPromotion();
    }

    /**
     * Download the Unsplash photo as a square 900px JPG into public storage.
     * Returns the stored path, or the online URL if the download did not work.
     */
    private function photo(string $id, string $slug): string
    {
        $url = "https://images.unsplash.com/photo-{$id}?w=900&h=900&fit=crop&crop=entropy&q=80&fm=jpg";
        $path = "products/{$slug}.jpg";

        if (Storage::disk('public')->exists($path)) {
            return $path;
        }

        try {
            $response = Http::timeout(30)->retry(2, 500)->get($url);
            if ($response->successful() && str_starts_with((string) $response->header('Content-Type'), 'image/')) {
                Storage::disk('public')->put($path, $response->body());
                $this->command?->info("  photo saved: {$slug}");

                return $path;
            }
        } catch (Throwable $exception) {
            $this->command?->warn("  could not download the photo for {$slug} ({$exception->getMessage()}); linking to it online instead.");
        }

        return $url;
    }

    private function seedBanners(): void
    {
        $order = 0;
        foreach (self::BANNERS as $name => $badge) {
            $product = Product::where('name', $name)->first();
            if (! $product) {
                continue;
            }
            Banner::updateOrCreate(['product_id' => $product->id], [
                'title' => $product->name,
                'subtitle' => $product->short_description,
                'badge' => $badge,
                'button_text' => 'Buy now',
                'link' => '/product/'.$product->slug,
                'sort_order' => $order++,
                'is_active' => true,
            ]);
        }
    }

    /** A running "Kitchen week" sale, so the shop shows promotion badges and countdowns. */
    private function seedPromotion(): void
    {
        $promotion = Promotion::updateOrCreate(['name' => 'Kitchen week'], [
            'discount_type' => 'percent',
            'discount_value' => 15,
            'applies_to' => 'categories',
            'starts_at' => now()->startOfDay(),
            'ends_at' => now()->addDays(6)->endOfDay(),
            'is_active' => true,
            'badge' => 'Kitchen week',
        ]);
        $promotion->categories()->sync([Category::where('slug', 'home-kitchen')->value('id')]);
    }
}
