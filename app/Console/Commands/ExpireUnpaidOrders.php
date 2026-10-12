<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Console\Command;

class ExpireUnpaidOrders extends Command
{
    protected $signature = 'orders:expire-unpaid';

    protected $description = 'Cancel prepaid orders that were not paid in time and release their stock';

    public function handle(OrderService $orders): int
    {
        $expired = Order::where('status', OrderStatus::AwaitingPayment)
            ->where('payment_status', '!=', 'paid')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->get();

        foreach ($expired as $order) {
            $orders->cancel($order, 'Not paid in time');
        }

        $this->info("Cancelled {$expired->count()} unpaid order(s).");

        return self::SUCCESS;
    }
}
