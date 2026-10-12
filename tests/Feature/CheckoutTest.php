<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Region;
use Database\Seeders\RegionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([SettingSeeder::class, RegionSeeder::class]);

        $category = Category::create(['name' => 'Home & Kitchen', 'slug' => 'home-kitchen']);
        $this->product = Product::create([
            'category_id' => $category->id, 'name' => 'Electric Kettle', 'price' => 32000, 'stock' => 5,
        ]);
    }

    private function orderFor(string $regionName, array $overrides = []): \Illuminate\Testing\TestResponse
    {
        $region = Region::where('name', $regionName)->first();

        return $this->postJson('/api/orders', array_merge([
            'customer_name' => 'Neema Mushi',
            'phone' => '0712 345 678',
            'region_id' => $region->id,
            'district_id' => $region->districts()->first()?->id,
            'area' => 'Sinza',
            'address' => 'Near Sinza Mori bus stop',
            'items' => [['product_id' => $this->product->id, 'quantity' => 2]],
        ], $overrides));
    }

    public function test_dar_es_salaam_orders_are_cash_on_delivery(): void
    {
        $this->orderFor('Dar es Salaam')
            ->assertCreated()
            ->assertJsonPath('next_step', 'done')
            ->assertJsonPath('data.payment_mode', 'cod')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.subtotal', 64000);

        $this->assertSame(3, $this->product->fresh()->stock);
    }

    public function test_other_regions_must_pay_first(): void
    {
        $this->orderFor('Mwanza')
            ->assertCreated()
            ->assertJsonPath('next_step', 'pay')
            ->assertJsonPath('data.payment_mode', 'prepaid')
            ->assertJsonPath('data.status', 'awaiting_payment')
            ->assertJsonPath('data.delivery_fee', 12000)
            ->assertJsonPath('data.total', 76000);
    }

    public function test_mobile_money_payment_confirms_the_order(): void
    {
        config(['payments.fake.approve_after' => 0]);
        $res = $this->orderFor('Arusha')->assertCreated();
        $number = $res->json('data.order_number');
        $token = $res->json('data.access_token');

        $this->postJson("/api/orders/{$number}/pay", ['token' => $token, 'channel' => 'mpesa', 'phone' => '0754000111'])
            ->assertOk()
            ->assertJsonPath('data.payment.status', 'pending');

        $this->getJson("/api/orders/{$number}/payment-status?token={$token}")
            ->assertOk()
            ->assertJsonPath('data.payment_status', 'paid')
            ->assertJsonPath('data.status', 'confirmed');
    }

    public function test_cod_orders_cannot_be_sent_to_mobile_money(): void
    {
        $res = $this->orderFor('Dar es Salaam');
        $this->postJson('/api/orders/'.$res->json('data.order_number').'/pay', [
            'token' => $res->json('data.access_token'), 'channel' => 'mpesa', 'phone' => '0754000111',
        ])->assertUnprocessable();
    }

    public function test_prices_come_from_the_server_not_the_browser(): void
    {
        $this->orderFor('Dar es Salaam', [
            'items' => [['product_id' => $this->product->id, 'quantity' => 1, 'price' => 1]],
        ])->assertJsonPath('data.subtotal', 32000);
    }

    public function test_cannot_order_more_than_stock(): void
    {
        $this->orderFor('Dar es Salaam', ['items' => [['product_id' => $this->product->id, 'quantity' => 6]]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items.0.quantity');
    }

    public function test_unpaid_orders_expire_and_release_stock(): void
    {
        $this->orderFor('Mbeya')->assertCreated();
        $this->assertSame(3, $this->product->fresh()->stock);

        Carbon::setTestNow(now()->addHours(2));
        $this->artisan('orders:expire-unpaid')->assertSuccessful();

        $this->assertSame('cancelled', Order::first()->status->value);
        $this->assertSame(5, $this->product->fresh()->stock);
    }

    public function test_track_order_needs_matching_phone(): void
    {
        $number = $this->orderFor('Dar es Salaam')->json('data.order_number');

        $this->postJson('/api/orders/track', ['order_number' => $number, 'phone' => '+255712345678'])->assertOk();
        $this->postJson('/api/orders/track', ['order_number' => $number, 'phone' => '0799999999'])->assertNotFound();
    }
}
