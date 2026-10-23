<?php

namespace App\Filament\Resources\Returns\Pages;

use App\Enums\ReturnStatus;
use App\Filament\Resources\Returns\ReturnRequestResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListReturnRequests extends ListRecords
{
    protected static string $resource = ReturnRequestResource::class;

    public function getTabs(): array
    {
        return [
            'open' => Tab::make('To do')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', [ReturnStatus::Requested, ReturnStatus::Approved, ReturnStatus::Received])),
            'refunded' => Tab::make('Refunded')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', ReturnStatus::Refunded)),
            'rejected' => Tab::make('Rejected')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', ReturnStatus::Rejected)),
            'all' => Tab::make('All'),
        ];
    }
}
