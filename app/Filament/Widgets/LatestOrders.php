<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Support\Money;
use App\Support\Phone;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class LatestOrders extends TableWidget
{
    protected static ?int $sort = 6;

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Latest orders')
            ->query(Order::query()->with('region')->latest()->limit(10))
            ->paginated(false)
            ->recordUrl(fn (Order $record) => OrderResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('order_number')->label('Order')->weight('bold'),
                TextColumn::make('created_at')->label('Placed')->since(),
                TextColumn::make('customer_name')->label('Customer')
                    ->description(fn (Order $record) => Phone::display($record->phone)),
                TextColumn::make('region.name')->label('Region'),
                TextColumn::make('total')->formatStateUsing(fn ($state) => Money::tzs($state)),
                TextColumn::make('payment_status')->label('Payment')->badge(),
                TextColumn::make('status')->badge(),
            ]);
    }
}
