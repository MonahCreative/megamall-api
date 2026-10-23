<?php

namespace Tests\Feature;

use App\Filament\Pages\ReorderReport;
use App\Filament\Widgets\ReorderAlerts;
use App\Models\Category;
use App\Models\Product;
use App\Models\Region;
use App\Models\SmsMessage;
use App\Models\StockAlert;
use App\Models\Supplier;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\OrderService;
use App\Services\PosService;
use App\Services\Reports\ReorderList;
use App\Services\StockService;
use Database\Seeders\ContentPageSeeder;
use Database\Seeders\RegionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ShopExtrasAndReorderTest extends TestCase
{
    use RefreshDatabase;

    private Category $kitchen;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([SettingSeeder::class, RegionSeeder::class, ContentPageSeeder::class]);
        $this->kitchen = Category::create(['name' => 'Home & Kitchen', 'slug' => 'home-kitchen']);
        $this->admin = User::factory()->create();
        $this->admin->forceFill(['is_admin' => true])->save();
    }

    private function product(string $name, int $stock, array $extra = []): Product
    {
        return Product::create(['category_id' => $this->kitchen->id, 'name' => $name, 'price' => 30000, 'cost_price' => 20000, 'stock' => $stock, ...$extra]);
    }

    // ---------- Sitemap and saved items ----------

    public function test_sitemap_lists_products_categories_and_pages_and_products_load_by_id(): void
    {
        $kettle = $this->product('Electric Kettle', 5);
        $hidden = $this->product('Old Model', 5, ['is_active' => false]);
        $mixer = $this->product('Stand Mixer', 5);

        $this->getJson('/api/sitemap')
            ->assertOk()
            ->assertJsonCount(2, 'products')
            ->assertJsonPath('products.0.slug', 'electric-kettle')
            ->assertJsonPath('categories.0.slug', 'home-kitchen')
            ->assertJsonCount(5, 'pages');

        $this->getJson("/api/products?ids={$mixer->id},{$hidden->id},{$kettle->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data'); // hidden products never show, even if saved earlier
    }

    // ---------- Back in stock ----------

    public function test_customers_get_an_sms_when_a_sold_out_product_is_restocked(): void
    {
        $kettle = $this->product('Electric Kettle', 0);

        $this->postJson("/api/products/{$kettle->slug}/notify", ['phone' => '0712 345 678'])->assertCreated();
        $this->postJson("/api/products/{$kettle->slug}/notify", ['phone' => '0712345678'])->assertCreated(); // asking twice: one request
        $this->postJson("/api/products/{$kettle->slug}/notify", ['phone' => '12345'])->assertUnprocessable()->assertJsonValidationErrors('phone');
        $this->assertSame(1, StockAlert::waiting()->count());

        $this->actingAs($this->admin);
        $this->get('/admin/back-in-stock-requests')->assertOk()->assertSee('Electric Kettle');

        app(InventoryService::class)->receivePurchase([], [['product_id' => $kettle->id, 'quantity' => 10, 'unit_cost' => 20000]]);

        $sms = SmsMessage::where('event', 'back_in_stock')->sole();
        $this->assertSame('255712345678', $sms->to);
        $this->assertStringContainsString('Electric Kettle is back in stock', $sms->body);
        $this->assertStringContainsString('/product/electric-kettle', $sms->body);
        $this->assertSame(0, StockAlert::waiting()->count());

        // More stock arriving later does not SMS again.
        app(InventoryService::class)->receivePurchase([], [['product_id' => $kettle->id, 'quantity' => 5, 'unit_cost' => 20000]]);
        $this->assertSame(1, SmsMessage::where('event', 'back_in_stock')->count());
    }

    public function test_size_requests_wait_for_that_size(): void
    {
        $shirt = $this->product('Kitenge Shirt', 0);
        $medium = $shirt->variants()->create(['name' => 'M', 'stock' => 0]);
        $large = $shirt->variants()->create(['name' => 'L', 'stock' => 0]);

        $this->postJson("/api/products/{$shirt->slug}/notify", ['phone' => '0712345678', 'variant_id' => $large->id])->assertCreated();

        app(StockService::class)->setCount($shirt, $medium, 4);
        $this->assertSame(0, SmsMessage::count());

        app(StockService::class)->setCount($shirt, $large, 2);
        $this->assertStringContainsString('Kitenge Shirt (L)', SmsMessage::sole()->body);
    }

    // ---------- Reorder list ----------

    public function test_reorder_list_suggests_what_to_buy(): void
    {
        $this->actingAs($this->admin);
        $supplier = Supplier::create(['name' => 'Kariakoo Wholesale']);
        $fast = $this->product('Fast Seller', 40);
        app(InventoryService::class)->receivePurchase(['supplier_id' => $supplier->id], [['product_id' => $fast->id, 'quantity' => 10, 'unit_cost' => 18000]]);
        $out = $this->product('Sold Out Item', 0);
        $this->product('Plenty In Stock', 100);
        $this->product('Custom Level', 8, ['reorder_level' => 10]);

        // 50 in stock after the purchase; 40 sold at the counter -> 10 left, selling 40/30 = 1.33 a day -> 7 days left.
        app(PosService::class)->sell([['product_id' => $fast->id, 'quantity' => 40]], 'cash');

        $rows = collect(app(ReorderList::class)->rows())->keyBy('name');

        $this->assertSame('Sold Out Item', array_key_first($rows->all())); // out of stock comes first
        $this->assertSame(10, $rows['Sold Out Item']['suggested']);          // 2 x default level of 5
        $this->assertSame(10, $rows['Fast Seller']['stock']);
        $this->assertSame(7, $rows['Fast Seller']['days_left']);               // 10 / (40/30)
        $this->assertSame(30, $rows['Fast Seller']['suggested']);              // 40 for 30 days - 10 in stock
        $this->assertSame('Kariakoo Wholesale', $rows['Fast Seller']['supplier']);
        $this->assertSame(30 * 18000, $rows['Fast Seller']['estimated_cost']);
        $this->assertSame(12, $rows['Custom Level']['suggested']);             // 2 x 10 - 8
        $this->assertFalse($rows->has('Plenty In Stock'));

        $this->get('/admin')->assertOk();
        Livewire::test(ReorderAlerts::class)->assertSee('Reorder soon (3)')->assertSee('Sold Out Item');
        $this->get('/admin/reorder')->assertOk()->assertSee('Sold Out Item');
        Livewire::test(ReorderReport::class)->callAction('export')->assertFileDownloaded('mega-mall-reorder-list-'.now()->format('Y-m-d').'.xlsx');
    }

    public function test_online_orders_count_as_demand_too(): void
    {
        $kettle = $this->product('Electric Kettle', 6);
        $region = Region::where('name', 'Dar es Salaam')->first();
        app(OrderService::class)->place([
            'customer_name' => 'Neema', 'phone' => '0712345678', 'region_id' => $region->id, 'district_id' => $region->districts()->first()->id,
            'area' => 'Sinza', 'address' => 'Mori', 'items' => [['product_id' => $kettle->id, 'quantity' => 3]],
        ]);

        $row = collect(app(ReorderList::class)->rows())->firstWhere('name', 'Electric Kettle');

        $this->assertSame(3, $row['stock']);
        $this->assertSame(3, $row['sold_30_days']);
    }
}
