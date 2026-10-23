<?php

namespace App\Filament\Resources\Damages\Pages;

use App\Filament\Resources\Damages\DamageResource;
use App\Filament\Support\Attempt;
use App\Services\InventoryService;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageDamages extends ManageRecords
{
    protected static string $resource = DamageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Record damage or loss')
                ->modalDescription('The quantity is taken out of stock.')
                ->using(fn (array $data, CreateAction $action) => Attempt::run($action, fn () => app(InventoryService::class)->recordDamage(
                    (int) $data['product_id'],
                    filled($data['product_variant_id'] ?? null) ? (int) $data['product_variant_id'] : null,
                    (int) $data['quantity'],
                    $data['reason'],
                    $data['notes'] ?? null,
                    $data['damage_date'] ?? null,
                )))
                ->successNotificationTitle('Recorded and removed from stock'),
        ];
    }
}
