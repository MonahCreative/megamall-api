<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ContentPage;
use App\Models\Order;
use App\Models\Product;
use App\Models\Region;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\OrderService;
use App\Services\Products\ProductImporter;
use App\Services\Products\ProductSpreadsheet;
use App\Support\Spreadsheet;
use Database\Seeders\ContentPageSeeder;
use Database\Seeders\RegionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PagesPrintingAndImportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Category $kitchen;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([SettingSeeder::class, RegionSeeder::class, ContentPageSeeder::class]);
        $this->admin = User::factory()->create();
        $this->admin->forceFill(['is_admin' => true])->save();
        $this->kitchen = Category::create(['name' => 'Home & Kitchen', 'slug' => 'home-kitchen']);
    }

    private function darOrder(): Order
    {
        $product = Product::create(['category_id' => $this->kitchen->id, 'name' => 'Electric Kettle', 'price' => 35000, 'stock' => 5]);
        $region = Region::where('name', 'Dar es Salaam')->first();

        return app(OrderService::class)->place([
            'customer_name' => 'Neema Mushi', 'phone' => '0712345678', 'region_id' => $region->id,
            'district_id' => $region->districts()->first()->id, 'area' => 'Sinza', 'address' => 'Near Sinza Mori',
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ]);
    }

    private function sheet(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'import-').'.xlsx';
        Spreadsheet::write($path, ['Products' => [ProductSpreadsheet::HEADINGS, $rows]]);

        return $path;
    }

    // ---------- Shop pages ----------

    public function test_shop_pages_show_with_current_shop_details_and_no_scripts(): void
    {
        $this->getJson('/api/pages')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('data.0.slug', 'about-us');

        $contact = $this->getJson('/api/pages/contact-us')->assertOk()->json('data.body');
        $this->assertStringContainsString('+255 700 000 000', $contact);
        $this->assertStringNotContainsString('{phone}', $contact);

        ContentPage::create(['title' => 'Promo', 'body' => '<p>Hi</p><script>alert(1)</script>', 'is_published' => true]);
        $this->assertStringNotContainsString('<script', $this->getJson('/api/pages/promo')->json('data.body'));

        ContentPage::where('slug', 'promo')->update(['is_published' => false]);
        $this->getJson('/api/pages/promo')->assertNotFound();

        $this->actingAs($this->admin)->get('/admin/shop-pages')->assertOk()->assertSee('Return policy');
        $this->get('/admin/shop-pages/create')->assertOk();
        $this->get('/admin/shop-pages/'.ContentPage::first()->id.'/edit')->assertOk();
    }

    public function test_page_seeder_never_overwrites_edited_pages(): void
    {
        ContentPage::where('slug', 'about-us')->update(['body' => '<p>Our own words</p>']);

        $this->seed(ContentPageSeeder::class);

        $this->assertSame('<p>Our own words</p>', ContentPage::where('slug', 'about-us')->value('body'));
    }

    // ---------- Printing ----------

    public function test_staff_can_print_invoices_and_packing_slips(): void
    {
        $order = $this->darOrder();
        $second = $this->darOrder();

        $this->get(route('print.invoice', $order))->assertRedirect('/admin/login');

        $this->actingAs($this->admin);
        $this->get(route('print.invoice', $order))->assertOk()->assertSee($order->order_number)->assertSee('PAY ON DELIVERY')->assertSee('TZS 70,000');
        $this->get(route('print.packing-slip', $order))->assertOk()->assertSee('COLLECT TZS 70,000')->assertSee('0712 345 678');
        $this->get(route('print.packing-slips', ['orders' => "{$order->id},{$second->id}"]))->assertOk()->assertSee($order->order_number)->assertSee($second->order_number);
    }

    public function test_customer_opens_own_invoice_only_with_the_right_link(): void
    {
        $order = $this->darOrder();

        $this->get("/invoice/{$order->order_number}?token={$order->access_token}")->assertOk()->assertSee('INVOICE');
        $this->get("/invoice/{$order->order_number}?token=wrong")->assertForbidden();
    }

    // ---------- Excel import / export ----------

    public function test_import_creates_products_categories_and_opening_stock(): void
    {
        $result = app(ProductImporter::class)->import($this->sheet([
            ['KTL-1', 'Electric Kettle 1.8L', '', 'Home & Kitchen', 'Sayona', '35,000', 42000, 24000, 15, 'Boils fast.', '', '', 'yes'],
            ['PB-20', 'Power Bank 20000mAh', '', 'Gadgets', 'Oraimo', 45000, '', 30000, 8, '', '', 'https://example.com/pb.jpg', 'yes'],
        ]));

        $this->assertFalse($result->failed(), implode("\n", $result->errors));
        $this->assertSame(2, $result->created);
        $this->assertSame(['Gadgets'], $result->categoriesCreated);

        $kettle = Product::where('sku', 'KTL-1')->sole();
        $this->assertSame(35000, $kettle->price);
        $this->assertSame(42000, $kettle->compare_at_price);
        $this->assertSame(15, $kettle->stock);
        $this->assertTrue(StockMovement::where('product_id', $kettle->id)->where('note', 'Opening stock')->exists());
        $this->assertSame('https://example.com/pb.jpg', Product::where('sku', 'PB-20')->sole()->images()->value('path'));
    }

    public function test_import_updates_by_sku_keeps_empty_cells_and_logs_stock(): void
    {
        $product = Product::create(['category_id' => $this->kitchen->id, 'name' => 'Electric Kettle', 'sku' => 'KTL-1', 'brand' => 'Sayona', 'price' => 30000, 'stock' => 5]);

        $csv = tempnam(sys_get_temp_dir(), 'import-').'.csv';
        file_put_contents($csv, "sku,price,stock\nKTL-1,\"32,000\",9\n");
        $result = app(ProductImporter::class)->import($csv);

        $this->assertSame(1, $result->updated);
        $product->refresh();
        $this->assertSame(32000, $product->price);
        $this->assertSame(9, $product->stock);
        $this->assertSame('Sayona', $product->brand);
        $this->assertSame(4, StockMovement::where('product_id', $product->id)->where('note', 'Excel import')->value('quantity'));
    }

    public function test_one_bad_row_stops_the_whole_import(): void
    {
        $result = app(ProductImporter::class)->import($this->sheet([
            ['', 'Good Product', '', 'Home & Kitchen', '', 10000, '', '', 1, '', '', '', 'yes'],
            ['', 'Bad Product', '', 'Home & Kitchen', '', 'abc', '', '', 1, '', '', '', 'maybe'],
            ['', '', '', '', '', 5000, '', '', '', '', '', '', ''],
        ]));

        $this->assertTrue($result->failed());
        $this->assertStringContainsString('Row 3: price "abc" is not a number.', implode(' ', $result->errors));
        $this->assertStringContainsString('Row 3: active must be yes or no', implode(' ', $result->errors));
        $this->assertStringContainsString('Row 4: name is needed for a new product', implode(' ', $result->errors));
        $this->assertSame(0, Product::count());
    }

    public function test_size_rows_update_that_size(): void
    {
        $shirt = Product::create(['category_id' => $this->kitchen->id, 'name' => 'Kitenge Shirt', 'price' => 38000, 'stock' => 0]);
        $large = $shirt->variants()->create(['name' => 'L', 'stock' => 2]);

        $result = app(ProductImporter::class)->import($this->sheet([
            ['', 'Kitenge Shirt', 'L', '', '', 40000, '', '', 7, '', '', '', ''],
        ]));

        $this->assertSame(1, $result->updated, implode("\n", $result->errors));
        $this->assertSame(7, $large->fresh()->stock);
        $this->assertSame(40000, $large->fresh()->price);
    }

    public function test_exported_file_imports_back_without_changes(): void
    {
        $product = Product::create(['category_id' => $this->kitchen->id, 'name' => 'Electric Kettle', 'sku' => 'KTL-1', 'price' => 30000, 'compare_at_price' => 35000, 'cost_price' => 20000, 'stock' => 5]);
        $shirt = Product::create(['category_id' => $this->kitchen->id, 'name' => 'Kitenge Shirt', 'price' => 38000, 'stock' => 0]);
        $shirt->variants()->create(['name' => 'M', 'stock' => 3]);

        $export = app(ProductSpreadsheet::class)->export();
        $rows = Spreadsheet::read($export->getFile()->getPathname());
        $this->assertSame(ProductSpreadsheet::HEADINGS, $rows[0]);
        $this->assertCount(4, $rows); // headings + kettle + shirt + size M

        $result = app(ProductImporter::class)->import($export->getFile()->getPathname());

        $this->assertFalse($result->failed(), implode("\n", $result->errors));
        $this->assertSame(0, $result->created);
        $this->assertSame(3, $result->unchanged);
        $this->assertSame(30000, $product->fresh()->price);

        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\BinaryFileResponse::class, app(ProductSpreadsheet::class)->template());
    }
}
