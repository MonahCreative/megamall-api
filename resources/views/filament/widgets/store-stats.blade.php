<x-filament-widgets::widget>
    <div style="font-size:1.1rem; font-weight:600; margin-bottom:12px;" class="fi-wi-stats-overview-stat-value">Overview</div>
    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:16px;">
        @foreach ($cards as $card)
            <a href="{{ $card['url'] }}"
               style="display:flex; align-items:center; gap:16px; padding:22px 20px; border-radius:14px; color:#fff; text-decoration:none; background:{{ $card['background'] }}; box-shadow:0 6px 18px -8px rgba(0,0,0,.35);">
                <span style="flex:none; width:52px; height:52px; border-radius:50%; background:#fff; display:grid; place-items:center;">
                    <x-filament::icon :icon="$card['icon']" style="width:26px; height:26px; color:#1f2937;" />
                </span>
                <span style="min-width:0;">
                    <span style="display:block; font-size:.95rem; opacity:.95;">{{ $card['label'] }}</span>
                    <span style="display:block; font-size:1.5rem; font-weight:700; line-height:1.25; font-variant-numeric:tabular-nums; overflow-wrap:anywhere;">{{ $card['value'] }}</span>
                </span>
            </a>
        @endforeach
    </div>
</x-filament-widgets::widget>
