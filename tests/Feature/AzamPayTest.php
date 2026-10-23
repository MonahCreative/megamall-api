<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Region;
use App\Models\SmsMessage;
use Database\Seeders\RegionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** AzamPay driver, with AzamPay's servers faked: no real request leaves the test. */
class AzamPayTest extends TestCase
{
    use RefreshDatabase;

    private Order $order;

    /** What the fake AzamPay answers to a checkout request. */
    private array $checkoutReply = [['success' => true, 'transactionId' => 'AZ-TXN-1', 'message' => 'Request successful'], 200];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([SettingSeeder::class, RegionSeeder::class]);

        config([
            'payments.driver' => 'azampay',
            'payments.azampay.environment' => 'sandbox',
            'payments.azampay.app_name' => 'megamall',
            'payments.azampay.client_id' => 'client-123',
            'payments.azampay.client_secret' => 'secret-456',
            'payments.azampay.api_key' => 'api-key-789',
            'payments.azampay.callback_key' => 'callback-secret',
        ]);

        Http::fake(fn (HttpRequest $request) => str_starts_with($request->url(), 'https://authenticator-sandbox.azampay.co.tz/')
            ? Http::response(['data' => ['accessToken' => 'token-abc', 'expire' => '2026-12-31']])
            : Http::response(...$this->checkoutReply));

        $category = Category::create(['name' => 'Home & Kitchen', 'slug' => 'home-kitchen']);
        $kettle = Product::create(['category_id' => $category->id, 'name' => 'Electric Kettle', 'price' => 32000, 'stock' => 5]);
        $region = Region::where('name', 'Mwanza')->first();

        $response = $this->postJson('/api/orders', [
            'customer_name' => 'Emmanuel Mollel', 'phone' => '0687 441 350', 'region_id' => $region->id,
            'area' => 'Nyamagana', 'address' => 'Kenyatta Road', 'items' => [['product_id' => $kettle->id, 'quantity' => 2]],
        ])->assertCreated();
        $this->order = Order::where('order_number', $response->json('data.order_number'))->sole();
    }

    private function pay(string $channel = 'mixx')
    {
        return $this->postJson("/api/orders/{$this->order->order_number}/pay", [
            'token' => $this->order->access_token, 'channel' => $channel, 'phone' => '0754 000 111',
        ]);
    }

    private function sendCallback(array $overrides = [], string $key = 'callback-secret')
    {
        $payment = Payment::where('order_id', $this->order->id)->latest('id')->first();

        return $this->postJson("/api/payments/callback/azampay?key={$key}", [
            'msisdn' => '255754000111', 'amount' => '64000', 'message' => 'Success', 'utilityref' => $payment->reference,
            'operator' => 'Tigo', 'reference' => 'AZ-REF-9', 'transactionstatus' => 'success', 'submerchantAcc' => '', ...$overrides,
        ]);
    }

    public function test_pin_prompt_is_requested_with_the_right_details(): void
    {
        $this->pay('mixx')->assertOk();

        $payment = Payment::where('order_id', $this->order->id)->sole();
        $this->assertSame('azampay', $payment->gateway);
        $this->assertSame('AZ-TXN-1', $payment->external_id);
        $this->assertSame('pending', $payment->status->value);

        Http::assertSent(fn (HttpRequest $request) => $request->url() === 'https://sandbox.azampay.co.tz/azampay/mno/checkout'
            && $request['provider'] === 'Tigo'                 // Mixx by Yas
            && $request['accountNumber'] === '255754000111'
            && $request['amount'] === '64000'
            && $request['currency'] === 'TZS'
            && $request['externalId'] === $payment->reference
            && $request->hasHeader('Authorization', 'Bearer token-abc')
            && $request->hasHeader('X-API-Key', 'api-key-789'));
    }

    public function test_successful_callback_marks_the_order_paid(): void
    {
        $this->pay()->assertOk();
        $this->sendCallback()->assertOk();
        $this->sendCallback()->assertOk(); // AzamPay may repeat the callback: no harm

        $this->order->refresh();
        $this->assertTrue($this->order->isPaid());
        $this->assertSame('confirmed', $this->order->status->value);
        $this->assertSame(1, SmsMessage::where('event', 'payment_received')->count());

        // The shop's payment page sees it too.
        $this->getJson("/api/orders/{$this->order->order_number}/payment-status?token={$this->order->access_token}")
            ->assertJsonPath('data.payment_status', 'paid');
    }

    public function test_callbacks_with_a_wrong_key_or_amount_do_not_mark_paid(): void
    {
        $this->pay()->assertOk();

        $this->sendCallback([], 'guess')->assertNotFound();
        $this->assertFalse($this->order->fresh()->isPaid());

        $this->sendCallback(['amount' => '100'])->assertOk();
        $this->assertFalse($this->order->fresh()->isPaid());
        $this->assertSame('failed', Payment::where('order_id', $this->order->id)->sole()->status->value);
    }

    public function test_declined_payment_lets_the_customer_try_again(): void
    {
        $this->pay()->assertOk();
        $this->sendCallback(['transactionstatus' => 'failure', 'message' => 'Insufficient balance'])->assertOk();

        $payment = Payment::where('order_id', $this->order->id)->sole();
        $this->assertSame('failed', $payment->status->value);
        $this->assertSame('Insufficient balance', $payment->failure_reason);
        $this->assertSame('failed', $this->order->fresh()->payment_status->value);
    }

    public function test_azampay_rejecting_the_request_shows_a_friendly_error(): void
    {
        $this->checkoutReply = [['success' => false, 'message' => 'Invalid msisdn'], 400];

        $this->pay()->assertUnprocessable()->assertJsonValidationErrors('payment');

        $this->assertSame('failed', Payment::where('order_id', $this->order->id)->sole()->status->value);
    }

    public function test_local_callback_simulator_completes_the_payment(): void
    {
        $this->pay()->assertOk();

        $this->artisan('payments:simulate-callback')->assertSuccessful();

        $this->assertTrue($this->order->fresh()->isPaid());
    }
}
