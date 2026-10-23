<?php

namespace App\Filament\Pages;

use App\Filament\Resources\CashUps\CashUpResource;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\PosService;
use App\Services\PricingService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;
use UnitEnum;

/**
 * The shop counter screen: tap products, take payment, print a receipt.
 * Every sale becomes a "POS order" and takes stock like an online order.
 */
class PointOfSale extends Page
{
    protected string $view = 'filament.pages.point-of-sale';

    protected static ?string $slug = 'pos';

    protected static ?string $navigationLabel = 'POS';

    protected static ?string $title = 'Point of sale';

    protected static string | UnitEnum | null $navigationGroup = 'Orders & POS';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static ?int $navigationSort = 1;

    public string $search = '';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('cashUp')
                ->label('Cash up a day')
                ->icon(Heroicon::OutlinedScale)
                ->color('gray')
                ->url(fn () => CashUpResource::getUrl('create')),
        ];
    }

    public ?int $categoryId = null;

    /** @var array<string, array{product_id:int, variant_id:?int, quantity:int}> */
    public array $cart = [];

    public string $paymentChannel = 'cash';

    public string $customerName = '';

    public string $customerPhone = '';

    public string $discount = '';

    public string $amountReceived = '';

    public string $reference = '';

    public ?int $lastOrderId = null;

    public function addToCart(int $productId, ?int $variantId = null): void
    {
        $key = $productId.'-'.($variantId ?? 0);
        $available = $variantId ? (int) ProductVariant::whereKey($variantId)->value('stock') : (int) Product::whereKey($productId)->value('stock');
        $inCart = $this->cart[$key]['quantity'] ?? 0;

        if ($inCart + 1 > $available) {
            Notification::make()->warning()->title($available ? "Only {$available} in stock" : 'Out of stock')->send();

            return;
        }

        $this->cart[$key] = ['product_id' => $productId, 'variant_id' => $variantId, 'quantity' => $inCart + 1];
        $this->lastOrderId = null;
    }

    public function increment(string $key): void
    {
        if (isset($this->cart[$key])) {
            $this->addToCart($this->cart[$key]['product_id'], $this->cart[$key]['variant_id']);
        }
    }

    public function decrement(string $key): void
    {
        if (! isset($this->cart[$key])) {
            return;
        }
        $this->cart[$key]['quantity']--;
        if ($this->cart[$key]['quantity'] <= 0) {
            unset($this->cart[$key]);
        }
    }

    public function remove(string $key): void
    {
        unset($this->cart[$key]);
    }

    public function clearCart(): void
    {
        $this->reset(['cart', 'customerName', 'customerPhone', 'discount', 'amountReceived', 'reference']);
        $this->paymentChannel = 'cash';
    }

    public function chooseCategory(?int $categoryId): void
    {
        $this->categoryId = $categoryId;
    }

    public function completeSale(): void
    {
        if (empty($this->cart)) {
            Notification::make()->warning()->title('Add products first')->send();

            return;
        }

        $total = $this->totals()['total'];
        if ($this->paymentChannel === 'cash' && filled($this->amountReceived) && (int) $this->amountReceived < $total) {
            Notification::make()->danger()->title('Cash received is less than the total')->send();

            return;
        }

        try {
            $order = app(PosService::class)->sell(
                array_values($this->cart),
                $this->paymentChannel,
                (int) ($this->discount ?: 0),
                $this->customerName ?: null,
                $this->customerPhone ?: null,
                $this->reference ?: null,
            );
        } catch (ValidationException $exception) {
            Notification::make()->danger()->title('Sale not saved')->body(collect($exception->errors())->flatten()->first())->send();

            return;
        }

        $change = $this->paymentChannel === 'cash' && filled($this->amountReceived) ? (int) $this->amountReceived - $order->total : null;
        $this->clearCart();
        $this->lastOrderId = $order->id;

        Notification::make()
            ->success()
            ->title("Sale {$order->order_number} saved")
            ->body($change !== null ? 'Give change: TZS '.number_format($change) : null)
            ->actions([
                Action::make('print')->label('Print receipt')->url(route('pos.receipt', $order))->openUrlInNewTab(),
            ])
            ->persistent()
            ->send();
    }

    /** Cart lines with current prices (including promotions). */
    public function lines(): array
    {
        $pricing = app(PricingService::class);
        $products = Product::with('category')->whereIn('id', collect($this->cart)->pluck('product_id'))->get()->keyBy('id');
        $variants = ProductVariant::whereIn('id', collect($this->cart)->pluck('variant_id')->filter())->get()->keyBy('id');

        $lines = [];
        foreach ($this->cart as $key => $item) {
            $product = $products[$item['product_id']] ?? null;
            if (! $product) {
                continue;
            }
            $variant = $item['variant_id'] ? ($variants[$item['variant_id']] ?? null) : null;
            $unit = $pricing->unitPrice($product, $variant);
            $lines[] = [
                'key' => $key,
                'name' => $product->name,
                'variant' => $variant?->name,
                'unit' => $unit,
                'quantity' => $item['quantity'],
                'total' => $unit * $item['quantity'],
            ];
        }

        return $lines;
    }

    /** @return array{subtotal:int, discount:int, total:int, change:?int} */
    public function totals(): array
    {
        $subtotal = array_sum(array_column($this->lines(), 'total'));
        $discount = min($subtotal, max(0, (int) ($this->discount ?: 0)));
        $total = $subtotal - $discount;
        $received = (int) ($this->amountReceived ?: 0);

        return [
            'subtotal' => $subtotal,
            'discount' => $discount,
            'total' => $total,
            'change' => $this->paymentChannel === 'cash' && $received > 0 ? $received - $total : null,
        ];
    }

    /** Product tiles: one per product, or one per size/colour when the product has them. */
    public function tiles(): array
    {
        $pricing = app(PricingService::class);
        $term = trim($this->search);

        $products = Product::active()
            ->with(['mainImage', 'category', 'variants' => fn ($query) => $query->where('is_active', true)])
            ->when($this->categoryId, fn ($query) => $query->whereIn('category_id', Category::where('parent_id', $this->categoryId)->pluck('id')->push($this->categoryId)))
            ->when($term !== '', fn ($query) => $query->where(fn ($q) => $q->where('name', 'like', "%{$term}%")->orWhere('sku', 'like', "%{$term}%")->orWhere('brand', 'like', "%{$term}%")))
            ->orderByDesc('sold_count')
            ->limit(40)
            ->get();

        $tiles = [];
        foreach ($products as $product) {
            $options = $product->variants->isNotEmpty() ? $product->variants : collect([null]);
            foreach ($options as $variant) {
                $tiles[] = [
                    'product_id' => $product->id,
                    'variant_id' => $variant?->id,
                    'name' => $product->name,
                    'variant' => $variant?->name,
                    'price' => $pricing->unitPrice($product, $variant),
                    'stock' => $variant ? $variant->stock : $product->stock,
                    'image' => $product->mainImage?->url(),
                ];
            }
        }

        return $tiles;
    }

    protected function getViewData(): array
    {
        return [
            'tiles' => $this->tiles(),
            'lines' => $this->lines(),
            'totals' => $this->totals(),
            'categories' => Category::active()->whereNull('parent_id')->orderBy('sort_order')->get(['id', 'name']),
            'channels' => ['cash' => 'Cash', ...config('payments.channels'), 'card' => 'Card', 'bank' => 'Bank transfer'],
            'lastOrder' => $this->lastOrderId ? Order::find($this->lastOrderId) : null,
        ];
    }
}
