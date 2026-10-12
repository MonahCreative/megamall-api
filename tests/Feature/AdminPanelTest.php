<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\Region;
use App\Models\User;
use App\Services\OrderService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        ];

        foreach ($pages as $page) {
            $this->actingAs($this->admin)->get($page)->assertOk();
        }

        $this->assertInstanceOf(Order::class, $order);
    }
}
