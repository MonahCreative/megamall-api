<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Payment */
class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'reference' => $this->reference,
            'channel' => $this->channel,
            'channel_label' => config("payments.channels.{$this->channel}", $this->channel),
            'amount' => $this->amount,
            'status' => $this->status->value,
            'failure_reason' => $this->failure_reason,
            'transaction_id' => $this->external_id,
            'paid_at' => $this->paid_at?->toIso8601String(),
        ];
    }
}
