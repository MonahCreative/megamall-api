<?php

namespace App\Services\Products;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\StockService;
use App\Support\Spreadsheet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Loads products from an Excel or CSV sheet: one row per product (or per size/colour).
 *
 * - A row whose SKU (or exact name) matches an existing product UPDATES it. Empty cells keep the current value.
 * - Any other row CREATES a new product (name, category and price are then required).
 * - A row with a "variant" (size/colour) updates that size's price and stock.
 * - The whole file is checked first. If any row has a problem, nothing is saved.
 */
class ProductImporter
{
    /** Column => the headings we accept for it (compared without spaces, case or punctuation). */
    public const COLUMNS = [
        'sku' => ['sku', 'code', 'productcode', 'barcode'],
        'name' => ['name', 'product', 'productname'],
        'variant' => ['variant', 'size', 'option', 'sizecolour', 'sizecolor'],
        'category' => ['category'],
        'brand' => ['brand'],
        'price' => ['price', 'sellingprice', 'pricetzs'],
        'old_price' => ['oldprice', 'wasprice', 'compareatprice', 'oldpricetzs'],
        'cost_price' => ['cost', 'costprice', 'buyingprice', 'costpricetzs'],
        'stock' => ['stock', 'quantity', 'qty', 'instock'],
        'short_description' => ['shortdescription', 'summary'],
        'description' => ['description', 'fulldescription', 'details'],
        'image_url' => ['image', 'imageurl', 'photo', 'photourl', 'picture'],
        'active' => ['active', 'visible', 'onshop', 'status'],
    ];

    public const MAX_ROWS = 5000;

    public function __construct(private readonly StockService $stock) {}

    public function import(string $path): ImportResult
    {
        $result = new ImportResult;

        try {
            $rows = Spreadsheet::read($path);
        } catch (RuntimeException $exception) {
            $result->errors[] = $exception->getMessage();

            return $result;
        } catch (Throwable) {
            $result->errors[] = 'The file could not be read. In Excel use File > Save As and choose Excel Workbook (.xlsx) or CSV, then try again.';

            return $result;
        }

        // The first non-empty row holds the headings.
        while ($rows && $this->isEmpty($rows[0])) {
            array_shift($rows);
        }
        $columns = $this->mapHeadings(array_shift($rows) ?? []);

        if (! isset($columns['name']) && ! isset($columns['sku'])) {
            $result->errors[] = 'The first row must have column headings, including "name" or "sku". Download the template to see the layout.';

            return $result;
        }
        if (count($rows) > self::MAX_ROWS) {
            $result->errors[] = 'The file has more than '.self::MAX_ROWS.' rows. Split it into smaller files.';

            return $result;
        }

        $plan = $this->check($rows, $columns, $result);
        if ($result->failed()) {
            return $result;
        }

        DB::transaction(fn () => $this->apply($plan, $result));

        return $result;
    }

