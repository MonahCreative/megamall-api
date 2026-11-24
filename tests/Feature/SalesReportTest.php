<?php

namespace Tests\Feature;

use App\Filament\Pages\SalesReports;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Region;
use App\Models\User;
use App\Services\OrderService;
use App\Services\OrderWorkflow;
use App\Services\Reports\SalesReport;
use Database\Seeders\RegionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SalesReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Product $kettle;   // sells 30,000, costs 20,000

    private Product $mixer;    // sells 100,000, costs 70,000

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([SettingSeeder::class, RegionSeeder::class]);

        $this->admin = User::factory()->create(['name' => 'Asha Manager']);
        $this->admin->forceFill(['is_admin' => true])->save();
        $this->actingAs($this->admin);

        $kitchen = Category::create(['name' => 'Home & Kitchen', 'slug' => 'home-kitchen']);
        $this->kettle = Product::create(['category_id' => $kitchen->id, 'name' => 'Electric Kettle', 'price' => 30000, 'cost_price' => 20000, 'stock' => 20]);
        $this->mixer = Product::create(['category_id' => $kitchen->id, 'name' => 'Stand Mixer', 'price' => 100000, 'cost_price' => 70000, 'stock' => 20]);
    }

    private function darOrder(Product $product, int $quantity = 1): Order
    {
        $region = Region::where('name', 'Dar es Salaam')->first();

        return app(OrderService::class)->place([
            'customer_name' => 'Neema Mushi', 'phone' => '0712345678', 'region_id' => $region->id,
            'district_id' => $region->districts()->first()->id, 'area' => 'Sinza', 'address' => 'Near Sinza Mori',
            'items' => [['product_id' => $product->id, 'quantity' => $quantity]],
        ]);
    }

    private function deliver(Order $order, string $channel = 'cash'): Order
    {
        $flow = app(OrderWorkflow::class);
        $flow->confirm($order);
        $flow->ship($order->fresh());
        $flow->deliver($order->fresh(), cashCollected: true, channel: $channel);

        return $order->fresh();
    }

    public function test_sales_profit_and_payment_methods(): void
    {
        $this->deliver($this->darOrder($this->kettle, 2));            // 60,000, cost 40,000
        $this->deliver($this->darOrder($this->mixer), 'mpesa');       // 100,000, cost 70,000
        $this->darOrder($this->mixer);                                // not paid yet: not a sale
        app(OrderWorkflow::class)->cancel($this->darOrder($this->kettle), 'Changed mind'); // cancelled: not a sale

        $report = new SalesReport(today(), today());
        $summary = $report->summary();

        $this->assertSame(2, $summary['orders']);
        $this->assertSame(160000, $summary['sales']);
        $this->assertSame(110000, $summary['cost']);
        $this->assertSame(50000, $summary['profit']);
        $this->assertSame(31.3, $summary['margin']);
        $this->assertSame(3, $summary['items_sold']);
        $this->assertSame(0, $summary['items_without_cost']);

        $methods = collect($report->byPaymentMethod())->keyBy('method');
        $this->assertSame(60000, $methods['cash']['received']);
        $this->assertSame(100000, $methods['mpesa']['received']);

        $this->assertSame('Stand Mixer', $report->byProduct()[0]['name']);
        $this->assertSame('Electric Kettle', $report->bestSellers()[0]['name']);
        $this->assertSame(160000, $report->byCategory()[0]['sales']);
        $this->assertSame(160000, $report->byDay()[0]['sales']); // newest day first
    }

    public function test_refunds_and_cost_kept_from_time_of_sale(): void
    {
        $order = $this->deliver($this->darOrder($this->mixer));
        $this->mixer->update(['cost_price' => 90000]); // later cost change must not rewrite old profit
        app(OrderWorkflow::class)->refund($order, 10000, 'mpesa', null, 'Scratched');

        $summary = (new SalesReport(today(), today()))->summary();

        $this->assertSame(100000, $summary['sales']);
        $this->assertSame(30000, $summary['profit']); // 100,000 - 70,000
        $this->assertSame(10000, $summary['refunds']);
        $this->assertSame(90000, $summary['net_sales']);
    }

    public function test_slow_sellers_lists_stock_that_did_not_move(): void
    {
        $this->deliver($this->darOrder($this->kettle));

        $slow = (new SalesReport(today(), today()))->slowSellers();

        $this->assertSame('Stand Mixer', $slow[0]['name']);
        $this->assertSame(0, $slow[0]['quantity']);
        $this->assertSame(20 * 70000, $slow[0]['stock_value']);
    }

    public function test_shop_runs_on_tanzania_time(): void
    {
        $this->assertSame('Africa/Dar_es_Salaam', config('app.timezone'));
        $this->assertSame('+03:00', now()->format('P'));
    }

    public function test_reports_page_opens_and_exports(): void
    {
        $this->deliver($this->darOrder($this->kettle, 2));

        $this->get('/admin/reports')->assertOk()->assertSee('Sales by day');

        Livewire::test(SalesReports::class)
            ->callAction('export')
            ->assertFileDownloaded('mega-mall-sales-'.today()->startOfMonth()->format('Y-m-d').'-to-'.today()->format('Y-m-d').'.xlsx');
    }
}
