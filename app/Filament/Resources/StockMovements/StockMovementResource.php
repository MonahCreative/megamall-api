<?php

namespace App\Filament\Resources\StockMovements;

use App\Enums\StockMovementType;
use App\Filament\Resources\StockMovements\Pages\ListStockMovements;
use App\Models\StockMovement;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** Read-only log of every stock change. */
class StockMovementResource extends Resource
{
    protected static ?string $model = StockMovement::class;

    protected static ?string $navigationLabel = 'Stock history';

    protected static ?string $pluralModelLabel = 'Stock history';

    protected static string | UnitEnum | null $navigationGroup = 'Products & Stock';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?int $navigationSort = 7;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['product', 'variant', 'user']))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime('d M Y, H:i')->sortable(),
                TextColumn::make('product.name')->label('Product')->searchable()->wrap()
                    ->description(fn (StockMovement $record) => $record->variant?->name),
                TextColumn::make('type')->badge(),
                TextColumn::make('quantity')
                    ->label('Change')
                    ->formatStateUsing(fn (int $state) => sprintf('%+d', $state))
                    ->color(fn (int $state) => $state < 0 ? 'danger' : 'success')
                    ->weight('bold'),
                TextColumn::make('balance_after')->label('Stock after'),
                TextColumn::make('note')->wrap()->placeholder('-'),
                TextColumn::make('user.name')->label('By')->placeholder('Shop / system'),
            ])
            ->filters([
                SelectFilter::make('type')->options(StockMovementType::class)->multiple(),
                SelectFilter::make('product')->relationship('product', 'name')->searchable(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListStockMovements::route('/')];
    }
}
