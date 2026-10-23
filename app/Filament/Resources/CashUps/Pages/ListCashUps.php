<?php

namespace App\Filament\Resources\CashUps\Pages;

use App\Filament\Resources\CashUps\CashUpResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCashUps extends ListRecords
{
    protected static string $resource = CashUpResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Cash up a day')];
    }
}
