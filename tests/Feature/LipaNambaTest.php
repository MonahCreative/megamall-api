<?php

namespace Tests\Feature;

use App\Filament\Pages\StoreSettings;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Category;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Region;
use App\Models\Setting;
use App\Models\SmsMessage;
use App\Models\User;
use App\Notifications\NewOrderForStaff;
use App\Notifications\PaymentToCheckForStaff;
use App\Support\LipaNamba;
use Database\Seeders\RegionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Upcountry customers pay to the shop's own Lipa Namba; staff check each payment and confirm it. */
class LipaNambaTest extends TestCase
{
    use RefreshDatabase;

    private Product $kettle;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([SettingSeeder::class, RegionSeeder::class]);
        Setting::updateOrCreate(['key' => 'lipa_numbers'], ['value' => [['channel' => 'mpesa', 'number' => '5123456', 'name' => 'MEGA MALL TANZANIA']]]);
        Setting::updateOrCreate(['key' => 'staff_alert_emails'], ['value' => 'orders@megamall.co.tz']);
        Notification::fake();

        $category = Category::create(['name' => 'Home & Kitchen', 'slug' => 'home-kitchen']);
        $this->kettle = Product::create(['category_id' => $category->id, 'name' => 'Electric Kettle', 'price' => 32000, 'stock' => 10]);
        $this->admin = User::factory()->create();
        $this->admin->forceFill(['is_admin' => true])->save();
    }

    private function upcountryOrder(): Order
    {
        $region = Region::where('name', 'Mwanza')->first();
        $response = $this->postJson('/api/orders', [
            'customer_name' => 'Emmanuel Mollel', 'phone' => '0687 441 350', 'region_id' => $region->id,
            'area' => 'Nyamagana', 'address' => 'Kenyatta Road', 'items' => [['product_id' => $this->kettle->id, 'quantity' => 2]],
        ])->assertCreated();

        return Order::where('order_number', $response->json('data.order_number'))->sole();
    }

    private function sendId(Order $order, string $transactionId)
    {
        return $this->postJson("/api/orders/{$order->order_number}/lipa-namba", [
            'token' => $order->access_token, 'channel' => 'mpesa', 'phone' => '0754 000 111', 'transaction_id' => $transactionId,
        ]);
    }

    public function test_shop_shows_the_lipa_namba_and_the_pin_prompt_is_off(): void
    {
        $this->getJson('/api/store')
            ->assertJsonPath('payment_method', 'lipa_namba')
            ->assertJsonPath('lipa_numbers.0.number', '5123456')
            ->assertJsonPath('lipa_numbers.0.label', 'M-Pesa')
            ->assertJsonPath('lipa_numbers.0.name', 'MEGA MALL TANZANIA');

        $order = $this->upcountryOrder();

        // The "order placed" SMS tells the customer where to pay.
        $this->assertStringContainsString('Pay to M-Pesa Lipa Namba 5123456 (MEGA MALL TANZANIA).', SmsMessage::where('event', 'order_placed_prepaid')->sole()->body);

        $this->postJson("/api/orders/{$order->order_number}/pay", ['token' => $order->access_token, 'channel' => 'mpesa', 'phone' => '0754000111'])
            ->assertUnprocessable();
        $this->assertSame(0, Payment::count());
    }

    public function test_customer_sends_the_transaction_id_and_staff_are_told(): void
    {
        $order = $this->upcountryOrder();

        // Many customers paste the whole confirmation SMS.
        $this->sendId($order, 'qjk3x7y2ab Confirmed. Tsh64,000.00 paid to MEGA MALL TANZANIA on 16/10/26 at 1:15 PM.')
            ->assertOk()
            ->assertJsonPath('data.payment.method', 'lipa_namba')
            ->assertJsonPath('data.payment.status', 'pending')
            ->assertJsonPath('data.payment.transaction_id', 'QJK3X7Y2AB')
            ->assertJsonPath('data.needs_payment', true);

        $order->refresh();
        $this->assertSame('pending', $order->payment_status->value);
        $this->assertNull($order->expires_at); // not cancelled while staff check it

        Notification::assertSentOnDemand(PaymentToCheckForStaff::class, fn (PaymentToCheckForStaff $mail) => $mail->payment->external_id === 'QJK3X7Y2AB');
        Notification::assertSentOnDemandTimes(NewOrderForStaff::class, 1); // when the order was placed

        // The shop page keeps waiting: nobody to ask but staff.
        $this->getJson("/api/orders/{$order->order_number}/payment-status?token={$order->access_token}")
            ->assertJsonPath('data.payment.status', 'pending');

        // Sending again, or using the same payment for another order, is refused.
        $this->sendId($order, 'QJK3X7Y2AC')->assertUnprocessable()->assertJsonValidationErrors('order');
        $this->sendId($this->upcountryOrder(), 'QJK3X7Y2AB')->assertUnprocessable()->assertJsonValidationErrors('transaction_id');
        $this->sendId($this->upcountryOrder(), 'hello')->assertUnprocessable()->assertJsonValidationErrors('transaction_id');

        // The scheduler does not cancel an order whose payment is being checked.
        $this->travel(3)->hours();
        $this->artisan('orders:expire-unpaid')->assertSuccessful();
        $this->assertSame('awaiting_payment', $order->fresh()->status->value);
    }

    public function test_staff_confirm_the_payment(): void
    {
        $order = $this->upcountryOrder();
        $this->sendId($order, 'QJK3X7Y2AB')->assertOk();
        $this->actingAs($this->admin);

        $this->get('/admin/orders?tab=payment_to_check')->assertOk()->assertSee($order->order_number);
        $this->get("/admin/orders/{$order->id}")->assertOk()->assertSee('Payment to check')->assertSee('QJK3X7Y2AB');

        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->assertActionVisible('confirmLipaPayment')
            ->assertActionHidden('recordPayment')
            ->callAction('confirmLipaPayment')
            ->assertHasNoActionErrors();

        $order->refresh();
        $this->assertTrue($order->isPaid());
        $this->assertSame('confirmed', $order->status->value);
        $payment = Payment::sole();
        $this->assertSame('paid', $payment->status->value);
        $this->assertSame($this->admin->id, $payment->payload['checked_by']);
        $this->assertSame(1, SmsMessage::where('event', 'payment_received')->count());
        Notification::assertSentOnDemandTimes(NewOrderForStaff::class, 1); // only when placed: staff confirmed it themselves

        $this->get('/admin/orders?tab=payment_to_check')->assertOk()->assertDontSee($order->order_number);
    }

    public function test_payment_not_found_lets_the_customer_try_again(): void
    {
        $order = $this->upcountryOrder();
        $this->sendId($order, 'QJK3X7Y2AB')->assertOk();
        $this->actingAs($this->admin);

        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->callAction('rejectLipaPayment', data: ['reason' => 'no payment with this transaction ID'])
            ->assertHasNoActionErrors();

        $order->refresh();
        $this->assertSame('failed', $order->payment_status->value);
        $this->assertNotNull($order->expires_at); // the payment window starts again
        $sms = SmsMessage::where('event', 'payment_not_found')->sole();
        $this->assertStringContainsString('QJK3X7Y2AB', $sms->body);
        $this->assertStringContainsString('no payment with this transaction ID', $sms->body);

        $this->getJson("/api/orders/{$order->order_number}?token={$order->access_token}")
            ->assertJsonPath('data.payment.status', 'failed')
            ->assertJsonPath('data.payment.failure_reason', 'no payment with this transaction ID');

        $this->assertStringContainsString("/track?order={$order->order_number}", $sms->body);

        // The SMS link opens Track order; number + phone unlock paying from there.
        $this->postJson('/api/orders/track', ['order_number' => $order->order_number, 'phone' => '0687441350'])
            ->assertOk()
            ->assertJsonPath('data.access_token', $order->access_token);

        $this->sendId($order, 'QJK3X7Y2AC')->assertOk();
        $this->assertSame('pending', $order->fresh()->payment_status->value);
    }

    public function test_admin_sets_up_lipa_namba_in_store_settings(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(StoreSettings::class)
            ->assertFormSet(['payment_method' => 'lipa_namba'])
            ->fillForm([
                'lipa_numbers' => [
                    ['channel' => 'mpesa', 'number' => '5123456', 'name' => 'MEGA MALL TANZANIA'],
                    ['channel' => 'mixx', 'number' => '17654321', 'name' => 'MEGA MALL TZ'],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertCount(2, LipaNamba::accounts());
        $this->assertSame('Mixx by Yas', LipaNamba::accounts()[1]['label']);

        // Switching to automatic keeps the numbers for later.
        Livewire::test(StoreSettings::class)
            ->fillForm(['payment_method' => 'automatic'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('automatic', LipaNamba::method());
        $this->assertCount(2, LipaNamba::accounts());
        $this->getJson('/api/store')->assertJsonPath('payment_method', 'automatic')->assertJsonCount(0, 'lipa_numbers');

        Livewire::test(StoreSettings::class)
            ->fillForm(['payment_method' => 'lipa_namba', 'lipa_numbers' => [['channel' => 'mpesa', 'number' => '51 23', 'name' => 'X']]])
            ->call('save')
            ->assertHasFormErrors();
    }

    public function test_transaction_ids_are_read_from_what_customers_type(): void
    {
        $this->assertSame('QJK3X7Y2AB', LipaNamba::cleanTransactionId(' qjk3x7y2ab '));
        $this->assertSame('QJK3X7Y2AB', LipaNamba::cleanTransactionId('QJK3X7Y2AB Confirmed. Tsh64,000.00 paid to MEGA MALL'));
        $this->assertSame('8FG21HK0QZ', LipaNamba::cleanTransactionId('Muamala: 8FG21HK0QZ umekamilika. Umelipa Tsh 64,000'));
        $this->assertNull(LipaNamba::cleanTransactionId('I paid already'));
        $this->assertNull(LipaNamba::cleanTransactionId('12'));
    }

    public function test_unknown_callback_drivers_are_not_found(): void
    {
        $this->postJson('/api/payments/callback/lipa_namba', ['anything' => 1])->assertNotFound();
        $this->postJson('/api/payments/callback/fake', [])->assertNotFound();
    }

    public function test_staff_hear_about_new_upcountry_orders_and_record_the_payment_after_calling(): void
    {
        $order = $this->upcountryOrder();

        // Staff hear about it straight away, to watch the business phone for the money.
        Notification::assertSentOnDemand(NewOrderForStaff::class, function (NewOrderForStaff $mail) use ($order) {
            $text = implode(' ', $mail->toMail(new \stdClass)->introLines).' '.implode(' ', $mail->toMail(new \stdClass)->outroLines);

            return $mail->order->is($order) && str_contains($text, 'to be paid to your Lipa Namba') && str_contains($text, 'Record payment');
        });

        $this->actingAs($this->admin);
        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->callAction('recordPayment', data: ['channel' => 'mpesa', 'reference' => ' qjk3x7y2ab '])
            ->assertNotified('Payment recorded');

        $order->refresh();
        $this->assertTrue($order->isPaid());
        $this->assertSame('confirmed', $order->status->value);
        $this->assertSame('QJK3X7Y2AB', Payment::sole()->external_id);
        $this->assertSame(1, SmsMessage::where('event', 'payment_received')->count());

        // The same mobile money payment cannot pay a second order.
        $other = $this->upcountryOrder();
        Livewire::test(ViewOrder::class, ['record' => $other->getRouteKey()])
            ->callAction('recordPayment', data: ['channel' => 'mpesa', 'reference' => 'QJK3X7Y2AB'])
            ->assertNotified('Not allowed');
        $this->assertFalse($other->fresh()->isPaid());
    }

    public function test_cash_on_delivery_orders_are_unchanged(): void
    {
        $region = Region::where('name', 'Dar es Salaam')->first();
        $this->postJson('/api/orders', [
            'customer_name' => 'Neema Mushi', 'phone' => '0712345678', 'region_id' => $region->id, 'district_id' => $region->districts()->first()->id,
            'area' => 'Sinza', 'address' => 'Near Sinza Mori', 'items' => [['product_id' => $this->kettle->id, 'quantity' => 1]],
        ])->assertCreated()->assertJsonPath('data.payment_mode', 'cod');

        Notification::assertSentOnDemandTimes(NewOrderForStaff::class, 1);
        $this->assertSame(1, SmsMessage::where('event', 'order_placed_cod')->count());
    }
}
