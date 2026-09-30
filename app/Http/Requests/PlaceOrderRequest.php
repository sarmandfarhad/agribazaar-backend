<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /api/orders. Prices, delivery fee and distance sent by the app are accepted but ignored:
 * the server recomputes them.
 */
class PlaceOrderRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'items'              => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity'   => 'required|integer|min:1',
            'items.*.quality'    => 'required|integer|in:1,2,3',
            'items.*.price'      => 'nullable|numeric',
            'address'            => 'required_if:delivery_method,delivery|nullable|string',
            'city'               => 'required_if:delivery_method,delivery|nullable|string',
            'phone'              => 'nullable|string',
            'delivery_method'    => 'required|in:delivery,pickup',
            'latitude'           => 'required_if:delivery_method,delivery|nullable|numeric|between:-90,90',
            'longitude'          => 'required_if:delivery_method,delivery|nullable|numeric|between:-180,180',
            'delivery_fee'       => 'nullable|numeric',
            'distance_km'        => 'nullable|numeric',
        ];
    }
}
