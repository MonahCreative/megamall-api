<?php

namespace App\Http\Resources;

use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Order */
class OrderResource extends JsonResource
{
    /** Set true only in the response to the buyer who just placed the order. */
    public bool $withToken = false;

    public function toArray(Request $request): array
    {
        $latestPayment = $this->payments->first();

        return [
            'order_number' => $this->order_number,
            'access_token' => $this->when($this->withToken, $this->access_token),
            'customer_name' => $this->customer_name,
            'phone' => Phone::display($this->phone),
            'region' => $this->region->name,
            'district' => $this->district?->name,
            'area' => $this->area,
            'address' => $this->address,
            'payment_mode' => $this->payment_mode->value,
            'payment_mode_label' => $this->payment_mode->label(),
            'payment_status' => $this->payment_status->value,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'needs_payment' => $this->needsPrepayment() && $this->status->value !== 'cancelled',
            'subtotal' => $this->subtotal,
            'delivery_fee' => $this->delivery_fee,
            'total' => $this->total,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'tracking_note' => $this->tracking_note,
            'created_at' => $this->created_at->toIso8601String(),
            'items' => $this->items->map(fn ($item) => [
                'product_id' => $item->product_id,
                'name' => $item->product_name,
                'variant' => $item->variant_name,
                'unit_price' => $item->unit_price,
                'quantity' => $item->quantity,
                'line_total' => $item->line_total,
            ]),
            'payment' => $latestPayment ? PaymentResource::make($latestPayment) : null,
        ];
    }
}
