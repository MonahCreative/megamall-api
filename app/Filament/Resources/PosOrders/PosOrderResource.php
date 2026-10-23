<?php

namespace App\Filament\Resources\PosOrders;

use App\Enums\OrderStatus;
use App\Filament\Pages\PointOfSale;
use App\Filament\Resources\Orders\OrderActions;
use App\Filament\Resources\PosOrders\Pages\ListPosOrders;
use App\Filament\Resources\PosOrders\Pages\ViewPosOrder;
use App\Models\Order;
use App\Support\Money;
use App\Support\Phone;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** Sales made at the shop counter. */
class PosOrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $slug = 'pos-orders';

    protected static ?string $navigationLabel = 'POS orders';

    protected static ?string $modelLabel = 'shop sale';

    protected static string | UnitEnum | null $navigationGroup = 'Orders & POS';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'order_number';

    public static function canCreate(): bool
    {
        return false; // sales are made on the POS screen
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('channel', 'pos');
    }

    public static function receiptAction(): Action
    {
        return Action::make('receipt')
            ->label('Receipt')
            ->icon(Heroicon::OutlinedPrinter)
            ->color('gray')
            ->url(fn (Order $record) => route('pos.receipt', $record))
            ->openUrlInNewTab();
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Sale')
                    ->columnSpan(2)
                    ->columns(3)
                    ->schema([
                        TextEntry::make('order_number')->label('Receipt no.')->copyable()->weight('bold'),
                        TextEntry::make('created_at')->label('Time')->dateTime('d M Y, H:i'),
                        TextEntry::make('servedBy.name')->label('Served by')->placeholder('-'),
                        TextEntry::make('customer_name')->label('Customer'),
                        TextEntry::make('phone')->formatStateUsing(fn (?string $state) => $state ? Phone::display($state) : '-')->placeholder('-'),
                        TextEntry::make('paid_with')
                            ->label('Paid with')
                            ->state(fn (Order $record) => $record->payments->first()?->channel)
                            ->formatStateUsing(fn (?string $state) => $state === 'cash' ? 'Cash' : config("payments.channels.{$state}", ucfirst((string) $state))),
                        TextEntry::make('status')->badge(),
                    ]),
                Section::make('Totals')
                    ->columnSpan(1)
                    ->schema([
                        TextEntry::make('subtotal')->formatStateUsing(fn ($state) => Money::tzs($state)),
                        TextEntry::make('discount')->formatStateUsing(fn ($state) => Money::tzs($state)),
                        TextEntry::make('total')->formatStateUsing(fn ($state) => Money::tzs($state))->weight('bold')->size('lg'),
                    ]),
                Section::make('Items')
                    ->columnSpanFull()
                    ->schema([
                        RepeatableEntry::make('items')
                            ->hiddenLabel()
                            ->schema([
                                Grid::make(4)->schema([
                                    TextEntry::make('product_name')->label('Product')->helperText(fn ($record) => $record->variant_name),
                                    TextEntry::make('quantity')->label('Qty'),
                                    TextEntry::make('unit_price')->label('Price')->formatStateUsing(fn ($state) => Money::tzs($state)),
                                    TextEntry::make('line_total')->label('Total')->formatStateUsing(fn ($state) => Money::tzs($state)),
                                ]),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['servedBy', 'payments'])->withCount('items'))
            ->defaultSort('created_at', 'desc')
            ->recordUrl(fn (Order $record) => static::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('order_number')->label('Receipt')->searchable()->weight('bold')
                    ->description(fn (Order $record) => $record->created_at->format('d M Y, H:i')),
                TextColumn::make('customer_name')->label('Customer')->searchable(),
                TextColumn::make('items_count')->label('Items'),
                TextColumn::make('total')->formatStateUsing(fn ($state) => Money::tzs($state))->sortable(),
                TextColumn::make('paid_with')
                    ->label('Paid with')
                    ->state(fn (Order $record) => $record->payments->first()?->channel)
                    ->formatStateUsing(fn (?string $state) => $state === 'cash' ? 'Cash' : config("payments.channels.{$state}", ucfirst((string) $state))),
                TextColumn::make('servedBy.name')->label('Served by')->toggleable(),
                TextColumn::make('status')->badge()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('today')->label('Today only')->query(fn (Builder $query) => $query->whereDate('created_at', today())),
            ])
            ->headerActions([
                Action::make('newSale')->label('New sale')->icon(Heroicon::OutlinedPlus)->url(fn () => PointOfSale::getUrl()),
            ])
            ->recordActions([
                ViewAction::make(),
                static::receiptAction(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPosOrders::route('/'),
            'view' => ViewPosOrder::route('/{record}'),
        ];
    }
}
