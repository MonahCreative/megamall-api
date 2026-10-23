<?php

namespace App\Filament\Pages;

use App\Services\Reports\ReorderList;
use App\Support\Spreadsheet;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use UnitEnum;

/** Everything that needs re-ordering, with suggested quantities, to take to suppliers. */
class ReorderReport extends Page
{
    protected string $view = 'filament.pages.reorder-report';

    protected static ?string $slug = 'reorder';

    protected static ?string $navigationLabel = 'Reorder list';

    protected static ?string $title = 'Reorder list';

    protected static string | UnitEnum | null $navigationGroup = 'Reports';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?int $navigationSort = 2;

    public function getSubheading(): ?string
    {
        return 'Suggested quantity = enough stock for the next '.(int) \App\Models\Setting::getValue('reorder_cover_days', 30).' days at the current selling speed (and at least twice the reorder level). Change these in Store settings › Stock alerts, or per product.';
    }

    protected function getViewData(): array
    {
        return ['rows' => app(ReorderList::class)->rows()];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export to Excel')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->action(fn () => $this->export()),
        ];
    }

    public function export(): BinaryFileResponse
    {
        $rows = app(ReorderList::class)->rows();

        return Spreadsheet::download('mega-mall-reorder-list-'.now()->format('Y-m-d').'.xlsx', [
            'Reorder list' => [
                ['Product', 'Category', 'In stock', 'Reorder level', 'Sold in 30 days', 'Days left', 'Suggested order', 'Customers waiting', 'Last supplier', 'Last unit cost (TZS)', 'Estimated cost (TZS)'],
                array_map(fn (array $row) => [
                    $row['name'], $row['category'], $row['stock'], $row['level'], $row['sold_30_days'], $row['days_left'] ?? '',
                    $row['suggested'], $row['customers_waiting'], $row['supplier'] ?? '', $row['last_cost'] ?? '', $row['estimated_cost'],
                ], $rows),
            ],
        ]);
    }
}
