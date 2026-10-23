<x-filament-widgets::widget>
    <x-filament::section :heading="'Reorder soon' . ($total ? ' (' . $total . ')' : '')" description="Low stock, or selling fast enough to run out within a week. 'Order' is the suggested quantity to buy.">
        @if ($total === 0)
            <p style="opacity:.7">Nothing to re-order right now. Stock levels look healthy.</p>
        @else
            @include('filament.partials.reorder-table', ['rows' => $rows, 'showCost' => false])
            <div style="margin-top:12px; text-align:right">
                <x-filament::link :href="$url" icon="heroicon-o-arrow-right" icon-position="after">See the full reorder list</x-filament::link>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
