<?php

namespace App\Filament\Resources\Refunds;

use App\Filament\Resources\Refunds\Pages\ListRefunds;
use App\Models\Refund;
use App\Support\Money;
use App\Support\PaymentChannels;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** Every refund given: for cancelled paid orders and for items customers returned. Read-only. */
class RefundResource extends Resource
{
    protected static ?string $model = Refund::class;

    protected static ?string $navigationLabel = 'Refunds';

    protected static ?string $pluralModelLabel = 'Refunds';

    protected static string | UnitEnum | null $navigationGroup = 'Orders';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedReceiptRefund;

    protected static ?int $navigationSort = 6;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['order', 'user']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime('d M Y, H:i')->sortable(),
                TextColumn::make('order.order_number')->label('Order')->searchable(),
                TextColumn::make('order.customer_name')->label('Customer')->searchable(),
                TextColumn::make('note')->label('Reason')->placeholder('-')->wrap(),
                TextColumn::make('amount')->formatStateUsing(fn ($state) => Money::tzs($state))->weight('bold')->sortable(),
                TextColumn::make('method')->formatStateUsing(fn (string $state) => PaymentChannels::refundMethods()[$state] ?? $state),
                TextColumn::make('reference')->label('Transaction ID')->copyable()->placeholder('-'),
                TextColumn::make('user.name')->label('By')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('method')->options(PaymentChannels::refundMethods()),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListRefunds::route('/')];
    }
}
