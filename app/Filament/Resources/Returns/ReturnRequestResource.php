<?php

namespace App\Filament\Resources\Returns;

use App\Enums\ReturnStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Returns\Pages\ListReturnRequests;
use App\Filament\Resources\Returns\Pages\ViewReturnRequest;
use App\Models\ReturnRequest;
use App\Support\Money;
use App\Support\Phone;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ReturnRequestResource extends Resource
{
    protected static ?string $model = ReturnRequest::class;

    protected static ?string $slug = 'returns';

    protected static ?string $navigationLabel = 'Return orders';

    protected static ?string $modelLabel = 'return';

    protected static string | UnitEnum | null $navigationGroup = 'Orders & POS';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedArrowUturnLeft;

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'return_number';

    public static function canCreate(): bool
    {
        return false; // created by customers on the shop, or from an order page
    }

    public static function getNavigationBadge(): ?string
    {
        $open = ReturnRequest::where('status', ReturnStatus::Requested)->count();

        return $open ? (string) $open : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Return')
                    ->columnSpan(2)
                    ->columns(3)
                    ->schema([
                        TextEntry::make('return_number')->copyable()->weight('bold'),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('created_at')->label('Requested')->dateTime('d M Y, H:i'),
                        TextEntry::make('reason')->formatStateUsing(fn (string $state) => ReturnStatus::reasons()[$state] ?? $state),
                        TextEntry::make('refund_amount')->label('Value of items')->formatStateUsing(fn ($state) => Money::tzs($state)),
                        IconEntry::make('restocked')->label('Back in stock')->boolean(),
                        TextEntry::make('details')->label('Customer says')->placeholder('-')->columnSpanFull(),
                        TextEntry::make('admin_note')->label('Staff note')->placeholder('-')->columnSpanFull(),
                    ]),
                Section::make('Order')
                    ->columnSpan(1)
                    ->schema([
                        TextEntry::make('order.order_number')
                            ->label('Order')
                            ->url(fn (ReturnRequest $record) => $record->order->isPos() ? null : OrderResource::getUrl('view', ['record' => $record->order_id])),
                        TextEntry::make('order.customer_name')->label('Customer'),
                        TextEntry::make('order.phone')->label('Phone')
                            ->formatStateUsing(fn (?string $state) => $state ? Phone::display($state) : '-'),
                    ]),
                Section::make('Items to return')
                    ->columnSpanFull()
                    ->schema([
                        RepeatableEntry::make('items')
                            ->hiddenLabel()
                            ->schema([
                                Grid::make(3)->schema([
                                    TextEntry::make('orderItem.product_name')->label('Product')->helperText(fn ($record) => $record->orderItem?->variant_name),
                                    TextEntry::make('quantity')->label('Qty'),
                                    TextEntry::make('orderItem.unit_price')->label('Price each')->formatStateUsing(fn ($state) => Money::tzs($state)),
                                ]),
                            ]),
                    ]),
                Section::make('Refunds')
                    ->columnSpanFull()
                    ->visible(fn (ReturnRequest $record) => $record->refunds()->exists())
                    ->schema([
                        RepeatableEntry::make('refunds')
                            ->hiddenLabel()
                            ->schema([
                                Grid::make(4)->schema([
                                    TextEntry::make('created_at')->label('When')->dateTime('d M Y, H:i'),
                                    TextEntry::make('amount')->formatStateUsing(fn ($state) => Money::tzs($state)),
                                    TextEntry::make('method')->formatStateUsing(fn (string $state) => ReturnActions::refundMethods()[$state] ?? $state),
                                    TextEntry::make('reference')->label('Transaction ID')->placeholder('-'),
                                ]),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('order')->withCount('items'))
            ->defaultSort('created_at', 'desc')
            ->recordUrl(fn (ReturnRequest $record) => static::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('return_number')->label('Return')->searchable()->weight('bold')
                    ->description(fn (ReturnRequest $record) => $record->created_at->diffForHumans()),
                TextColumn::make('order.order_number')->label('Order')->searchable(),
                TextColumn::make('order.customer_name')->label('Customer')->searchable(),
                TextColumn::make('reason')->formatStateUsing(fn (string $state) => ReturnStatus::reasons()[$state] ?? $state),
                TextColumn::make('refund_amount')->label('Value')->formatStateUsing(fn ($state) => Money::tzs($state)),
                TextColumn::make('status')->badge(),
            ])
            ->recordActions([
                ViewAction::make(),
                ActionGroup::make(ReturnActions::all()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReturnRequests::route('/'),
            'view' => ViewReturnRequest::route('/{record}'),
        ];
    }
}
