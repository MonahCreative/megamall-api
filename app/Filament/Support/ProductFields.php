<?php

namespace App\Filament\Support;

use App\Models\Product;
use App\Models\ProductVariant;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

/** Product and size/colour pickers shared by purchases, damages and stock screens. */
class ProductFields
{
    public static function product(string $name = 'product_id', ?callable $afterChosen = null): Select
    {
        return Select::make($name)
            ->label('Product')
            ->searchable()
            ->required()
            ->live()
            ->getSearchResultsUsing(fn (string $search) => Product::query()
                ->where(fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%"))
                ->orderBy('name')
                ->limit(30)
                ->get()
                ->mapWithKeys(fn (Product $product) => [$product->id => $product->name.($product->sku ? " ({$product->sku})" : '')]))
            ->getOptionLabelUsing(fn ($value) => Product::find($value)?->name)
            ->afterStateUpdated(function (Set $set, Get $get, $state) use ($afterChosen) {
                $set('product_variant_id', null);
                if ($afterChosen) {
                    $afterChosen($set, $get, $state ? Product::find($state) : null);
                }
            });
    }

    public static function variant(string $productField = 'product_id'): Select
    {
        return Select::make('product_variant_id')
            ->label('Size / option')
            ->options(fn (Get $get) => ProductVariant::where('product_id', $get($productField))->pluck('name', 'id'))
            ->visible(fn (Get $get) => filled($get($productField)) && ProductVariant::where('product_id', $get($productField))->exists())
            ->required(fn (Get $get) => filled($get($productField)) && ProductVariant::where('product_id', $get($productField))->exists());
    }
}
