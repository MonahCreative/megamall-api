<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\ReorderReport;
use App\Services\Reports\ReorderList;
use Filament\Widgets\Widget;

/** Dashboard: the most urgent products to re-order. */
class ReorderAlerts extends Widget
{
    protected static ?int $sort = 8;

    protected int | string | array $columnSpan = 'full';

    protected string $view = 'filament.widgets.reorder-alerts';

    protected function getViewData(): array
    {
        $rows = app(ReorderList::class)->rows();

        return [
            'rows' => array_slice($rows, 0, 8),
            'total' => count($rows),
            'url' => ReorderReport::getUrl(),
        ];
    }
}
