<?php

namespace App\Filament\Pages;

use App\Support\DateRange;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    public function getColumns(): int | array
    {
        return ['md' => 2, 'xl' => 2];
    }

    /** The greeting widget is the heading, so hide the default "Dashboard" title on the page. */
    public function getHeading(): string
    {
        return '';
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(['md' => 3])
                    ->schema([
                        Select::make('period')
                            ->label('Show figures for')
                            ->options(DateRange::OPTIONS)
                            ->default('this_month')
                            ->selectablePlaceholder(false)
                            ->live(),
                        DatePicker::make('startDate')
                            ->label('From')
                            ->native(false)
                            ->displayFormat('d M Y')
                            ->maxDate(now())
                            ->visible(fn (Get $get) => $get('period') === 'custom'),
                        DatePicker::make('endDate')
                            ->label('To')
                            ->native(false)
                            ->displayFormat('d M Y')
                            ->maxDate(now())
                            ->visible(fn (Get $get) => $get('period') === 'custom'),
                    ]),
            ]);
    }
}
