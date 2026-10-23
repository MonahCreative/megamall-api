<?php

namespace App\Filament\Resources\ProductSections\Pages;

use App\Filament\Resources\ProductSections\ProductSectionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageProductSections extends ManageRecords
{
    protected static string $resource = ProductSectionResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Add section')];
    }
}