    /** Read every row and decide what it will do, collecting problems. */
    private function check(array $rows, array $columns, ImportResult $result): array
    {
        $plan = [];
        $seenSkus = [];

        foreach ($rows as $index => $cells) {
            if ($this->isEmpty($cells)) {
                continue;
            }
            $line = $index + 2; // spreadsheet row number (headings are row 1)
            $problems = [];
            $value = fn (string $column) => isset($columns[$column]) ? $this->text($cells[$columns[$column]] ?? null) : null;

            $row = ['line' => $line];
            foreach (['sku', 'name', 'variant', 'category', 'brand', 'short_description', 'description', 'image_url'] as $column) {
                $row[$column] = $value($column);
            }
            foreach (['price', 'old_price', 'cost_price', 'stock'] as $column) {
                [$row[$column], $problem] = $this->number($value($column), $column);
                if ($problem) {
                    $problems[] = $problem;
                }
            }
            [$row['active'], $problem] = $this->yesNo($value('active'));
            if ($problem) {
                $problems[] = $problem;
            }

            if ($row['sku'] !== null) {
                $key = mb_strtolower($row['sku']);
                if (isset($seenSkus[$key])) {
                    $problems[] = "SKU {$row['sku']} is also used on row {$seenSkus[$key]}";
                }
                $seenSkus[$key] = $line;
            }
            if ($row['image_url'] !== null && ! preg_match('#^https?://#i', $row['image_url'])) {
                $problems[] = 'image must be a full web link starting with https://';
            }

            // What does this row point at?
            if ($row['variant'] !== null) {
                $variant = $this->findVariant($row);
                if (! $variant) {
                    $problems[] = "size/option \"{$row['variant']}\" of \"".($row['name'] ?? $row['sku'])."\" was not found. Add sizes in the admin first";
                }
                $row['action'] = 'variant';
                $row['target'] = $variant;
            } else {
                $product = $this->findProduct($row);
                $row['action'] = $product ? 'update' : 'create';
                $row['target'] = $product;

                if (! $product) {
                    if ($row['name'] === null) {
                        $problems[] = 'name is needed for a new product'.($row['sku'] ? " (no product has SKU {$row['sku']})" : '');
                    }
                    if ($row['category'] === null) {
                        $problems[] = 'category is needed for a new product';
                    }
                    if ($row['price'] === null || $row['price'] < 1) {
                        $problems[] = 'price is needed for a new product';
                    }
                }
            }

            if ($row['old_price'] !== null && $row['price'] !== null && $row['old_price'] > 0 && $row['old_price'] <= $row['price']) {
                $problems[] = 'old price must be higher than the price (it is shown crossed out)';
            }

            foreach ($problems as $problem) {
                $result->errors[] = "Row {$line}: {$problem}.";
            }
            $plan[] = $row;
        }

        if ($plan === [] && ! $result->failed()) {
            $result->errors[] = 'The file has headings but no product rows.';
        }

        return $plan;
    }

    private function apply(array $plan, ImportResult $result): void
    {
        foreach ($plan as $row) {
            match ($row['action']) {
                'create' => $this->create($row, $result),
                'update' => $this->update($row['target'], $row, $result),
                'variant' => $this->updateVariant($row['target'], $row, $result),
            };
        }
    }

    private function create(array $row, ImportResult $result): void
    {
        $product = Product::create([
            'category_id' => $this->category($row['category'], $result)->id,
            'name' => $row['name'],
            'sku' => $row['sku'],
            'brand' => $row['brand'],
            'price' => $row['price'],
            'compare_at_price' => $row['old_price'] ?: null,
            'cost_price' => $row['cost_price'],
            'stock' => $row['stock'] ?? 0,
            'short_description' => $row['short_description'],
            'description' => $row['description'],
            'is_active' => $row['active'] ?? true,
            'is_featured' => (bool) $row['old_price'],
        ]);

        if ($row['image_url']) {
            $product->images()->create(['path' => $row['image_url'], 'sort_order' => 0]);
        }
        $this->stock->recordOpeningStock($product);
        $result->created++;
    }

    private function update(Product $product, array $row, ImportResult $result): void
    {
        $changes = array_filter([
            'name' => $row['name'],
            'sku' => $row['sku'],
            'brand' => $row['brand'],
            'price' => $row['price'],
            'compare_at_price' => $row['old_price'],
            'cost_price' => $row['cost_price'],
            'short_description' => $row['short_description'],
            'description' => $row['description'],
            'is_active' => $row['active'],
        ], fn ($value) => $value !== null);

        if (isset($changes['compare_at_price']) && $changes['compare_at_price'] === 0) {
            $changes['compare_at_price'] = null; // 0 in the sheet removes the crossed-out price
        }
        if ($row['category'] !== null) {
            $changes['category_id'] = $this->category($row['category'], $result)->id;
        }

        $product->fill($changes);
        $changed = $product->isDirty();
        $product->save();

        if ($row['image_url'] && ! $product->images()->exists()) {
            $product->images()->create(['path' => $row['image_url'], 'sort_order' => 0]);
            $changed = true;
        }

        if ($row['stock'] !== null) {
            if ($product->variants()->exists()) {
                $result->warnings[] = "Row {$row['line']}: {$product->name} has sizes/options, so its stock was not changed. Put each size on its own row with the \"variant\" column.";
            } elseif ($this->stock->setCount($product, null, $row['stock'], 'Excel import')) {
                $changed = true;
            }
        }

        $changed ? $result->updated++ : $result->unchanged++;
    }

