<?php

namespace App\Filament\Widgets;

use App\Models\Setting;
use Filament\Widgets\Widget;

class Greeting extends Widget
{
    protected static ?int $sort = 1;

    protected int | string | array $columnSpan = 'full';

    protected string $view = 'filament.widgets.greeting';

    protected function getViewData(): array
    {
        $hour = (int) now()->format('G');

        return [
            'greeting' => match (true) {
                $hour < 12 => 'Good morning',
                $hour < 17 => 'Good afternoon',
                default => 'Good evening',
            },
            'swahili' => match (true) {
                $hour < 12 => 'Habari za asubuhi',
                $hour < 17 => 'Habari za mchana',
                default => 'Habari za jioni',
            },
            'name' => auth()->user()?->name,
            'store' => Setting::getValue('store_name', 'Mega Mall Tanzania'),
            'date' => now()->format('l, d F Y'),
        ];
    }
}
