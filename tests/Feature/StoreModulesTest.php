<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReturnStatus;
use App\Enums\ReviewStatus;
use App\Enums\StockMovementType;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductSection;
use App\Models\Promotion;
use App\Models\Region;
use App\Models\Review;
use App\Models\StockMovement;
use App\Services\InventoryService;
use App\Services\OrderService;
use App\Services\OrderWorkflow;
use App\Services\PosService;
use App\Services\ReturnService;
use App\Services\StockService;
use Database\Seeders\RegionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Purchases, damages, stock, POS, promotions, returns, refunds, reviews and home sections. */
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

    private function deliveredDarOrder(int $quantity = 2): Order
    {
        $region = Region::where('name', 'Dar es Salaam')->first();
        $order = app(OrderService::class)->place([
            'customer_name' => 'Neema Mushi',
            'phone' => '0712345678',
            'region_id' => $region->id,
            'district_id' => $region->districts()->first()->id,
            'area' => 'Sinza',
            'address' => 'Near Sinza Mori',
            'items' => [['product_id' => $this->kettle->id, 'quantity' => $quantity]],
        ]);
        $flow = app(OrderWorkflow::class);
        $flow->confirm($order);
        $flow->ship($order);
        $flow->deliver($order, cashCollected: true);

        return $order->fresh();
    }

    // ---------- Stock ----------

    public function test_purchase_adds_stock_logs_it_and_updates_cost(): void
    {
        $purchase = app(InventoryService::class)->receivePurchase(
            ['purchase_date' => now()->toDateString()],
            [['product_id' => $this->kettle->id, 'quantity' => 5, 'unit_cost' => 21000]],
        );

        $this->assertSame(15, $this->kettle->fresh()->stock);
        $this->assertSame(21000, $this->kettle->fresh()->cost_price);
        $this->assertSame(105000, $purchase->total);
        $this->assertDatabaseHas('stock_movements', ['product_id' => $this->kettle->id, 'type' => 'purchase', 'quantity' => 5, 'balance_after' => 15]);
    }

    public function test_damage_removes_stock_and_cannot_go_below_zero(): void
    {
        app(InventoryService::class)->recordDamage($this->kettle->id, null, 3, 'broken');
        $this->assertSame(7, $this->kettle->fresh()->stock);

        $this->expectException(ValidationException::class);
        app(InventoryService::class)->recordDamage($this->kettle->id, null, 50, 'lost');
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

    // ---------- POS ----------

    public function test_pos_sale_is_paid_delivered_and_takes_stock(): void
    {
        $order = app(PosService::class)->sell([['product_id' => $this->kettle->id, 'quantity' => 2]], 'cash', discount: 2000);

        $this->assertSame('pos', $order->channel);
        $this->assertSame(OrderStatus::Delivered, $order->status);
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertSame(58000, $order->total);
        $this->assertSame(8, $this->kettle->fresh()->stock);
        $this->assertSame(0, Order::query()->needsAction()->count()); // POS sales never land in the online "To do" list
    }

    public function test_pos_discount_cannot_exceed_total(): void
    {
        $this->expectException(ValidationException::class);
        app(PosService::class)->sell([['product_id' => $this->kettle->id, 'quantity' => 1]], 'cash', discount: 99999);
    }

    // ---------- Promotions ----------

    public function test_running_category_promotion_lowers_checkout_price_and_shows_in_api(): void
    {
        $promotion = Promotion::create([
            'name' => 'Kitchen week', 'discount_type' => 'percent', 'discount_value' => 20, 'applies_to' => 'categories',
            'starts_at' => now()->subHour(), 'ends_at' => now()->addDay(), 'is_active' => true, 'badge' => 'Sale',
        ]);
        $promotion->categories()->attach($this->category);

        $order = app(PosService::class)->sell([['product_id' => $this->kettle->id, 'quantity' => 1]], 'cash');
        $this->assertSame(24000, $order->total);

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

    // ---------- Returns and refunds ----------

    public function test_full_return_journey_restocks_and_refunds(): void
    {
        $order = $this->deliveredDarOrder(2);
        $this->assertSame(8, $this->kettle->fresh()->stock);
        $item = $order->items->first();

        $this->postJson('/api/returns', [
            'order_number' => $order->order_number,
            'phone' => '0712 345 678',
            'reason' => 'damaged',
            'items' => [['order_item_id' => $item->id, 'quantity' => 1]],
        ])->assertCreated()->assertJsonPath('data.refund_amount', 30000);

        $return = $order->returnRequests()->first();
        $service = app(ReturnService::class);
        $service->approve($return);
        $service->receive($return->fresh(), restock: true);
        $service->refundReturn($return->fresh(), 30000, 'mpesa', 'QX12345');

        $this->assertSame(ReturnStatus::Refunded, $return->fresh()->status);
        $this->assertSame(9, $this->kettle->fresh()->stock);
        $this->assertSame(30000, $order->fresh()->refundedAmount());
        $this->assertSame(PaymentStatus::Paid, $order->fresh()->payment_status); // only part of the order was refunded
    }

    public function test_cannot_return_more_than_bought_or_undelivered_orders(): void
    {
        $order = $this->deliveredDarOrder(1);

        $this->postJson('/api/returns', [
            'order_number' => $order->order_number, 'phone' => '0712345678', 'reason' => 'changed_mind',
            'items' => [['order_item_id' => $order->items->first()->id, 'quantity' => 2]],
        ])->assertUnprocessable();

        $region = Region::where('name', 'Dar es Salaam')->first();
        $open = app(OrderService::class)->place([
            'customer_name' => 'Juma', 'phone' => '0713000000', 'region_id' => $region->id,
            'district_id' => $region->districts()->first()->id, 'area' => 'Mbezi', 'address' => 'X',
            'items' => [['product_id' => $this->kettle->id, 'quantity' => 1]],
        ]);
        $this->postJson('/api/returns', [
            'order_number' => $open->order_number, 'phone' => '0713000000', 'reason' => 'changed_mind',
            'items' => [['order_item_id' => $open->items->first()->id, 'quantity' => 1]],
        ])->assertUnprocessable();
    }

    public function test_refund_cannot_exceed_what_was_paid(): void
    {
        $order = $this->deliveredDarOrder(1); // 30,000 + 3,000 delivery

        $this->expectException(ValidationException::class);
        app(ReturnService::class)->refundOrder($order, 40000, 'cash');
    }

    // ---------- Reviews ----------

    public function test_only_buyers_of_delivered_orders_can_review_and_reviews_wait_for_approval(): void
    {
        $order = $this->deliveredDarOrder(1);

        $this->postJson("/api/products/{$this->kettle->slug}/reviews", [
            'order_number' => $order->order_number, 'phone' => '0799999999', 'rating' => 5,
        ])->assertUnprocessable();

        $this->postJson("/api/products/{$this->kettle->slug}/reviews", [
            'order_number' => $order->order_number, 'phone' => '0712345678', 'rating' => 4, 'comment' => 'Boils fast',
        ])->assertCreated();

        $this->getJson('/api/products/'.$this->kettle->slug)->assertJsonPath('reviews.summary.count', 0);

        Review::first()->update(['status' => ReviewStatus::Approved]);

        $this->getJson('/api/products/'.$this->kettle->slug)
            ->assertJsonPath('reviews.summary.count', 1)
            ->assertJsonPath('data.review_count', 1)
            ->assertJsonPath('reviews.items.0.comment', 'Boils fast');
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
