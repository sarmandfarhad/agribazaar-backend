<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Creating (POST) and updating (PUT/PATCH/POST with a product) an admin product.
 * On update every field is optional.
 */
class ProductRequest extends FormRequest
{
    public function rules(): array
    {
        $required = $this->route('product') ? 'sometimes|required' : 'required';

        return [
            'title'          => "$required|string|max:255",
            'price_good'     => "$required|numeric|min:0",
            'price_normal'   => "$required|numeric|min:0",
            'price_bad'      => "$required|numeric|min:0",
            'image'          => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048', // primary image
            'media'          => 'nullable|array',                                 // extra images
            'media.*'        => 'image|mimes:jpeg,png,jpg,gif|max:2048',
            'remove_media'   => 'nullable|array',                                 // product_images ids to delete (update only)
            'remove_media.*' => 'integer|exists:product_images,id',
            'quantity'       => "$required|integer|min:0",
            'information'    => "$required|string",
            'total_orders'   => 'nullable|integer|min:0',
            'total_quantity' => 'nullable|integer|min:0',
            'category_id'    => 'nullable|exists:categories,id',
            'status'         => ['nullable', Rule::in(['active', 'inactive', 'out_of_stock'])],
        ];
    }
}
