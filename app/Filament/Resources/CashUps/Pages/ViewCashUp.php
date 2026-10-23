<?php

namespace App\Filament\Resources\CashUps\Pages;

use App\Filament\Resources\CashUps\CashUpResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCashUp extends ViewRecord
{
    protected static string $resource = CashUpResource::class;

    public function getTitle(): string
    {
        $record = $this->getRecord();

        return 'Cash-up: '.$record->user->name.', '.$record->business_date->format('D d M Y');
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->label('Delete to redo')
                ->modalDescription('Delete this cash-up so the day can be counted again. Sales are not affected.'),
        ];
    }
}
