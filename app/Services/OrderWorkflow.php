<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentMode;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Refund;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Every status change staff make in the admin panel goes through here,
 * so the rules are the same whether a button is clicked or a test runs.
 *
 * Normal flow:
 *   Dar (COD):   New order -> Confirmed -> Being packed -> On the way -> Delivered (+ cash recorded)
 *   Other regions: Awaiting payment -> (paid) Confirmed -> Being packed -> On the way -> Delivered
 */
class OrderWorkflow
{
    public function __construct(private readonly OrderService $orders) {}

    public function canConfirm(Order $order): bool
    {
        return $order->status === OrderStatus::Pending;
    }

    public function canPack(Order $order): bool
    {
        return $order->status === OrderStatus::Confirmed;
    }

    public function canShip(Order $order): bool
    {
        return in_array($order->status, [OrderStatus::Confirmed, OrderStatus::Processing], true);
    }

    public function canDeliver(Order $order): bool
    {
        return $order->status === OrderStatus::Shipped;
    }

    public function canCancel(Order $order): bool
    {
        return ! in_array($order->status, [OrderStatus::Delivered, OrderStatus::Cancelled], true);
    }

    public function canRecordPayment(Order $order): bool
    {
        return ! $order->isPaid() && $order->status !== OrderStatus::Cancelled;
    }

    /** Staff phoned the customer and the COD order is real. */
    public function confirm(Order $order): void
    {
        $this->guard($this->canConfirm($order), 'Only new orders can be confirmed.');
        $order->update(['status' => OrderStatus::Confirmed]);
    }

    public function pack(Order $order): void
    {
        $this->guard($this->canPack($order), 'Only confirmed orders can be packed.');
        $order->update(['status' => OrderStatus::Processing]);
    }

    /** @param string|null $note e.g. "Rider Juma 0712..." or "Shabiby bus, parcel no. 4471" */
    public function ship(Order $order, ?string $note = null): void
    {
        $this->guard($this->canShip($order), 'This order is not ready to ship.');
        $this->guard(
            $order->payment_mode === PaymentMode::CashOnDelivery || $order->isPaid(),
            'This order must be paid before it is sent.',
        );
        $order->update(['status' => OrderStatus::Shipped, 'tracking_note' => $note ?: $order->tracking_note]);
    }

    /** Mark delivered. For pay-on-delivery orders, pass $cashCollected = true to record the payment too. */
    public function deliver(Order $order, bool $cashCollected = false, string $channel = 'cash', ?string $reference = null): void
    {
        $this->guard($this->canDeliver($order), 'Only orders on the way can be marked delivered.');

        DB::transaction(function () use ($order, $cashCollected, $channel, $reference) {
            if ($cashCollected && ! $order->isPaid()) {
                $this->recordPayment($order, $channel, $reference);
            }
            $order->update(['status' => OrderStatus::Delivered, 'delivered_at' => now()]);
        });
    }

    /**
     * Record money received by hand: cash to the rider, or a customer who paid to the Lipa Namba
     * without sending the transaction ID through the shop.
     */
    public function recordPayment(Order $order, string $channel, ?string $reference = null): void
    {
        $this->guard($this->canRecordPayment($order), 'This order is already paid or cancelled.');

        // One mobile money payment can only pay for one order.
        $reference = filled($reference) ? strtoupper(trim($reference)) : null;
        if ($reference && ($used = Payment::query()->where('external_id', $reference)->where('status', '!=', PaymentStatus::Failed)->with('order:id,order_number')->first())) {
            throw ValidationException::withMessages(['reference' => "Transaction ID {$reference} was already used for order {$used->order?->order_number}."]);
        }

        DB::transaction(function () use ($order, $channel, $reference) {
            $order->payments()->create([
                'gateway' => 'manual',
                'channel' => $channel,
                'phone' => $order->phone,
                'amount' => $order->total,
                'status' => PaymentStatus::Paid,
                'reference' => $order->order_number.'-M'.strtoupper(Str::random(4)),
                'external_id' => $reference,
                'paid_at' => now(),
            ]);

            $order->update([
                'payment_status' => PaymentStatus::Paid,
                'paid_at' => now(),
                'expires_at' => null,
                'status' => $order->status === OrderStatus::AwaitingPayment ? OrderStatus::Confirmed : $order->status,
            ]);
        });
    }

    /** Paid orders that can still be refunded: cancelled after payment, or delivered and (partly) returned. */
    public function canRefund(Order $order): bool
    {
        return in_array($order->status, [OrderStatus::Cancelled, OrderStatus::Delivered], true)
            && ($order->isPaid() || $order->payment_status === PaymentStatus::Refunded)
            && $order->refundedAmount() < $order->total;
    }

    /** Record money given back to the customer. Fully refunded orders are marked "Refunded". */
    public function refund(Order $order, int $amount, string $method, ?string $reference = null, ?string $note = null): Refund
    {
        $this->guard($this->canRefund($order), 'Only paid orders that were cancelled or delivered can be refunded.');
        $left = $order->total - $order->refundedAmount();
        $this->guard($amount > 0 && $amount <= $left, 'You can refund at most TZS '.number_format($left).' on this order.');

        return DB::transaction(function () use ($order, $amount, $method, $reference, $note) {
            $refund = $order->refunds()->create([
                'amount' => $amount,
                'method' => $method,
                'reference' => $reference,
                'note' => $note,
                'user_id' => auth()->id(),
            ]);

            if ($order->refundedAmount() >= $order->total) {
                $order->update(['payment_status' => PaymentStatus::Refunded]);
            }

            return $refund;
        });
    }

    public function cancel(Order $order, string $reason): void
    {
        $this->guard($this->canCancel($order), 'Delivered or cancelled orders cannot be cancelled.');
        $this->orders->cancel($order, $reason);
    }

    private function guard(bool $allowed, string $message): void
    {
        if (! $allowed) {
            throw ValidationException::withMessages(['status' => $message]);
        }
    }
}
