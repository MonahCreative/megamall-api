<?php

namespace App\Filament\Pages;

use App\Services\Reports\SalesReport;
use App\Support\DateRange;
use App\Support\Spreadsheet;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Computed;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use UnitEnum;

/**
 * @property-read Schema $form
 * @property-read SalesReport $report
 */
class SalesReports extends Page
{
    protected string $view = 'filament.pages.sales-reports';

    protected static ?string $slug = 'reports';

    protected static ?string $navigationLabel = 'Sales reports';

    protected static ?string $title = 'Sales reports';

    protected static string | UnitEnum | null $navigationGroup = 'Reports';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?int $navigationSort = 1;

    public const CHANNELS = ['all' => 'Online and shop', 'online' => 'Online orders only', 'pos' => 'Shop (POS) only'];

    /** @var array<string, mixed> | null */
    public ?array $filters = [];

    public function mount(): void
    {
        $this->form->fill(['period' => 'this_month', 'channel' => 'all']);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(['md' => 4])
                    ->schema([
                        Select::make('period')
                            ->label('Period')
                            ->options(DateRange::OPTIONS)
                            ->selectablePlaceholder(false)
                            ->live(),
                        DatePicker::make('startDate')
                            ->label('From')
                            ->native(false)
                            ->displayFormat('d M Y')
                            ->maxDate(now())
                            ->live()
                            ->visible(fn (Get $get) => $get('period') === 'custom'),
                        DatePicker::make('endDate')
                            ->label('To')
                            ->native(false)
                            ->displayFormat('d M Y')
                            ->maxDate(now())
                            ->live()
                            ->visible(fn (Get $get) => $get('period') === 'custom'),
                        Select::make('channel')
                            ->label('Sales from')
                            ->options(self::CHANNELS)
                            ->selectablePlaceholder(false)
                            ->live(),
                    ]),
            ])
            ->statePath('filters');
    }

    public function range(): DateRange
    {
        return DateRange::fromFilters($this->filters);
    }

    #[Computed]
    public function report(): SalesReport
    {
        $range = $this->range();

        return new SalesReport($range->start, $range->end, $this->filters['channel'] ?? 'all');
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
        $report = $this->report;
        $summary = $report->summary();
        $range = $this->range();

        $sheets = [
            'Summary' => [['Figure', 'Value'], [
                ['Period', $range->start->format('d M Y').' - '.$range->end->format('d M Y')],
                ['Sales from', self::CHANNELS[$report->channel] ?? $report->channel],
                ['Orders', $summary['orders']],
                ['Online orders', $summary['online_orders']],
                ['Shop (POS) sales', $summary['pos_orders']],
                ['Items sold', $summary['items_sold']],
                ['Gross sales (TZS)', $summary['gross_sales']],
                ['Discounts (TZS)', $summary['discounts']],
                ['Sales (TZS)', $summary['sales']],
                ['Cost of items sold (TZS)', $summary['cost']],
                ['Gross profit (TZS)', $summary['profit']],
                ['Margin (%)', $summary['margin']],
                ['Average order (TZS)', $summary['average_order']],
                ['Refunds (TZS)', $summary['refunds']],
                ['Net sales after refunds (TZS)', $summary['net_sales']],
                ['Items sold without a cost price', $summary['items_without_cost']],
            ]],
            'By day' => [
                ['Date', 'Orders', 'Items', 'Sales (TZS)', 'Profit (TZS)'],
                array_map(fn (array $row) => [$row['date'], $row['orders'], $row['items'], $row['sales'], $row['profit']], $report->byDay()),
            ],
            'By product' => [
                ['Product', 'Category', 'Quantity', 'Sales (TZS)', 'Cost (TZS)', 'Profit (TZS)'],
                array_map(fn (array $row) => [$row['name'], $row['category'], $row['quantity'], $row['sales'], $row['cost'], $row['profit']], $report->byProduct()),
            ],
            'By category' => [
                ['Category', 'Quantity', 'Sales (TZS)', 'Profit (TZS)'],
                array_map(fn (array $row) => [$row['category'], $row['quantity'], $row['sales'], $row['profit']], $report->byCategory()),
            ],
            'Payment methods' => [
                ['Method', 'Payments', 'Received (TZS)', 'Refunded (TZS)', 'Net (TZS)'],
                array_map(fn (array $row) => [$row['label'], $row['payments'], $row['received'], $row['refunded'], $row['net']], $report->byPaymentMethod()),
            ],
            'Best sellers' => [
                ['Product', 'Category', 'Quantity', 'Sales (TZS)'],
                array_map(fn (array $row) => [$row['name'], $row['category'], $row['quantity'], $row['sales']], $report->bestSellers(50)),
            ],
            'Slow sellers' => [
                ['Product', 'Category', 'Sold in period', 'In stock', 'Stock value at cost (TZS)'],
                array_map(fn (array $row) => [$row['name'], $row['category'], $row['quantity'], $row['stock'], $row['stock_value']], $report->slowSellers(50)),
            ],
        ];

        return Spreadsheet::download('mega-mall-sales-'.$range->start->format('Y-m-d').'-to-'.$range->end->format('Y-m-d').'.xlsx', $sheets);
    }
}
