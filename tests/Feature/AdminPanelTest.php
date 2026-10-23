<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\Region;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\OrderService;
use App\Services\PosService;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoActivitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Opens every admin page once, so a broken page shows up here instead of in front of staff. */
class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Http::fake(['images.unsplash.com/*' => Http::response('fake-jpeg', 200, ['Content-Type' => 'image/jpeg'])]);
        $this->seed(DatabaseSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->forceFill(['is_admin' => true])->save();
    }

    public function test_customers_cannot_open_the_admin_panel(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)->get('/admin')->assertForbidden();
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_every_admin_page_opens(): void
    {
        $region = Region::where('name', 'Dar es Salaam')->first();
        $order = app(OrderService::class)->place([
            'customer_name' => 'Neema Mushi',
            'phone' => '0712345678',
            'region_id' => $region->id,
            'district_id' => $region->districts()->first()->id,
            'area' => 'Sinza',
            'address' => 'Near Sinza Mori',
            'items' => [['product_id' => Product::where('stock', '>', 0)->whereDoesntHave('variants')->first()->id, 'quantity' => 1]],
        ]);

        $sale = app(PosService::class)->sell([['product_id' => Product::where('stock', '>', 0)->whereDoesntHave('variants')->first()->id, 'quantity' => 1]], 'cash');
        $purchase = app(InventoryService::class)->receivePurchase([], [['product_id' => Product::first()->id, 'product_variant_id' => Product::first()->variants()->value('id'), 'quantity' => 3, 'unit_cost' => 1000]]);

        $pages = [
            '/admin',
            '/admin/orders',
            '/admin/orders/'.$order->id,
            '/admin/payments',
            '/admin/products',
            '/admin/products/create',
            '/admin/products/'.Product::first()->id.'/edit',
            '/admin/categories',
            '/admin/banners',
            '/admin/regions',
            '/admin/regions/'.$region->id.'/edit',
            '/admin/store-settings',
            '/admin/pos',
            '/admin/pos-orders',
            '/admin/pos-orders/'.$sale->id,
            '/pos-receipt/'.$sale->id,
            '/admin/returns',
            '/admin/refunds',
            '/admin/purchases',
            '/admin/purchases/create',
            '/admin/purchases/'.$purchase->id,
            '/admin/suppliers',
            '/admin/damages',
            '/admin/stock',
            '/admin/stock-movements',
            '/admin/reviews',
            '/admin/promotions',
            '/admin/product-sections',
            '/admin/reports',
            '/admin/cash-ups',
            '/admin/cash-ups/create',
            '/admin/sms-outbox',
            '/admin/shop-pages',
            '/admin/shop-pages/create',
            '/print/orders/'.$order->id.'/invoice',
            '/print/orders/'.$order->id.'/packing-slip',
            '/admin/reorder',
            '/admin/back-in-stock-requests',
        ];

        foreach ($pages as $page) {
            $this->actingAs($this->admin)->get($page)->assertOk();
        }

        $this->assertInstanceOf(Order::class, $order);
    }

    public function test_demo_catalogue_and_activity_seed_cleanly(): void
    {
        $this->seed(DemoActivitySeeder::class);

        $this->assertGreaterThanOrEqual(25, Product::count());
        $this->assertTrue(Product::first()->images()->first()->path === 'products/'.Product::first()->slug.'.jpg');
        $this->assertGreaterThan(10, Order::count());
        $this->actingAs($this->admin)->get('/admin')->assertOk();
        $this->getJson('/api/sections')->assertOk();
    }
}
