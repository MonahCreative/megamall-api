<?php

namespace App\Filament\Resources\Orders;

use App\Enums\OrderStatus;
use App\Enums\PaymentMode;
use App\Enums\PaymentStatus;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Order;
use App\Support\Money;
use App\Support\Phone;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use UnitEnum;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string | UnitEnum | null $navigationGroup = 'Orders & POS';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Online orders';

    protected static ?string $modelLabel = 'online order';

    protected static ?string $recordTitleAttribute = 'order_number';

    /** Red number next to "Orders" in the menu: orders staff need to act on. */
    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::query()->needsAction()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function canCreate(): bool
    {
        return false; // orders come from the shop
    }

    /** Shop-counter sales live under "POS orders". */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('channel', 'online');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Order')
                    ->columnSpan(2)
                    ->columns(3)
                    ->schema([
                        TextEntry::make('order_number')->copyable()->weight('bold'),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('created_at')->label('Placed')->dateTime('d M Y, H:i'),
                        TextEntry::make('payment_mode')->label('Payment rule')->badge(),
                        TextEntry::make('payment_status')->label('Payment')->badge(),
                        TextEntry::make('paid_at')->label('Paid at')->dateTime('d M Y, H:i')->placeholder('-'),
                        TextEntry::make('tracking_note')->label('Delivery note')->placeholder('-')->columnSpanFull(),
                    ]),
                Section::make('Customer')
                    ->columnSpan(1)
                    ->schema([
                        TextEntry::make('customer_name')->label('Name'),
                        TextEntry::make('phone')
                            ->formatStateUsing(fn (string $state) => Phone::display($state))
                            ->url(fn (Order $record) => 'tel:+'.$record->phone)
                            ->copyable()
                            ->copyableState(fn (Order $record) => '0'.substr($record->phone, 3)),
                        TextEntry::make('email')->placeholder('-'),
                    ]),
                Section::make('Deliver to')
                    ->columnSpan(2)
                    ->columns(3)
                    ->schema([
                        TextEntry::make('region.name')->label('Region'),
                        TextEntry::make('district.name')->label('District')->placeholder('-'),
                        TextEntry::make('area'),
                        TextEntry::make('address')->label('Street / landmark')->columnSpanFull(),
                        TextEntry::make('notes')->label('Customer note')->placeholder('-')->columnSpanFull(),
                    ]),
                Section::make('Totals')
                    ->columnSpan(1)
                    ->schema([
                        TextEntry::make('subtotal')->formatStateUsing(fn ($state) => Money::tzs($state)),
                        TextEntry::make('delivery_fee')->label('Delivery')->formatStateUsing(fn ($state) => Money::tzs($state))
                            ->visible(fn (Order $record) => $record->delivery_fee > 0), // only older orders charged delivery
                        TextEntry::make('total')->formatStateUsing(fn ($state) => Money::tzs($state))->weight('bold')->size('lg'),
                    ]),
                Section::make('Items')
                    ->columnSpanFull()
                    ->schema([
                        RepeatableEntry::make('items')
                            ->hiddenLabel()
                            ->schema([
                                Grid::make(4)->schema([
                                    TextEntry::make('product_name')->label('Product')
                                        ->helperText(fn ($record) => $record->variant_name),
                                    TextEntry::make('quantity')->label('Qty'),
                                    TextEntry::make('unit_price')->label('Price')->formatStateUsing(fn ($state) => Money::tzs($state)),
                                    TextEntry::make('line_total')->label('Total')->formatStateUsing(fn ($state) => Money::tzs($state)),
                                ]),
                            ]),
                    ]),
                Section::make('Payment history')
                    ->columnSpanFull()
                    ->collapsible()
                    ->visible(fn (Order $record) => $record->payments()->exists())
                    ->schema([
                        RepeatableEntry::make('payments')
                            ->hiddenLabel()
                            ->schema([
                                Grid::make(5)->schema([
                                    TextEntry::make('created_at')->label('When')->dateTime('d M, H:i'),
                                    TextEntry::make('channel')->formatStateUsing(fn (?string $state) => config("payments.channels.{$state}", ucfirst((string) $state))),
                                    TextEntry::make('amount')->formatStateUsing(fn ($state) => Money::tzs($state)),
                                    TextEntry::make('status')->badge(),
                                    TextEntry::make('external_id')->label('Transaction ID')->placeholder('-')->copyable(),
                                ]),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['region', 'district'])->withCount('items'))
            ->defaultSort('created_at', 'desc')
            ->recordUrl(fn (Order $record) => static::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('order_number')
                    ->label('Order')
                    ->searchable()
                    ->weight('bold')
                    ->description(fn (Order $record) => $record->created_at->diffForHumans()),
                TextColumn::make('customer_name')
                    ->label('Customer')
                    ->searchable()
                    ->description(fn (Order $record) => Phone::display($record->phone)),
                TextColumn::make('region.name')
                    ->label('Region')
                    ->description(fn (Order $record) => $record->district?->name ?? $record->area),
                TextColumn::make('total')
                    ->formatStateUsing(fn ($state) => Money::tzs($state))
                    ->description(fn (Order $record) => $record->items_count.' item'.($record->items_count === 1 ? '' : 's'))
                    ->sortable(),
                TextColumn::make('payment_status')->label('Payment')->badge(),
                TextColumn::make('status')->badge(),
                TextColumn::make('payment_mode')->label('Rule')->badge()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options(OrderStatus::class)->multiple(),
                SelectFilter::make('payment_status')->label('Payment')->options(PaymentStatus::class),
                SelectFilter::make('payment_mode')->label('Payment rule')->options(PaymentMode::class),
                SelectFilter::make('region')->relationship('region', 'name')->searchable()->preload(),
            ])
            ->searchPlaceholder('Order number, name or phone')
            ->recordActions([
                ViewAction::make(),
                ActionGroup::make(OrderActions::all()),
            ])
            ->toolbarActions([
                BulkAction::make('printPackingSlips')
                    ->label('Print packing slips')
                    ->icon(Heroicon::OutlinedPrinter)
                    ->deselectRecordsAfterCompletion()
                    ->action(function (Collection $records, $livewire) {
                        $url = route('print.packing-slips', ['orders' => $records->pluck('id')->implode(',')]);
                        $livewire->js('window.open('.json_encode($url).", '_blank')");
                    }),
            ]);
    }

    /** Search by phone too, accepting 0712..., +255712... etc. */
    public static function getGloballySearchableAttributes(): array
    {
        return ['order_number', 'customer_name', 'phone'];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'view' => ViewOrder::route('/{record}'),
        ];
    }
}
