<?php

namespace Tests\Feature;

use App\Filament\Pages\SalesReports;
use App\Filament\Resources\CashUps\Pages\CreateCashUp;
use App\Models\CashUp;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Refund;
use App\Models\Region;
use App\Models\User;
use App\Services\CashUpService;
use App\Services\OrderService;
use App\Services\OrderWorkflow;
use App\Services\PosService;
use App\Services\Reports\SalesReport;
use Database\Seeders\RegionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class ReportsAndCashUpTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;

    private Product $kettle;   // sells 30,000, costs 20,000

    private Product $mixer;    // sells 100,000, costs 70,000

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([SettingSeeder::class, RegionSeeder::class]);

        $this->cashier = User::factory()->create(['name' => 'Asha Cashier']);
        $this->cashier->forceFill(['is_admin' => true])->save();
        $this->actingAs($this->cashier);

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

    private function deliverWithCash(Order $order): void
    {
        $flow = app(OrderWorkflow::class);
        $flow->confirm($order);
        $flow->ship($order->fresh());
        $flow->deliver($order->fresh(), cashCollected: true);
    }

    public function test_sales_profit_and_payment_methods(): void
    {
        app(PosService::class)->sell([['product_id' => $this->kettle->id, 'quantity' => 2]], 'cash'); // 60,000, cost 40,000
        $this->deliverWithCash($this->darOrder($this->mixer));                                        // 100,000, cost 70,000
        $this->darOrder($this->mixer);                                                                // not paid yet: not a sale
        $cancelled = $this->darOrder($this->kettle);
        app(OrderWorkflow::class)->cancel($cancelled, 'Changed mind');                                // cancelled: not a sale

        $report = new SalesReport(today(), today());
        $summary = $report->summary();

        $this->assertSame(2, $summary['orders']);
        $this->assertSame(160000, $summary['sales']);
        $this->assertSame(110000, $summary['cost']);
        $this->assertSame(50000, $summary['profit']);
        $this->assertSame(31.3, $summary['margin']);
        $this->assertSame(3, $summary['items_sold']);
        $this->assertSame(0, $summary['items_without_cost']);

        $cash = collect($report->byPaymentMethod())->firstWhere('method', 'cash');
        $this->assertSame(2, $cash['payments']);
        $this->assertSame(160000, $cash['received']);

        $this->assertSame('Stand Mixer', $report->byProduct()[0]['name']);
        $this->assertSame('Electric Kettle', $report->bestSellers()[0]['name']);
        $this->assertSame(160000, $report->byCategory()[0]['sales']);
        $this->assertSame(160000, $report->byDay()[0]['sales']); // newest day first

        $online = new SalesReport(today(), today(), 'online');
        $this->assertSame(100000, $online->summary()['sales']);
    }

    public function test_discounts_refunds_and_cost_kept_from_time_of_sale(): void
    {
        $sale = app(PosService::class)->sell([['product_id' => $this->mixer->id, 'quantity' => 1]], 'mpesa', discount: 5000);
        $this->mixer->update(['cost_price' => 90000]); // later cost change must not rewrite old profit
        Refund::create(['order_id' => $sale->id, 'amount' => 10000, 'method' => 'mpesa', 'user_id' => $this->cashier->id]);

        $summary = (new SalesReport(today(), today()))->summary();

        $this->assertSame(95000, $summary['sales']);
        $this->assertSame(25000, $summary['profit']); // 95,000 - 70,000
        $this->assertSame(10000, $summary['refunds']);
        $this->assertSame(85000, $summary['net_sales']);
    }

    public function test_slow_sellers_lists_stock_that_did_not_move(): void
    {
        app(PosService::class)->sell([['product_id' => $this->kettle->id, 'quantity' => 1]], 'cash');

        $slow = (new SalesReport(today(), today()))->slowSellers();

        $this->assertSame('Stand Mixer', $slow[0]['name']);
        $this->assertSame(0, $slow[0]['quantity']);
        $this->assertSame(20 * 70000, $slow[0]['stock_value']);
    }

    public function test_cash_up_compares_expected_and_counted(): void
    {
        $pos = app(PosService::class);
        $pos->sell([['product_id' => $this->kettle->id, 'quantity' => 2]], 'cash');   // 60,000 cash
        $pos->sell([['product_id' => $this->mixer->id, 'quantity' => 1]], 'mpesa');   // 100,000 M-Pesa
        $sale = $pos->sell([['product_id' => $this->kettle->id, 'quantity' => 1]], 'cash'); // 30,000 cash
        Refund::create(['order_id' => $sale->id, 'amount' => 30000, 'method' => 'cash', 'user_id' => $this->cashier->id]); // paid back from the drawer

        $service = app(CashUpService::class);
        $this->assertSame(['cash' => 60000, 'mpesa' => 100000], $service->expected($this->cashier->id, today()));
        $this->assertSame(3, $service->salesCount($this->cashier->id, today()));

        $cashUp = $service->close($this->cashier->id, today(), ['cash' => '58,000', 'mpesa' => 100000], 'Gave change wrongly');

        $this->assertSame(160000, $cashUp->expected_total);
        $this->assertSame(158000, $cashUp->counted_total);
        $this->assertSame(-2000, $cashUp->difference);

        $this->expectException(ValidationException::class);
        $service->close($this->cashier->id, today(), ['cash' => 60000]);
    }

    public function test_shop_runs_on_tanzania_time(): void
    {
        $this->assertSame('Africa/Dar_es_Salaam', config('app.timezone'));
        $this->assertSame('+03:00', now()->format('P'));
    }

    public function test_cash_up_form_and_reports_page_work(): void
    {
        app(PosService::class)->sell([['product_id' => $this->kettle->id, 'quantity' => 2]], 'cash');

        $this->get('/admin/cash-ups/create')->assertOk()->assertSee('What the system expects');
        $this->get('/admin/reports')->assertOk()->assertSee('Sales by day');

        Livewire::test(CreateCashUp::class)
            ->fillForm(['user_id' => $this->cashier->id, 'business_date' => today()->toDateString(), 'counted' => ['cash' => 60000]])
            ->call('create')
            ->assertHasNoFormErrors();

        $cashUp = CashUp::sole();
        $this->assertSame(0, $cashUp->difference);
        $this->get('/admin/cash-ups/'.$cashUp->id)->assertOk()->assertSee('Balanced');

        Livewire::test(SalesReports::class)
            ->callAction('export')
            ->assertFileDownloaded('mega-mall-sales-'.today()->startOfMonth()->format('Y-m-d').'-to-'.today()->format('Y-m-d').'.xlsx');
    }
}
