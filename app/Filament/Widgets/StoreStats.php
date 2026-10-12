<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Order;
use App\Models\Product;
use App\Support\Money;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;

/** The four coloured "Overview" cards at the top of the dashboard. All-time figures. */
class StoreStats extends Widget
{
    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 'full';

    protected string $view = 'filament.widgets.store-stats';

    protected ?string $pollingInterval = '60s';

    protected function getViewData(): array
    {
        $earnings = Order::where('payment_status', PaymentStatus::Paid)->sum('total');

        return [
            'cards' => [
                [
                    'label' => 'Total earnings',
                    'value' => Money::tzs($earnings),
                    'icon' => Heroicon::OutlinedBanknotes,
                    'background' => 'linear-gradient(135deg, #e11d74, #f43f5e)',
                    'url' => PaymentResource::getUrl('index'),
                ],
                [
                    'label' => 'Total orders',
                    'value' => number_format(Order::where('status', '!=', OrderStatus::Cancelled)->count()),
                    'icon' => Heroicon::OutlinedShoppingBag,
                    'background' => 'linear-gradient(135deg, #f97316, #fb923c)',
                    'url' => OrderResource::getUrl('index', ['tab' => 'all']),
                ],
                [
                    'label' => 'Total customers',
                    'value' => number_format(Order::distinct('phone')->count('phone')),
                    'icon' => Heroicon::OutlinedUserGroup,
                    'background' => 'linear-gradient(135deg, #6d28d9, #8b5cf6)',
                    'url' => OrderResource::getUrl('index', ['tab' => 'all']),
                ],
                [
                    'label' => 'Total products',
                    'value' => number_format(Product::count()),
                    'icon' => Heroicon::OutlinedCube,
                    'background' => 'linear-gradient(135deg, #2563eb, #3b82f6)',
                    'url' => ProductResource::getUrl('index'),
                ],
            ],
        ];
    }
}
