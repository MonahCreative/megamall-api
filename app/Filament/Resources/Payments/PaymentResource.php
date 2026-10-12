<?php

namespace App\Filament\Resources\Payments;

use App\Enums\PaymentStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Models\Payment;
use App\Support\Money;
use App\Support\Phone;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** Read-only log of every payment attempt: mobile money, cash and manual. */
class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static string | UnitEnum | null $navigationGroup = 'Sales';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?int $navigationSort = 2;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('order'))
            ->defaultSort('created_at', 'desc')
            ->recordUrl(fn (Payment $record) => OrderResource::getUrl('view', ['record' => $record->order_id]))
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime('d M Y, H:i')->sortable(),
                TextColumn::make('order.order_number')->label('Order')->searchable(),
                TextColumn::make('channel')
                    ->formatStateUsing(fn (?string $state) => config("payments.channels.{$state}", ucfirst((string) $state))),
                TextColumn::make('phone')
                    ->formatStateUsing(fn (?string $state) => $state ? Phone::display($state) : '-')
                    ->searchable(),
                TextColumn::make('amount')->formatStateUsing(fn ($state) => Money::tzs($state))->sortable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('external_id')->label('Transaction ID')->copyable()->searchable()->placeholder('-'),
                TextColumn::make('gateway')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('failure_reason')->label('Problem')->wrap()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options(PaymentStatus::class),
                SelectFilter::make('channel')->options(['cash' => 'Cash', 'bank' => 'Bank transfer', ...config('payments.channels')]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayments::route('/'),
        ];
    }
}
