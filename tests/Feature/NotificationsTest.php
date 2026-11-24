<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Livewire\NewOrderAlert;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Region;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\NewOrderForStaff;
use App\Services\OrderService;
use App\Services\OrderWorkflow;
use Database\Seeders\RegionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/** Staff hear about orders by email, WhatsApp and the admin pop-up. Customers get no SMS: staff call them. */
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

    public function test_cod_order_emails_staff_straight_away(): void
    {
        Setting::updateOrCreate(['key' => 'staff_alert_emails'], ['value' => 'orders@megamall.test, boss@megamall.test']);

        $order = $this->place('Dar es Salaam');

        Notification::assertSentOnDemand(NewOrderForStaff::class, function (NewOrderForStaff $notification, array $channels, AnonymousNotifiable $notifiable) use ($order) {
            return $notification->order->is($order) && $notifiable->routes['mail'] === ['orders@megamall.test', 'boss@megamall.test'];
        });
    }

    public function test_upcountry_order_emails_staff_when_placed_so_they_watch_for_the_lipa_namba_payment(): void
    {
        Setting::updateOrCreate(['key' => 'staff_alert_emails'], ['value' => 'orders@megamall.test']);

        $this->place('Mwanza');

        Notification::assertSentOnDemandTimes(NewOrderForStaff::class, 1);
    }

    public function test_order_steps_and_expiry_work_without_customer_messages(): void
    {
        $order = $this->place('Dar es Salaam');
        $flow = app(OrderWorkflow::class);
        $flow->confirm($order);
        $flow->pack($order->fresh());
        $flow->ship($order->fresh(), 'Rider Juma, 0712 000 111');
        $flow->deliver($order->fresh(), cashCollected: true);
        $this->assertSame(OrderStatus::Delivered, $order->fresh()->status);
        $this->assertTrue($order->fresh()->isPaid());

        $unpaid = $this->place('Mbeya');
        $this->travel(2)->hours();
        $this->artisan('orders:expire-unpaid')->assertSuccessful();
        $this->assertSame(OrderStatus::Cancelled, $unpaid->fresh()->status);
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
    }
}
