<x-filament-widgets::widget>
    <div style="display:flex; flex-wrap:wrap; align-items:flex-end; justify-content:space-between; gap:12px;">
        <div>
            <div style="font-size:1.6rem; font-weight:700; color:var(--primary-600); line-height:1.2;">
                {{ $greeting }}!
            </div>
            <div class="fi-wi-stats-overview-stat-label" style="font-size:1.05rem; margin-top:4px;">
                {{ $name }} · <span style="opacity:.7;">{{ $swahili }}</span>
            </div>
        </div>
        <div style="text-align:right;">
            <span style="display:inline-block; padding:4px 14px; border-radius:999px; border:1px solid var(--primary-400); color:var(--primary-600); font-weight:600; font-size:.85rem;">
                {{ $store }}
            </span>
            <div class="fi-wi-stats-overview-stat-label" style="font-size:.8rem; margin-top:6px;">{{ $date }}</div>
        </div>
    </div>
</x-filament-widgets::widget>