    private function updateVariant(ProductVariant $variant, array $row, ImportResult $result): void
    {
        $changed = false;
        if ($row['price'] !== null && $row['price'] !== (int) ($variant->price ?? $variant->product->price)) {
            $variant->update(['price' => $row['price']]);
            $changed = true;
        }
        if ($row['active'] !== null && $row['active'] !== (bool) $variant->is_active) {
            $variant->update(['is_active' => $row['active']]);
            $changed = true;
        }
        if ($row['stock'] !== null && $this->stock->setCount($variant->product, $variant, $row['stock'], 'Excel import')) {
            $changed = true;
        }

        $changed ? $result->updated++ : $result->unchanged++;
    }

    private function findProduct(array $row): ?Product
    {
        if ($row['sku'] !== null) {
            return Product::where('sku', $row['sku'])->first();
        }

        // No SKU: match on the exact name, but only if exactly one product has it.
        $matches = Product::whereRaw('LOWER(name) = ?', [mb_strtolower($row['name'] ?? '')])->limit(2)->get();

        return $matches->count() === 1 ? $matches->first() : null;
    }

    private function findVariant(array $row): ?ProductVariant
    {
        if ($row['sku'] !== null && ($variant = ProductVariant::with('product')->where('sku', $row['sku'])->first())) {
            return $variant;
        }

        $product = $row['sku'] !== null ? Product::where('sku', $row['sku'])->first() : $this->findProduct($row);

        return $product?->variants()->with('product')->whereRaw('LOWER(name) = ?', [mb_strtolower($row['variant'])])->first();
    }

    private function category(string $name, ImportResult $result): Category
    {
        $category = Category::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();
        if ($category) {
            return $category;
        }

        $slug = Str::slug($name);
        $suffix = 2;
        while (Category::where('slug', $slug)->exists()) {
            $slug = Str::slug($name).'-'.$suffix++;
        }
        $result->categoriesCreated[] = $name;

        return Category::create(['name' => $name, 'slug' => $slug, 'is_active' => true]);
    }

    /** @return array<string, int> column => position in the row */
    private function mapHeadings(array $headings): array
    {
        $map = [];
        foreach ($headings as $position => $heading) {
            $key = preg_replace('/[^a-z]/', '', mb_strtolower((string) $heading));
            foreach (self::COLUMNS as $column => $aliases) {
                if (! isset($map[$column]) && in_array($key, $aliases, true)) {
                    $map[$column] = $position;
                }
            }
        }

        return $map;
    }

    private function text(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            $value = $value->format('Y-m-d');
        }
        if (is_float($value) && floor($value) === $value) {
            $value = (int) $value; // 1234.0 from Excel becomes "1234"
        }
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /** "35,000", "TZS 35000", 35000.0 -> 35000. @return array{0: ?int, 1: ?string} */
    private function number(?string $value, string $column): array
    {
        if ($value === null) {
            return [null, null];
        }
        $clean = preg_replace('/(tzs|tsh|\/=|\s|,)/i', '', $value);
        if (! is_numeric($clean) || (float) $clean < 0) {
            return [null, str_replace('_', ' ', $column)." \"{$value}\" is not a number"];
        }

        return [(int) round((float) $clean), null];
    }

    /** @return array{0: ?bool, 1: ?string} */
    private function yesNo(?string $value): array
    {
        if ($value === null) {
            return [null, null];
        }

        return match (mb_strtolower($value)) {
            'yes', 'y', '1', 'true', 'active', 'visible', 'ndiyo' => [true, null],
            'no', 'n', '0', 'false', 'hidden', 'inactive', 'hapana' => [false, null],
            default => [null, "active must be yes or no, not \"{$value}\""],
        };
    }

    private function isEmpty(array $cells): bool
    {
        foreach ($cells as $cell) {
            if (trim((string) ($cell instanceof \DateTimeInterface ? 'x' : $cell)) !== '') {
                return false;
            }
        }

        return true;
    }
}
