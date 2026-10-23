<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Services\Products\ProductImporter;
use App\Services\Products\ProductSpreadsheet;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                $this->importAction(),
                Action::make('export')
                    ->label('Export all products')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->action(fn () => app(ProductSpreadsheet::class)->export()),
                Action::make('template')
                    ->label('Download empty template')
                    ->icon(Heroicon::OutlinedDocumentArrowDown)
                    ->action(fn () => app(ProductSpreadsheet::class)->template()),
            ])
                ->label('Excel')
                ->icon(Heroicon::OutlinedTableCells)
                ->color('gray')
                ->button(),
            CreateAction::make()->label('Add product'),
        ];
    }

    private function importAction(): Action
    {
        return Action::make('import')
            ->label('Import from Excel')
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->modalHeading('Import products from Excel')
            ->modalDescription('Add new products, or update prices and stock of existing ones (matched by SKU). The whole file is checked first: if a row has a problem, nothing is saved and you get the list of rows to fix.')
            ->modalSubmitActionLabel('Import')
            ->schema([
                FileUpload::make('file')
                    ->label('Excel (.xlsx) or CSV file')
                    ->disk('local')
                    ->directory('imports')
                    ->maxSize(10240)
                    ->required(),
            ])
            ->action(function (array $data, Action $action) {
                $path = Storage::disk('local')->path($data['file']);

                try {
                    $result = app(ProductImporter::class)->import($path);
                } finally {
                    Storage::disk('local')->delete($data['file']);
                }

                if ($result->failed()) {
                    $shown = array_slice($result->errors, 0, 12);
                    $more = count($result->errors) - count($shown);

                    Notification::make()
                        ->danger()
                        ->title('Nothing was imported')
                        ->body(implode('<br>', array_map('e', $shown)).($more > 0 ? "<br>…and {$more} more." : ''))
                        ->persistent()
                        ->send();
                    $action->halt(); // keep the window open to upload the corrected file

                    return;
                }

                Notification::make()
                    ->success()
                    ->title('Products imported')
                    ->body(e($result->summary()))
                    ->send();

                if ($result->warnings) {
                    Notification::make()
                        ->warning()
                        ->title('Please check')
                        ->body(implode('<br>', array_map('e', array_slice($result->warnings, 0, 8))))
                        ->persistent()
                        ->send();
                }
            });
    }
}
