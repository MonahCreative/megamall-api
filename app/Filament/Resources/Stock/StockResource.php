<?php

namespace App\Filament\Resources\Stock;

use App\Filament\Resources\Stock\Pages\ListStock;
use App\Filament\Resources\StockMovements\StockMovementResource;
use App\Filament\Support\Attempt;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\StockService;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** Stock levels for every product, with a "count correction" button. Same products as the Products page. */
class StockResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $slug = 'stock';

    protected static ?string $navigationLabel = 'Stock';

    protected static ?string $modelLabel = 'stock item';

    protected static ?string $pluralModelLabel = 'Stock';

    protected static string | UnitEnum | null $navigationGroup = 'Products & Stock';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static ?int $navigationSort = 6;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['mainImage', 'category', 'variants']))
            ->defaultSort('stock')
            ->columns([
                ImageColumn::make('mainImage.path')->label('')->disk('public')->square(),
                TextColumn::make('name')
                    ->searchable(['name', 'sku'])
                    ->wrap()
                    ->description(fn (Product $record) => $record->variants->isNotEmpty()
                        ? $record->variants->map(fn ($variant) => "{$variant->name}: {$variant->stock}")->join(' · ')
                        : $record->sku),
                TextColumn::make('category.name')->label('Category')->toggleable(),
                TextColumn::make('total_stock')
                    ->label('In stock')
                    ->state(fn (Product $record) => $record->totalStock())
                    ->badge()
                    ->color(fn (int $state) => match (true) {
                        $state === 0 => 'danger',
                        $state < 5 => 'warning',
                        default => 'success',
                    }),
                TextColumn::make('cost_price')->label('Cost each')->formatStateUsing(fn ($state) => Money::tzs($state))->placeholder('-'),
                TextColumn::make('stock_value')
                    ->label('Stock value')
                    ->state(fn (Product $record) => $record->cost_price ? $record->cost_price * $record->totalStock() : null)
                    ->formatStateUsing(fn ($state) => Money::tzs($state))
                    ->placeholder('-'),
                TextColumn::make('sold_count')->label('Sold')->sortable()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('category')->relationship('category', 'name')->preload(),
                Filter::make('low')
                    ->label('Low or out of stock')
                    ->query(fn (Builder $query) => $query->where(fn ($q) => $q
                        ->where(fn ($simple) => $simple->whereDoesntHave('variants')->where('stock', '<', 5))
                        ->orWhereHas('variants', fn ($variants) => $variants->where('stock', '<', 5)))),
            ])
            ->recordActions([
                static::adjustAction(),
                Action::make('history')
                    ->label('History')
                    ->icon(Heroicon::OutlinedClock)
                    ->color('gray')
                    ->url(fn (Product $record) => StockMovementResource::getUrl('index', ['search' => $record->name])),
            ]);
    }

    public static function adjustAction(): Action
    {
        return Action::make('adjust')
            ->label('Count correction')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->modalHeading(fn (Product $record) => "Correct stock: {$record->name}")
            ->modalDescription('Enter the number you actually counted on the shelf. The difference is recorded in the stock history.')
            ->schema(fn (Product $record) => [
                Select::make('product_variant_id')
                    ->label('Size / option')
                    ->options($record->variants->mapWithKeys(fn (ProductVariant $variant) => [$variant->id => "{$variant->name} (now {$variant->stock})"]))
                    ->visible($record->variants->isNotEmpty())
                    ->required($record->variants->isNotEmpty()),
                TextInput::make('counted')
                    ->label('Counted quantity')
                    ->helperText($record->variants->isEmpty() ? "System says {$record->stock}." : null)
                    ->numeric()
                    ->minValue(0)
                    ->required(),
                TextInput::make('note')->label('Reason')->placeholder('Monthly stock count'),
            ])
            ->action(function (Product $record, array $data, Action $action) {
                Attempt::run($action, function () use ($record, $data) {
                    $variant = filled($data['product_variant_id'] ?? null) ? ProductVariant::find($data['product_variant_id']) : null;
                    $movement = app(StockService::class)->setCount($record, $variant, (int) $data['counted'], $data['note'] ?? null);
                    Notification::make()->success()
                        ->title($movement ? 'Stock corrected by '.sprintf('%+d', $movement->quantity) : 'No change: the count matches')
                        ->send();
                });
            });
    }

    public static function getPages(): array
    {
        return ['index' => ListStock::route('/')];
    }
}
