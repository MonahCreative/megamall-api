<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\StockMovementType;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductSection;
use App\Models\Promotion;
use App\Models\Region;
use App\Models\StockMovement;
use App\Services\InventoryService;
use App\Services\OrderService;
use App\Services\OrderWorkflow;
use App\Services\StockService;
use Database\Seeders\RegionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Stock, promotions, refunds and home sections. */
class StoreModulesTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    private Product $kettle;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([SettingSeeder::class, RegionSeeder::class]);
        $this->category = Category::create(['name' => 'Home & Kitchen', 'slug' => 'home-kitchen']);
        $this->kettle = Product::create(['category_id' => $this->category->id, 'name' => 'Electric Kettle', 'price' => 30000, 'stock' => 10]);
    }

    private function darOrder(int $quantity = 1): Order
    {
        $region = Region::where('name', 'Dar es Salaam')->first();

        return app(OrderService::class)->place([
            'customer_name' => 'Neema Mushi',
            'phone' => '0712345678',
            'region_id' => $region->id,
            'district_id' => $region->districts()->first()->id,
            'area' => 'Sinza',
            'address' => 'Near Sinza Mori',
            'items' => [['product_id' => $this->kettle->id, 'quantity' => $quantity]],
        ]);
    }

    private function deliveredDarOrder(int $quantity = 2): Order
    {
        $order = $this->darOrder($quantity);
        $flow = app(OrderWorkflow::class);
        $flow->confirm($order);
        $flow->ship($order);
        $flow->deliver($order, cashCollected: true);

        return $order->fresh();
    }

    // ---------- Stock ----------

    public function test_adding_stock_logs_it_and_updates_cost(): void
    {
        app(InventoryService::class)->receiveStock($this->kettle, null, 5, 21000, 'From Kariakoo');

        $this->assertSame(15, $this->kettle->fresh()->stock);
        $this->assertSame(21000, $this->kettle->fresh()->cost_price);
        $movement = StockMovement::where('product_id', $this->kettle->id)->sole();
        $this->assertSame(StockMovementType::Purchase, $movement->type);
        $this->assertSame('From Kariakoo', $movement->note);

        // Without a cost, the cost price stays; products with sizes need the size.
        app(InventoryService::class)->receiveStock($this->kettle->fresh(), null, 1);
        $this->assertSame(21000, $this->kettle->fresh()->cost_price);

        $shirt = Product::create(['category_id' => $this->category->id, 'name' => 'Shirt', 'price' => 20000, 'stock' => 0]);
        $shirt->variants()->create(['name' => 'M', 'stock' => 0]);
        $this->expectException(ValidationException::class);
        app(InventoryService::class)->receiveStock($shirt, null, 3);
    }

    public function test_count_correction_logs_the_difference(): void
    {
        $movement = app(StockService::class)->setCount($this->kettle, null, 8, 'Monthly count');

        $this->assertSame(-2, $movement->quantity);
        $this->assertSame(8, $this->kettle->fresh()->stock);
    }

    public function test_online_order_and_cancel_are_in_stock_history(): void
    {
        $region = Region::where('name', 'Arusha')->first();
        $order = app(OrderService::class)->place([
            'customer_name' => 'Baraka', 'phone' => '0754111222', 'region_id' => $region->id,
            'area' => 'Njiro', 'address' => 'Block C', 'items' => [['product_id' => $this->kettle->id, 'quantity' => 4]],
        ]);
        app(OrderService::class)->cancel($order, 'Not paid');

        $types = StockMovement::where('product_id', $this->kettle->id)->orderBy('id')->pluck('type')->map->value->all();
        $this->assertSame([StockMovementType::OnlineSale->value, StockMovementType::OrderCancelled->value], $types);
        $this->assertSame(10, $this->kettle->fresh()->stock);
    }

    // ---------- Promotions ----------

    public function test_running_category_promotion_lowers_checkout_price_and_shows_in_api(): void
    {
        $promotion = Promotion::create([
            'name' => 'Kitchen week', 'discount_type' => 'percent', 'discount_value' => 20, 'applies_to' => 'categories',
            'starts_at' => now()->subHour(), 'ends_at' => now()->addDay(), 'is_active' => true, 'badge' => 'Sale',
        ]);
        $promotion->categories()->attach($this->category);

        $this->assertSame(24000, $this->darOrder()->total);

        $this->getJson('/api/products/'.$this->kettle->slug)
            ->assertOk()
            ->assertJsonPath('data.price', 24000)
            ->assertJsonPath('data.compare_at_price', 30000)
            ->assertJsonPath('data.promotion.badge', 'Sale');
    }

    public function test_expired_or_switched_off_promotions_are_ignored(): void
    {
        $expired = Promotion::create([
            'name' => 'Old', 'discount_type' => 'fixed', 'discount_value' => 5000, 'applies_to' => 'all',
            'starts_at' => now()->subDays(5), 'ends_at' => now()->subDay(), 'is_active' => true,
        ]);
        Promotion::create([
            'name' => 'Off', 'discount_type' => 'fixed', 'discount_value' => 5000, 'applies_to' => 'all',
            'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(), 'is_active' => false,
        ]);

        $this->getJson('/api/products/'.$this->kettle->slug)->assertJsonPath('data.price', 30000);
        $this->assertNotNull($expired);
    }

    // ---------- Refunds ----------

    public function test_delivered_order_can_be_refunded_in_part_then_in_full(): void
    {
        $order = $this->deliveredDarOrder(2); // 60,000 paid in cash
        $flow = app(OrderWorkflow::class);
        $this->assertTrue($flow->canRefund($order));

        $flow->refund($order, 30000, 'mpesa', 'QX12345', 'One kettle returned');
        $this->assertSame(PaymentStatus::Paid, $order->fresh()->payment_status);

        $flow->refund($order->fresh(), 30000, 'cash', null, 'Second kettle returned');
        $this->assertSame(PaymentStatus::Refunded, $order->fresh()->payment_status);
        $this->assertFalse($flow->canRefund($order->fresh()));
    }

    public function test_refund_cannot_exceed_what_was_paid_or_be_given_on_unpaid_orders(): void
    {
        $order = $this->deliveredDarOrder(1);

        try {
            app(OrderWorkflow::class)->refund($order, 40000, 'cash');
            $this->fail('Refunded more than was paid.');
        } catch (ValidationException) {
            $this->assertSame(0, $order->refundedAmount());
        }

        $unpaid = $this->darOrder();
        $this->assertSame(OrderStatus::Pending, $unpaid->status);
        $this->assertFalse(app(OrderWorkflow::class)->canRefund($unpaid));
    }

    // ---------- Removed features ----------

    public function test_reviews_and_online_returns_are_gone(): void
    {
        $order = $this->deliveredDarOrder(1);

        $this->postJson("/api/products/{$this->kettle->slug}/reviews", ['order_number' => $order->order_number, 'phone' => '0712345678', 'rating' => 5])->assertNotFound();
        $this->postJson('/api/returns', ['order_number' => $order->order_number])->assertNotFound();
        $this->getJson('/api/returns/reasons')->assertNotFound();
        $this->getJson('/api/products/'.$this->kettle->slug)->assertOk()->assertJsonMissingPath('reviews')->assertJsonMissingPath('data.rating');
    }

    // ---------- Home page sections ----------

    public function test_home_sections_return_their_products(): void
    {
        $blender = Product::create(['category_id' => $this->category->id, 'name' => 'Blender', 'price' => 89000, 'compare_at_price' => 99000, 'stock' => 3]);
        $manual = ProductSection::create(['title' => 'Our picks', 'source' => 'manual', 'sort_order' => 1]);
        $manual->products()->attach($this->kettle);
        ProductSection::create(['title' => 'On sale', 'source' => 'discounted', 'sort_order' => 2]);
        ProductSection::create(['title' => 'Empty', 'source' => 'featured', 'sort_order' => 3]); // nothing featured, so hidden

        $this->getJson('/api/sections')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.title', 'Our picks')
            ->assertJsonPath('data.0.products.0.slug', $this->kettle->slug)
            ->assertJsonPath('data.1.products.0.slug', $blender->slug);
    }
}
