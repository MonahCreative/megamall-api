<?php

namespace App\Filament\Resources\CashUps\Pages;

use App\Filament\Resources\CashUps\CashUpResource;
use App\Services\CashUpService;
use Carbon\CarbonImmutable;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateCashUp extends CreateRecord
{
    protected static string $resource = CashUpResource::class;

    protected static ?string $title = 'Cash up a day';

    protected static bool $canCreateAnother = false;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(CashUpService::class)->close(
                (int) $data['user_id'],
                CarbonImmutable::parse($data['business_date']),
                $data['counted'] ?? [],
                $data['note'] ?? null,
            );
        } catch (ValidationException $exception) {
            Notification::make()->danger()->title('Not saved')->body(collect($exception->errors())->flatten()->first())->send();
            $this->halt();
        }
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return null; // afterCreate() shows the result instead
    }

    protected function afterCreate(): void
    {
        $difference = $this->getRecord()->difference;

        Notification::make()
            ->title('Cash-up saved: '.CashUpResource::differenceLabel($difference))
            ->{$difference < 0 ? 'danger' : ($difference > 0 ? 'warning' : 'success')}()
            ->send();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
