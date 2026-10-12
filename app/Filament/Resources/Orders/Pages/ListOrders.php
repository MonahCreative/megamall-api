<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    public function getTabs(): array
    {
        return [
            'action' => Tab::make('To do')
                ->badge(Order::query()->needsAction()->count() ?: null)
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->needsAction()),
            'awaiting_payment' => Tab::make('Awaiting payment')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', OrderStatus::AwaitingPayment)),
            'shipped' => Tab::make('On the way')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', OrderStatus::Shipped)),
            'delivered' => Tab::make('Delivered')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', OrderStatus::Delivered)),
            'cancelled' => Tab::make('Cancelled')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', OrderStatus::Cancelled)),
            'all' => Tab::make('All'),
        ];
    }

    public function getDefaultActiveTab(): string | int | null
    {
        return 'action';
    }
}
