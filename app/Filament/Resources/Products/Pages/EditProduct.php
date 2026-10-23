<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Services\StockService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    /** New sizes/colours added while editing get their opening stock logged. */
    protected function afterSave(): void
    {
        app(StockService::class)->recordOpeningStock($this->getRecord());
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('viewInShop')
                ->label('View in shop')
                ->color('gray')
                ->url(fn () => rtrim(config('megamall.frontend_url'), '/').'/product/'.$this->getRecord()->slug)
                ->openUrlInNewTab(),
            DeleteAction::make(),
        ];
    }
}
