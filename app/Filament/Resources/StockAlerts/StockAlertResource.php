<?php

namespace App\Filament\Resources\StockAlerts;

use App\Filament\Resources\StockAlerts\Pages\ListStockAlerts;
use App\Models\StockAlert;
use App\Support\Phone;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** Customers waiting for an SMS when a product is back in stock. Restock to notify them automatically. */
class StockAlertResource extends Resource
{
    protected static ?string $model = StockAlert::class;

    protected static ?string $slug = 'back-in-stock-requests';

    protected static ?string $navigationLabel = 'Back-in-stock requests';

    protected static ?string $modelLabel = 'back-in-stock request';

    protected static string | UnitEnum | null $navigationGroup = 'Products & Stock';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedBellAlert;

    protected static ?int $navigationSort = 9;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $waiting = StockAlert::waiting()->count();

        return $waiting ? (string) $waiting : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['product', 'variant']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('product.name')->label('Product')->searchable()
                    ->description(fn (StockAlert $record) => $record->variant ? 'Size/option: '.$record->variant->name : 'Any size'),
                TextColumn::make('phone')->formatStateUsing(fn (string $state) => Phone::display($state))->searchable(),
                TextColumn::make('created_at')->label('Asked')->since()->sortable(),
                TextColumn::make('notified_at')
                    ->label('SMS')
                    ->badge()
                    ->placeholder('Waiting')
                    ->formatStateUsing(fn ($state) => 'Sent '.$state->diffForHumans())
                    ->color('success'),
            ])
            ->filters([
                TernaryFilter::make('notified_at')
                    ->label('Status')
                    ->nullable()
                    ->placeholder('All')
                    ->trueLabel('SMS sent')
                    ->falseLabel('Still waiting')
                    ->default(false),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListStockAlerts::route('/')];
    }
}
