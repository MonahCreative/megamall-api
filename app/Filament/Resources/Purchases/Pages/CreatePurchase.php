<?php

namespace App\Filament\Resources\Purchases\Pages;

use App\Filament\Resources\Purchases\PurchaseResource;
use App\Services\InventoryService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreatePurchase extends CreateRecord
{
    protected static string $resource = PurchaseResource::class;

    protected static ?string $title = 'Record purchase';

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(InventoryService::class)->receivePurchase($data, array_values($data['items'] ?? []));
        } catch (ValidationException $exception) {
            Notification::make()->danger()->title('Not saved')->body(collect($exception->errors())->flatten()->first())->send();
            $this->halt();
        }
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Purchase saved and stock updated';
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
