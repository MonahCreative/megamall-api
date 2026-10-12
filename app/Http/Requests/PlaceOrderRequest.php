<?php

namespace App\Http\Requests;

use App\Rules\TanzanianPhone;
use Illuminate\Foundation\Http\FormRequest;

class PlaceOrderRequest extends FormRequest
{
    use CartItemsRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_name' => ['required', 'string', 'min:3', 'max:100'],
            'phone' => ['required', 'string', new TanzanianPhone],
            'email' => ['nullable', 'email', 'max:150'],
            'region_id' => ['required', 'integer', 'exists:regions,id'],
            'district_id' => ['nullable', 'integer', 'exists:districts,id'],
            'area' => ['required', 'string', 'max:120'],
            'address' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:500'],
            ...$this->itemRules(),
        ];
    }

    public function messages(): array
    {
        return [
            'customer_name.required' => 'Enter your full name.',
            'region_id.required' => 'Choose your region.',
            'area.required' => 'Enter your area or town.',
            'address.required' => 'Add a street or landmark so the rider can find you.',
        ];
    }
}
