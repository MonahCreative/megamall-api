<?php

namespace App\Filament\Resources\StockAlerts;

use App\Filament\Resources\StockAlerts\Pages\ListStockAlerts;
use App\Models\Setting;
use App\Models\StockAlert;
use App\Support\Phone;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use UnitEnum;

/** Customers waiting for a product to come back. When it is restocked, staff get a WhatsApp and contact them from here. */
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

    /** Opens WhatsApp with a ready message to the customer. */
    public static function whatsappUrl(StockAlert $alert): string
    {
        $product = $alert->product?->name.($alert->variant ? " ({$alert->variant->name})" : '');
        $store = Setting::getValue('store_name', config('app.name'));
        $link = rtrim((string) config('megamall.frontend_url'), '/').'/product/'.$alert->product?->slug;

        return 'https://wa.me/'.$alert->phone.'?text='.rawurlencode("Habari! {$product} is back in stock at {$store}. Order here: {$link}");
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['product', 'variant']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('product.name')->label('Product')->searchable()
                    ->description(fn (StockAlert $record) => $record->variant ? 'Size/option: '.$record->variant->name : 'Any size'),
                TextColumn::make('in_stock')
                    ->label('In stock now')
                    ->state(fn (StockAlert $record) => $record->variant ? $record->variant->stock : ($record->product?->totalStock() ?? 0))
                    ->badge()
                    ->color(fn (int $state) => $state > 0 ? 'success' : 'gray'),
                TextColumn::make('phone')->formatStateUsing(fn (string $state) => Phone::display($state))->searchable(),
                TextColumn::make('created_at')->label('Asked')->since()->sortable(),
                TextColumn::make('notified_at')
                    ->label('Contacted')
                    ->badge()
                    ->placeholder('Waiting')
                    ->formatStateUsing(fn ($state) => $state->diffForHumans())
                    ->color('success'),
            ])
            ->filters([
                TernaryFilter::make('notified_at')
                    ->label('Status')
                    ->nullable()
                    ->placeholder('All')
                    ->trueLabel('Contacted')
                    ->falseLabel('Still waiting')
                    ->default(false),
            ])
            ->recordActions([
                Action::make('whatsapp')
                    ->label('WhatsApp')
                    ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                    ->color('success')
                    ->url(fn (StockAlert $record) => static::whatsappUrl($record))
                    ->openUrlInNewTab(),
                Action::make('contacted')
                    ->label('Mark contacted')
                    ->icon(Heroicon::OutlinedCheck)
                    ->visible(fn (StockAlert $record) => $record->notified_at === null)
                    ->action(fn (StockAlert $record) => $record->update(['notified_at' => now()])),
            ])
            ->toolbarActions([
                BulkAction::make('contacted')
                    ->label('Mark contacted')
                    ->icon(Heroicon::OutlinedCheck)
                    ->action(fn (Collection $records) => $records->each->update(['notified_at' => now()])),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListStockAlerts::route('/')];
    }
}
