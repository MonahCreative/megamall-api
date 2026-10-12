<x-filament-widgets::widget>
    <div style="display:flex; justify-content:space-between; align-items:baseline; gap:12px; flex-wrap:wrap; margin-bottom:12px;">
        <div style="font-size:1.1rem; font-weight:600;" class="fi-wi-stats-overview-stat-value">Order statistics</div>
        <div class="fi-wi-stats-overview-stat-label" style="font-size:.85rem;">{{ $rangeLabel }}</div>
    </div>
    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(210px, 1fr)); gap:16px;">
        @foreach ($tiles as $tile)
            <a href="{{ $tile['url'] }}" class="fi-wi-stats-overview-stat" style="display:flex; align-items:center; gap:14px; padding:18px; text-decoration:none;">
                <span style="flex:none; width:46px; height:46px; border-radius:50%; display:grid; place-items:center; background:color-mix(in srgb, {{ $tile['color'] }} 14%, transparent);">
                    <x-filament::icon :icon="$tile['icon']" style="width:22px; height:22px; color:{{ $tile['color'] }};" />
                </span>
                <span style="min-width:0;">
                    <span class="fi-wi-stats-overview-stat-label" style="display:block; font-size:.9rem;">{{ $tile['label'] }}</span>
                    <span style="display:block; font-size:1.35rem; font-weight:700; font-variant-numeric:tabular-nums;" class="fi-wi-stats-overview-stat-value">{{ $tile['value'] }}</span>
                </span>
            </a>
        @endforeach
    </div>
</x-filament-widgets::widget>
