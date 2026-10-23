<x-filament-panels::page>
    <x-filament::section>
        @if (count($rows) === 0)
            <p style="opacity:.7">Nothing to re-order right now. Stock levels look healthy.</p>
        @else
            @include('filament.partials.reorder-table', ['rows' => $rows, 'showCost' => true])
            <p style="margin-top:12px; opacity:.7; font-size:.85rem">
                Total estimated cost: <strong>{{ \App\Support\Money::tzs(array_sum(array_column($rows, 'estimated_cost'))) }}</strong>
                (suggested quantities × last purchase cost).
            </p>
        @endif
    </x-filament::section>
</x-filament-panels::page>
