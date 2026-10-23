<?php

namespace Tests\Feature;

use App\Livewire\NewOrderAlert;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Region;
use App\Models\Setting;
use App\Models\SmsMessage;
use App\Models\User;
use App\Notifications\NewOrderForStaff;
use App\Services\OrderService;
use App\Services\OrderWorkflow;
use App\Services\PosService;
use Database\Seeders\RegionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class NotificationsTest extends TestCase
{
    use RefreshDatabase;

    private Product $kettle;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([SettingSeeder::class, RegionSeeder::class]);
        Notification::fake();

        $category = Category::create(['name' => 'Home & Kitchen', 'slug' => 'home-kitchen']);
        $this->kettle = Product::create(['category_id' => $category->id, 'name' => 'Electric Kettle', 'price' => 32000, 'stock' => 10]);
    }

    private function place(string $regionName): Order
    {
        $region = Region::where('name', $regionName)->first();

        return app(OrderService::class)->place([
            'customer_name' => 'Neema Mushi', 'phone' => '0712 345 678', 'region_id' => $region->id,
            'district_id' => $region->districts()->first()?->id, 'area' => 'Sinza', 'address' => 'Near Sinza Mori',
            'items' => [['product_id' => $this->kettle->id, 'quantity' => 1]],
        ]);
    }

    public function test_customer_gets_an_sms_at_each_step_of_a_dar_order(): void
    {
        $order = $this->place('Dar es Salaam');
        $flow = app(OrderWorkflow::class);
        $flow->confirm($order);
        $flow->pack($order->fresh());
        $flow->ship($order->fresh(), 'Rider Juma, 0712 000 111');
        $flow->deliver($order->fresh(), cashCollected: true);

        $messages = SmsMessage::orderBy('id')->get();
        $this->assertSame(['order_placed_cod', 'order_confirmed', 'order_shipped', 'order_delivered'], $messages->pluck('event')->all());
        $this->assertSame('255712345678', $messages[0]->to);
        $this->assertSame('logged', $messages[0]->status); // no SMS account yet: saved, not sent
        $this->assertStringContainsString($order->order_number, $messages[0]->body);
        $this->assertStringStartsWith('Habari Neema!', $messages[0]->body);
        $this->assertStringContainsString('Rider Juma, 0712 000 111.', $messages[2]->body);
        $this->assertStringNotContainsString('{', $messages->pluck('body')->implode(' '));
    }

    public function test_upcountry_order_gets_payment_sms_and_staff_are_emailed_once_paid(): void
    {
        Setting::updateOrCreate(['key' => 'staff_alert_emails'], ['value' => 'orders@megamall.test, boss@megamall.test']);
        config(['payments.fake.approve_after' => 0]);

        $order = $this->place('Mwanza');
        Notification::assertNothingSent(); // not paid yet

        $this->postJson("/api/orders/{$order->order_number}/pay", ['token' => $order->access_token, 'channel' => 'mpesa', 'phone' => '0754000111'])->assertOk();
        $this->getJson("/api/orders/{$order->order_number}/payment-status?token={$order->access_token}")->assertJsonPath('data.payment_status', 'paid');

        $this->assertSame(['order_placed_prepaid', 'payment_received'], SmsMessage::orderBy('id')->pluck('event')->all());
        Notification::assertSentOnDemand(NewOrderForStaff::class, function (NewOrderForStaff $notification, array $channels, AnonymousNotifiable $notifiable) use ($order) {
            return $notification->order->is($order) && $notifiable->routes['mail'] === ['orders@megamall.test', 'boss@megamall.test'];
        });
    }

    public function test_cod_order_emails_staff_straight_away(): void
    {
        Setting::updateOrCreate(['key' => 'staff_alert_emails'], ['value' => 'orders@megamall.test']);

        $this->place('Dar es Salaam');

        Notification::assertSentOnDemandTimes(NewOrderForStaff::class, 1);
    }

    public function test_cancelled_and_expired_orders_tell_the_customer(): void
    {
        $order = $this->place('Mbeya');
        $this->travel(2)->hours();
        $this->artisan('orders:expire-unpaid')->assertSuccessful();

        $sms = SmsMessage::where('event', 'order_cancelled')->sole();
        $this->assertStringContainsString('(Not paid in time)', $sms->body);
        $this->assertSame($order->id, $sms->order_id);
    }

    public function test_shop_sales_and_switched_off_sms_send_nothing(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $this->actingAs($admin);
        app(PosService::class)->sell([['product_id' => $this->kettle->id, 'quantity' => 1]], 'cash');

        Setting::updateOrCreate(['key' => 'sms_enabled'], ['value' => false]);
        $this->place('Dar es Salaam');

        $this->assertSame(0, SmsMessage::count());
    }

    public function test_sms_wording_can_be_changed_in_settings(): void
    {
        Setting::updateOrCreate(['key' => 'sms_templates'], ['value' => ['order_placed_cod' => 'Hi {first_name}, we got {order} for {total}.']]);

        $order = $this->place('Dar es Salaam');

        $this->assertSame("Hi Neema, we got {$order->order_number} for TZS 32,000.", SmsMessage::sole()->body);
    }

    public function test_admin_pop_up_announces_each_new_order_once(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $this->actingAs($admin);

        $alert = Livewire::test(NewOrderAlert::class);
        $alert->call('check')->assertNotDispatched('mm-new-order');

        $this->place('Dar es Salaam');
        $alert->call('check')->assertDispatched('mm-new-order');
        $alert->call('check')->assertNotDispatched('mm-new-order');

        $this->get('/admin/sms-outbox')->assertOk()->assertSee('Order placed (Dar es Salaam, pay on delivery)');
    }
}
