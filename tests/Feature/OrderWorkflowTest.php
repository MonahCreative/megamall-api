<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Region;
use App\Services\OrderService;
use App\Services\OrderWorkflow;
use Database\Seeders\RegionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OrderWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([SettingSeeder::class, RegionSeeder::class]);
        $category = Category::create(['name' => 'Fashion', 'slug' => 'fashion']);
        $this->product = Product::create(['category_id' => $category->id, 'name' => 'Kitenge Shirt', 'price' => 38000, 'stock' => 10]);
    }

    private function place(string $regionName): Order
    {
        $region = Region::where('name', $regionName)->first();

        return app(OrderService::class)->place([
            'customer_name' => 'Baraka Juma',
            'phone' => '0754 111 222',
            'region_id' => $region->id,
            'district_id' => $region->districts()->first()?->id,
            'area' => 'Mbezi',
            'address' => 'Near the mosque',
            'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
        ]);
    }

    public function test_dar_order_full_journey_records_cash_on_delivery(): void
    {
        $order = $this->place('Dar es Salaam');
        $flow = app(OrderWorkflow::class);

        $flow->confirm($order);
        $flow->pack($order);
        $flow->ship($order, 'Rider Juma 0712 000 111');
        $flow->deliver($order, cashCollected: true);

        $order->refresh();
        $this->assertSame(OrderStatus::Delivered, $order->status);
        $this->assertTrue($order->isPaid());
        $this->assertSame('cash', $order->payments()->first()->channel);
        $this->assertSame(38000, $order->payments()->first()->amount); // items only: the rider's delivery charge is paid to him directly
    }

    public function test_unpaid_upcountry_order_cannot_be_shipped(): void
    {
        $order = $this->place('Mwanza');

        $this->expectException(ValidationException::class);
        app(OrderWorkflow::class)->ship($order);
    }

    public function test_manual_mobile_money_payment_confirms_upcountry_order(): void
    {
        $order = $this->place('Mwanza');
        app(OrderWorkflow::class)->recordPayment($order, 'mpesa', 'QJK7H2L9');

        $order->refresh();
        $this->assertTrue($order->isPaid());
        $this->assertSame(OrderStatus::Confirmed, $order->status);
        $this->assertNull($order->expires_at);
    }

    public function test_cancel_returns_stock(): void
    {
        $order = $this->place('Arusha');
        $this->assertSame(9, $this->product->fresh()->stock);

        app(OrderWorkflow::class)->cancel($order, 'Customer changed their mind');

        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
        $this->assertSame(10, $this->product->fresh()->stock);
    }

    public function test_steps_cannot_be_skipped(): void
    {
        $order = $this->place('Dar es Salaam');

        $this->expectException(ValidationException::class);
        app(OrderWorkflow::class)->deliver($order); // still "new", not shipped
    }
}
