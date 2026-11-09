<?php

namespace Tests\Feature;

use App\Filament\Pages\StoreSettings;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Region;
use App\Models\Setting;
use App\Models\User;
use App\Services\PosService;
use App\Services\StaffWhatsApp;
use Database\Seeders\RegionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/** New-order alerts to staff phones on WhatsApp (CallMeBot), with CallMeBot faked. */
class StaffWhatsAppTest extends TestCase
{
    use RefreshDatabase;

    private Product $kettle;

    /** What the fake CallMeBot answers: [body, status]. */
    private array $reply = ['<p>Message queued. You will receive it in a few seconds.</p>', 200];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutDefer(); // send at once instead of after the response, so the test can see it
        $this->seed([SettingSeeder::class, RegionSeeder::class]);
        Setting::updateOrCreate(['key' => 'staff_whatsapp'], ['value' => [
            ['name' => 'Manager', 'phone' => '255712000111', 'apikey' => '111111'],
            ['name' => 'Packer', 'phone' => '255754000222', 'apikey' => '222222'],
        ]]);
        Setting::updateOrCreate(['key' => 'lipa_numbers'], ['value' => [['channel' => 'mpesa', 'number' => '5123456', 'name' => 'MEGA MALL']]]);
        Http::fake(fn () => Http::response(...$this->reply));

        $category = Category::create(['name' => 'Home & Kitchen', 'slug' => 'home-kitchen']);
        $this->kettle = Product::create(['category_id' => $category->id, 'name' => 'Electric Kettle', 'price' => 32000, 'stock' => 10]);
    }

    private function order(string $regionName): Order
    {
        $region = Region::where('name', $regionName)->first();
        $response = $this->postJson('/api/orders', [
            'customer_name' => 'Neema Mushi', 'phone' => '0687 441 350', 'region_id' => $region->id,
            'district_id' => $region->districts()->first()?->id, 'area' => 'Sinza', 'address' => 'Near Sinza Mori',
            'items' => [['product_id' => $this->kettle->id, 'quantity' => 2]],
        ])->assertCreated();

        return Order::where('order_number', $response->json('data.order_number'))->sole();
    }

    public function test_every_staff_phone_gets_a_whatsapp_for_a_new_order(): void
    {
        $order = $this->order('Dar es Salaam');

        Http::assertSentCount(2);
        Http::assertSent(fn (HttpRequest $request) => str_starts_with($request->url(), 'https://api.callmebot.com/whatsapp.php')
            && $request['phone'] === '+255712000111'
            && $request['apikey'] === '111111'
            && str_contains($request['text'], "*New order* {$order->order_number}")
            && str_contains($request['text'], '2 × Electric Kettle')
            && str_contains($request['text'], 'TZS 64,000')
            && str_contains($request['text'], 'pay on delivery')
            && str_contains($request['text'], "/admin/orders/{$order->id}"));
        Http::assertSent(fn (HttpRequest $request) => $request['phone'] === '+255754000222' && $request['apikey'] === '222222');
    }

    public function test_upcountry_orders_say_they_wait_for_the_lipa_namba_payment(): void
    {
        $this->order('Mwanza');

        Http::assertSent(fn (HttpRequest $request) => str_contains($request['text'], 'waiting for Lipa Namba payment'));
    }

    public function test_a_whatsapp_failure_never_breaks_the_order_and_shop_sales_send_nothing(): void
    {
        $this->reply = ['APIKey is invalid', 200];
        $this->order('Dar es Salaam'); // still created

        $problems = app(StaffWhatsApp::class)->sendToAll('test');
        $this->assertStringContainsString('APIKey is invalid', $problems['255712000111']);

        $this->reply = ['', 500];
        $this->order('Dar es Salaam');
        $this->assertSame(2, Order::count());

        $cashier = User::factory()->create();
        $cashier->forceFill(['is_admin' => true])->save();
        $this->actingAs($cashier);
        $sent = count(Http::recorded());
        app(PosService::class)->sell([['product_id' => $this->kettle->id, 'quantity' => 1]], 'cash');
        $this->assertCount($sent, Http::recorded());
    }

    public function test_admin_saves_numbers_and_sends_a_test(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $this->actingAs($admin);

        Livewire::test(StoreSettings::class)
            ->fillForm(['staff_whatsapp' => [['name' => 'Owner', 'phone' => '0713 999 888', 'apikey' => ' 987654 ']]])
            ->call('save')
            ->assertHasNoFormErrors()
            ->callAction('testWhatsApp')
            ->assertNotified('Sent to 0713 999 888');

        $this->assertSame([['name' => 'Owner', 'phone' => '255713999888', 'apikey' => '987654']], Setting::getValue('staff_whatsapp'));
        Http::assertSent(fn (HttpRequest $request) => $request['phone'] === '+255713999888' && str_contains($request['text'], 'test alert'));

        Livewire::test(StoreSettings::class)
            ->fillForm(['staff_whatsapp' => [['name' => 'Bad', 'phone' => '12345', 'apikey' => '1']]])
            ->call('save')
            ->assertHasFormErrors();
    }
}
