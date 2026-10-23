<?php

namespace App\Services\Reports;

use App\Enums\OrderStatus;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\PurchaseItem;
use App\Models\Setting;
use App\Models\StockAlert;
use Illuminate\Database\Eloquent\Builder;

/**
 * What to buy next. For every product (and every size), compares stock with how fast it sells:
 *
 *   sold per day   = units ordered in the last 30 days / 30
 *   days left      = stock / sold per day
 *   target stock   = enough for the "days of stock" setting, and never below 2 x the reorder level
 *   suggested qty  = target stock - current stock
 *
 * Listed when stock is at or below its reorder level, or will run out within 7 days.
 */
class ReorderList
{
    public const SALES_WINDOW_DAYS = 30;

    public const WARNING_DAYS = 7;

    /** @return array<int, array<string, mixed>> most urgent first */
    public function rows(): array
    {
        $defaultLevel = (int) Setting::getValue('low_stock_level', 5);
        $coverDays = max(1, (int) Setting::getValue('reorder_cover_days', 30));

        $sold = $this->unitsSold();
        $waiting = StockAlert::waiting()
            ->selectRaw('product_id, product_variant_id, count(*) as total')
            ->groupBy('product_id', 'product_variant_id')
            ->get()
            ->groupBy('product_id');
        $lastPurchases = $this->lastPurchases();

        $rows = [];
        $products = Product::query()->where('is_active', true)->with(['variants' => fn ($query) => $query->where('is_active', true), 'category:id,name'])->get();

        foreach ($products as $product) {
            $level = $product->reorder_level ?? $defaultLevel;
            $holders = $product->variants->isNotEmpty()
                ? $product->variants->map(fn ($variant) => [$variant->id, $variant->name, (int) $variant->stock])
                : collect([[null, null, (int) $product->stock]]);

            foreach ($holders as [$variantId, $variantName, $stock]) {
                $key = $product->id.':'.($variantId ?? 0);
                $perDay = ($sold[$key] ?? 0) / self::SALES_WINDOW_DAYS;
                $daysLeft = $perDay > 0 ? (int) floor($stock / $perDay) : null;

                $needsAction = $stock <= $level || ($daysLeft !== null && $daysLeft <= self::WARNING_DAYS);
                if (! $needsAction) {
                    continue;
                }

                $target = max((int) ceil($perDay * $coverDays), $level * 2);
                $suggested = max($target - $stock, 1);
                $alerts = $waiting->get($product->id, collect())
                    ->filter(fn ($row) => $row->product_variant_id === null || (int) $row->product_variant_id === (int) $variantId)
                    ->sum('total');
                $purchase = $lastPurchases[$key] ?? $lastPurchases[$product->id.':0'] ?? null;

                $rows[] = [
                    'product_id' => $product->id,
                    'name' => $product->name.($variantName ? " ({$variantName})" : ''),
                    'category' => $product->category?->name,
                    'stock' => $stock,
                    'level' => $level,
                    'sold_30_days' => (int) ($sold[$key] ?? 0),
                    'days_left' => $daysLeft,
                    'suggested' => $suggested,
                    'customers_waiting' => (int) $alerts,
                    'supplier' => $purchase['supplier'] ?? null,
                    'last_cost' => $purchase['unit_cost'] ?? $product->cost_price,
                    'estimated_cost' => $suggested * (int) ($purchase['unit_cost'] ?? $product->cost_price ?? 0),
                ];
            }
        }

        // Out of stock first, then whatever runs out soonest, then the lowest stock.
        usort($rows, fn (array $a, array $b) => [$a['stock'] > 0, $a['days_left'] ?? PHP_INT_MAX, $a['stock']]
            <=> [$b['stock'] > 0, $b['days_left'] ?? PHP_INT_MAX, $b['stock']]);

        return $rows;
    }

    /** @return array<string, int> "productId:variantId" => units ordered recently (online and shop, not cancelled) */
    private function unitsSold(): array
    {
        return OrderItem::query()
            ->whereHas('order', fn (Builder $query) => $query
                ->where('status', '!=', OrderStatus::Cancelled)
                ->where('created_at', '>=', now()->subDays(self::SALES_WINDOW_DAYS)))
            ->whereNotNull('product_id')
            ->selectRaw('product_id, product_variant_id, sum(quantity) as units')
            ->groupBy('product_id', 'product_variant_id')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->product_id.':'.($row->product_variant_id ?? 0) => (int) $row->units])
            ->all();
    }

    /** @return array<string, array{supplier: ?string, unit_cost: int}> latest purchase per product/size */
    private function lastPurchases(): array
    {
        $latest = [];
        PurchaseItem::query()
            ->with('purchase.supplier:id,name')
            ->orderByDesc('id')
            ->limit(2000)
            ->get()
            ->each(function (PurchaseItem $item) use (&$latest) {
                foreach ([$item->product_id.':'.($item->product_variant_id ?? 0), $item->product_id.':0'] as $key) {
                    $latest[$key] ??= ['supplier' => $item->purchase?->supplier?->name, 'unit_cost' => (int) $item->unit_cost];
                }
            });

        return $latest;
    }
}
