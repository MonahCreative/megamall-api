<?php

namespace App\Services\Products;

use App\Models\Product;
use App\Support\Spreadsheet;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Excel files for products: the full list (edit and import back) and an empty template. */
class ProductSpreadsheet
{
    public const HEADINGS = [
        'sku', 'name', 'variant', 'category', 'brand', 'price', 'old price', 'cost price', 'stock',
        'short description', 'description', 'image url', 'active',
    ];

    /** Every product, plus one row per size/colour, in the same layout the import reads. */
    public function export(): BinaryFileResponse
    {
        $rows = function () {
            $products = Product::query()->with(['category:id,name', 'mainImage', 'variants'])->orderBy('name')->orderBy('id')->lazy(200);
            foreach ($products as $product) {
                $hasVariants = $product->variants->isNotEmpty();
                yield [
                    $product->sku,
                    $product->name,
                    '',
                    $product->category?->name,
                    $product->brand,
                    $product->price,
                    $product->compare_at_price,
                    $product->cost_price,
                    $hasVariants ? '' : $product->stock,
                    $product->short_description,
                    $product->description,
                    $product->mainImage?->url(),
                    $product->is_active ? 'yes' : 'no',
                ];
                foreach ($product->variants as $variant) {
                    yield [
                        $variant->sku,
                        $product->name,
                        $variant->name,
                        $product->category?->name,
                        '',
                        $variant->price ?? $product->price,
                        '',
                        '',
                        $variant->stock,
                        '',
                        '',
                        '',
                        $variant->is_active ? 'yes' : 'no',
                    ];
                }
            }
        };

        return Spreadsheet::download('mega-mall-products-'.now()->format('Y-m-d').'.xlsx', [
            'Products' => [self::HEADINGS, $rows()],
            'How to use' => [['How to update products from this file'], array_map(fn ($line) => [$line], $this->instructions())],
        ]);
    }

    /** An empty sheet with example rows, for loading a new catalogue. */
    public function template(): BinaryFileResponse
    {
        return Spreadsheet::download('mega-mall-product-template.xlsx', [
            'Products' => [self::HEADINGS, [
                ['KTL-001', 'Electric Kettle 1.8L', '', 'Home & Kitchen', 'Sayona', 35000, 42000, 24000, 15, 'Boils water in 3 minutes, auto shut-off.', '', '', 'yes'],
                ['SHIRT-KTG', "Men's Kitenge Shirt", '', 'Fashion', '', 38000, '', 22000, '', 'Bold African print, 100% cotton.', '', '', 'yes'],
            ]],
            'How to use' => [['How to fill in this file'], array_map(fn ($line) => [$line], $this->instructions())],
        ]);
    }

    /** @return array<int, string> */
    private function instructions(): array
    {
        return [
            'One row per product. Keep the headings in row 1 exactly as they are. Delete the two example rows in the template.',
            'New products need at least: name, category and price. A new category name is created automatically.',
            'SKU is your own product code. With a SKU, importing the file again UPDATES that product instead of adding a new one.',
            'To update existing products: export from the admin, change prices or stock in Excel, then import the file back.',
            'Empty cells are left unchanged. Put 0 in "old price" to remove a crossed-out price.',
            'Prices are whole shillings: 35000 or 35,000 (no TZS needed).',
            'Stock is the number you have now. The change is recorded in Stock history as "Excel import".',
            'Sizes/colours: add them to the product in the admin first. Then each size can have its own row with the "variant" column filled (e.g. M or EU 42) to set its price and stock.',
            'image url: a full web link to a photo (https://...). Photos can also be uploaded later in the admin.',
            'active: yes = shown on the shop, no = hidden.',
            'If any row has a problem, nothing is saved and the admin lists the rows to fix.',
        ];
    }
}
