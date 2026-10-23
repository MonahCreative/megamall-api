<?php

namespace App\Filament\Resources\Returns\Pages;

use App\Filament\Resources\Returns\ReturnActions;
use App\Filament\Resources\Returns\ReturnRequestResource;
use Filament\Resources\Pages\ViewRecord;

class ViewReturnRequest extends ViewRecord
{
    protected static string $resource = ReturnRequestResource::class;

    protected function getHeaderActions(): array
    {
        return ReturnActions::all();
    }

    public function getTitle(): string
    {
        return 'Return '.$this->getRecord()->return_number;
    }
}
