<x-filament-panels::page>
    <style>
        .mm-pos { display:grid; grid-template-columns:minmax(0, 1fr) 380px; gap:20px; align-items:start; }
        @media (max-width: 1100px) { .mm-pos { grid-template-columns:1fr; } }
        .mm-pos-tiles { display:grid; grid-template-columns:repeat(auto-fill, minmax(150px, 1fr)); gap:12px; }
        .mm-pos-tile { text-align:left; padding:0; overflow:hidden; cursor:pointer; display:flex; flex-direction:column; }
        .mm-pos-tile[disabled] { opacity:.45; cursor:not-allowed; }
        .mm-pos-tile .img { aspect-ratio:1/1; width:100%; object-fit:cover; display:block; background:rgba(127,127,127,.12); }
        .mm-pos-tile .body { padding:10px 12px; display:flex; flex-direction:column; gap:2px; }
        .mm-pos-chip { border:1px solid rgba(127,127,127,.35); border-radius:999px; padding:5px 12px; font-size:.85rem; cursor:pointer; background:transparent; }
        .mm-pos-chip.on { background:var(--primary-600); border-color:var(--primary-600); color:#fff; }
        .mm-pos-line { display:grid; grid-template-columns:minmax(0,1fr) auto; gap:6px 10px; padding:10px 0; border-bottom:1px solid rgba(127,127,127,.2); }
        .mm-pos-qty { display:flex; align-items:center; gap:6px; }
        .mm-pos-qty button { width:28px; height:28px; border-radius:8px; border:1px solid rgba(127,127,127,.35); background:transparent; cursor:pointer; font-weight:700; }
        .mm-pos-row { display:flex; justify-content:space-between; gap:10px; font-variant-numeric:tabular-nums; }
        .mm-pos-total { font-size:1.6rem; font-weight:700; }
        .mm-pos-field { display:grid; gap:4px; font-size:.85rem; }
        .mm-pos-muted { opacity:.65; font-size:.85rem; }
    </style>

    <div class="mm-pos">
        {{-- Products --}}
        <div style="display:grid; gap:14px; min-width:0;">
            <x-filament::input.wrapper prefix-icon="heroicon-o-magnifying-glass">
                <x-filament::input type="search" wire:model.live.debounce.300ms="search" placeholder="Search name, SKU or brand" autofocus />
            </x-filament::input.wrapper>

            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                <button type="button" class="mm-pos-chip {{ $categoryId === null ? 'on' : '' }}" wire:click="chooseCategory(null)">All</button>
                @foreach ($categories as $category)
                    <button type="button" class="mm-pos-chip {{ $categoryId === $category->id ? 'on' : '' }}" wire:click="chooseCategory({{ $category->id }})">{{ $category->name }}</button>
                @endforeach
            </div>

            <div class="mm-pos-tiles">
                @forelse ($tiles as $tile)
                    <button type="button"
                            class="fi-wi-stats-overview-stat mm-pos-tile"
                            wire:click="addToCart({{ $tile['product_id'] }}, {{ $tile['variant_id'] ?? 'null' }})"
                            @disabled($tile['stock'] < 1)>
                        @if ($tile['image'])
                            <img class="img" src="{{ $tile['image'] }}" alt="" loading="lazy">
                        @else
                            <div class="img"></div>
                        @endif
                        <span class="body">
                            <span style="font-weight:600; font-size:.9rem; line-height:1.25;">{{ $tile['name'] }}</span>
                            @if ($tile['variant'])
                                <span class="mm-pos-muted">{{ $tile['variant'] }}</span>
                            @endif
                            <span style="font-weight:700; color:var(--primary-600);">TZS {{ number_format($tile['price']) }}</span>
                            <span class="mm-pos-muted">{{ $tile['stock'] > 0 ? $tile['stock'].' in stock' : 'Out of stock' }}</span>
                        </span>
                    </button>
                @empty
                    <p class="mm-pos-muted">No products found.</p>
                @endforelse
            </div>
        </div>

        {{-- Cart --}}
        <x-filament::section>
            <x-slot name="heading">Current sale</x-slot>
            <x-slot name="afterHeader">
                @if (count($lines))
                    <x-filament::link tag="button" color="danger" wire:click="clearCart" size="sm">Clear</x-filament::link>
                @endif
            </x-slot>

            @if (empty($lines))
                <p class="mm-pos-muted">Tap a product to add it.</p>
                @if ($lastOrder)
                    <p style="margin-top:12px;">
                        Last sale <strong>{{ $lastOrder->order_number }}</strong> ·
                        <a href="{{ route('pos.receipt', $lastOrder) }}" target="_blank" style="color:var(--primary-600); font-weight:600;">Print receipt</a>
                    </p>
                @endif
            @else
                <div>
                    @foreach ($lines as $line)
                        <div class="mm-pos-line" wire:key="line-{{ $line['key'] }}">
                            <div style="min-width:0;">
                                <div style="font-weight:600;">{{ $line['name'] }}</div>
                                <div class="mm-pos-muted">{{ $line['variant'] ? $line['variant'].' · ' : '' }}TZS {{ number_format($line['unit']) }} each</div>
                            </div>
                            <div style="font-weight:700; font-variant-numeric:tabular-nums;">TZS {{ number_format($line['total']) }}</div>
                            <div class="mm-pos-qty">
                                <button type="button" wire:click="decrement('{{ $line['key'] }}')" aria-label="One less">−</button>
                                <span style="min-width:24px; text-align:center; font-weight:600;">{{ $line['quantity'] }}</span>
                                <button type="button" wire:click="increment('{{ $line['key'] }}')" aria-label="One more">+</button>
                            </div>
                            <div style="text-align:right;">
                                <x-filament::link tag="button" color="gray" size="sm" wire:click="remove('{{ $line['key'] }}')">Remove</x-filament::link>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div style="display:grid; gap:12px; margin-top:14px;">
                    <div class="mm-pos-row"><span>Subtotal</span><span>TZS {{ number_format($totals['subtotal']) }}</span></div>
                    <label class="mm-pos-field">Discount (TZS)
                        <x-filament::input.wrapper>
                            <x-filament::input type="number" min="0" wire:model.live.debounce.400ms="discount" placeholder="0" />
                        </x-filament::input.wrapper>
                    </label>
                    <div class="mm-pos-row" style="align-items:baseline;"><span style="font-weight:600;">Total</span><span class="mm-pos-total">TZS {{ number_format($totals['total']) }}</span></div>

                    <label class="mm-pos-field">Paid with
                        <x-filament::input.wrapper>
                            <x-filament::input.select wire:model.live="paymentChannel">
                                @foreach ($channels as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                    </label>

                    @if ($paymentChannel === 'cash')
                        <label class="mm-pos-field">Cash received (TZS)
                            <x-filament::input.wrapper>
                                <x-filament::input type="number" min="0" wire:model.live.debounce.400ms="amountReceived" placeholder="{{ $totals['total'] }}" />
                            </x-filament::input.wrapper>
                        </label>
                        @if ($totals['change'] !== null)
                            <div class="mm-pos-row" style="font-weight:700; {{ $totals['change'] < 0 ? 'color:#e11d48;' : '' }}">
                                <span>{{ $totals['change'] < 0 ? 'Still to pay' : 'Change' }}</span>
                                <span>TZS {{ number_format(abs($totals['change'])) }}</span>
                            </div>
                        @endif
                    @else
                        <label class="mm-pos-field">Transaction ID
                            <x-filament::input.wrapper>
                                <x-filament::input type="text" wire:model="reference" placeholder="e.g. QJK7H2L9" />
                            </x-filament::input.wrapper>
                        </label>
                    @endif

                    <details>
                        <summary class="mm-pos-muted" style="cursor:pointer;">Customer details (optional)</summary>
                        <div style="display:grid; gap:8px; margin-top:8px;">
                            <x-filament::input.wrapper>
                                <x-filament::input type="text" wire:model="customerName" placeholder="Name" />
                            </x-filament::input.wrapper>
                            <x-filament::input.wrapper>
                                <x-filament::input type="tel" wire:model="customerPhone" placeholder="Phone, e.g. 0712 345 678" />
                            </x-filament::input.wrapper>
                        </div>
                    </details>

                    <x-filament::button size="xl" wire:click="completeSale" icon="heroicon-o-check-circle">
                        Complete sale · TZS {{ number_format($totals['total']) }}
                    </x-filament::button>
                </div>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
