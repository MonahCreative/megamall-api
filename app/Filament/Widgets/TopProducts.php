<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use App\Support\Money;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class TopProducts extends TableWidget
{
    protected static ?int $sort = 7;

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Best-selling products')
            ->query(Product::query()->with(['mainImage', 'category'])->orderByDesc('sold_count')->limit(5))
            ->paginated(false)
            ->recordUrl(fn (Product $record) => ProductResource::getUrl('edit', ['record' => $record]))
            ->columns([
                ImageColumn::make('mainImage.path')->label('')->disk('public')->square(),
                TextColumn::make('name')->wrap()->description(fn (Product $record) => $record->category?->name),
                TextColumn::make('price')->formatStateUsing(fn ($state) => Money::tzs($state)),
                TextColumn::make('sold_count')->label('Sold'),
                TextColumn::make('stock')
                    ->badge()
                    ->color(fn (int $state) => $state === 0 ? 'danger' : ($state < 5 ? 'warning' : 'success')),
            ]);
    }
}
