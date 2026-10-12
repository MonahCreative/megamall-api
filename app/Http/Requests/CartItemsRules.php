<?php

namespace App\Http\Requests;

/** Shared validation for the items[] array sent by the cart. */
trait CartItemsRules
{
    protected function itemRules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.variant_id' => ['nullable', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ];
    }
}
