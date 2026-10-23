<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReturnStatus;
use App\Enums\StockMovementType;
use App\Models\Order;
use App\Models\Refund;
use App\Models\ReturnRequest;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Returns and refunds.
 *   Customer asks -> staff Approve (or Reject) -> items come back: Receive (optionally back to stock) -> Refund.
 * Refunds can also be recorded for a paid order that was cancelled.
 */
class ReturnService
{
    public function __construct(private readonly StockService $stock) {}

    public function returnWindowDays(): int
    {
        return (int) Setting::getValue('return_window_days', 7);
    }

    /**
     * @param  array<int, array{order_item_id:int, quantity:int}>  $items
     */
    public function request(Order $order, array $items, string $reason, ?string $details = null, bool $byStaff = false): ReturnRequest
    {
        if ($order->status !== OrderStatus::Delivered) {
            throw ValidationException::withMessages(['order' => 'Only delivered orders can be returned.']);
        }
        if (! $byStaff && $order->delivered_at && $order->delivered_at->lt(now()->subDays($this->returnWindowDays()))) {
            throw ValidationException::withMessages(['order' => "Returns are accepted within {$this->returnWindowDays()} days of delivery."]);
        }
        $items = array_values(array_filter($items, fn ($item) => (int) ($item['quantity'] ?? 0) > 0));
        if (empty($items)) {
            throw ValidationException::withMessages(['items' => 'Choose at least one item to return.']);
        }

        return DB::transaction(function () use ($order, $items, $reason, $details) {
            $value = 0;
            $lines = [];
            foreach ($items as $i => $item) {
                $orderItem = $order->items()->whereKey($item['order_item_id'])->first();
                if (! $orderItem) {
                    throw ValidationException::withMessages(["items.$i.order_item_id" => 'That item is not part of this order.']);
                }
                $left = $orderItem->quantity - $orderItem->returnedQuantity();
                if ((int) $item['quantity'] > $left) {
                    throw ValidationException::withMessages(["items.$i.quantity" => "You can return at most {$left} of {$orderItem->product_name}."]);
                }
                $lines[] = ['order_item_id' => $orderItem->id, 'quantity' => (int) $item['quantity']];
                $value += $orderItem->unit_price * (int) $item['quantity'];
            }

            $return = ReturnRequest::create([
                'return_number' => 'RET-'.now()->format('ymd').'-'.strtoupper(Str::random(4)),
                'order_id' => $order->id,
                'reason' => $reason,
                'details' => $details,
                'status' => ReturnStatus::Requested,
                'refund_amount' => $value,
            ]);
            $return->items()->createMany($lines);

            return $return->load('items');
        });
    }

    public function approve(ReturnRequest $return, ?string $note = null): void
    {
        $this->guard($return->status === ReturnStatus::Requested, 'Only new requests can be approved.');
        $return->update(['status' => ReturnStatus::Approved, 'admin_note' => $note ?: $return->admin_note]);
    }

    public function reject(ReturnRequest $return, string $note): void
    {
        $this->guard(in_array($return->status, [ReturnStatus::Requested, ReturnStatus::Approved], true), 'This return can no longer be rejected.');
        $return->update(['status' => ReturnStatus::Rejected, 'admin_note' => $note]);
    }

    /** The items are back in the shop. Put them back on sale if they are in good condition. */
    public function receive(ReturnRequest $return, bool $restock): void
    {
        $this->guard(in_array($return->status, [ReturnStatus::Requested, ReturnStatus::Approved], true), 'Approve the return before receiving the items.');

        DB::transaction(function () use ($return, $restock) {
            if ($restock) {
                foreach ($return->items()->with('orderItem')->get() as $item) {
                    if ($item->orderItem->product_id) {
                        $this->stock->move(
                            $item->orderItem->product_id,
                            $item->orderItem->product_variant_id,
                            $item->quantity,
                            StockMovementType::Return,
                            $return,
                            "Return {$return->return_number}",
                        );
                    }
                }
            }
            $return->update(['status' => ReturnStatus::Received, 'restocked' => $restock]);
        });
    }

    /** Give money back for a return. */
    public function refundReturn(ReturnRequest $return, int $amount, string $method, ?string $reference = null, ?string $note = null): Refund
    {
        $this->guard($return->status === ReturnStatus::Received, 'Receive the returned items before refunding.');
        $refund = $this->refundOrder($return->order, $amount, $method, $reference, $note, $return);
        $return->update(['status' => ReturnStatus::Refunded]);

        return $refund;
    }

    /** Give money back for an order (a return, or a paid order that was cancelled). */
    public function refundOrder(Order $order, int $amount, string $method, ?string $reference = null, ?string $note = null, ?ReturnRequest $return = null): Refund
    {
        $this->guard($order->isPaid() || $order->payment_status === PaymentStatus::Refunded, 'This order was never paid, so there is nothing to refund.');
        $left = $order->total - $order->refundedAmount();
        $this->guard($amount > 0 && $amount <= $left, "You can refund at most TZS ".number_format($left)." on this order.");

        return DB::transaction(function () use ($order, $amount, $method, $reference, $note, $return) {
            $refund = $order->refunds()->create([
                'return_request_id' => $return?->id,
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

    private function guard(bool $allowed, string $message): void
    {
        if (! $allowed) {
            throw ValidationException::withMessages(['status' => $message]);
        }
    }
}
