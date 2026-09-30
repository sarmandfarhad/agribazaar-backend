<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\FarmerProduct;
use App\Services\FarmerStockService;
use Illuminate\Http\Request;

/**
 * Every farmer's stock, managed by an admin (routes are limited to admins).
 */
class FarmerProductController extends Controller
{
    public function __construct(private FarmerStockService $stock)
    {
    }

    public function index()
    {
        return response()->json([
            'success' => true,
            'farmer_products' => FarmerProduct::with(['farmer.user', 'product'])->latest()->get(),
        ]);
    }

    /**
     * Add kg of a product at a quality for a farmer.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'farmer_id'  => 'required|exists:farmers,id',
            'product_id' => 'required|exists:products,id',
            'quantity'   => 'required|numeric|min:0',
            'rating'     => 'nullable|integer|min:1|max:3',
        ]);

        $this->stock->add(
            (int) $data['farmer_id'],
            (int) $data['product_id'],
            isset($data['rating']) ? (int) $data['rating'] : null,
            (float) $data['quantity'],
        );

        return response()->json([
            'success' => true,
            'message' => 'Product quantity added for farmer successfully.',
        ]);
    }

    /**
     * Set the quality of a row (merging it into the farmer's row of that quality, if there is one).
     */
    public function rate(Request $request, $id)
    {
        $data = $request->validate([
            'rating' => 'required|integer|min:1|max:3',
        ]);

        $row = $this->stock->update(FarmerProduct::findOrFail($id), null, (int) $data['rating'], changeRating: true);

        return response()->json([
            'success' => true,
            'message' => 'Farmer product rated successfully.',
            'farmer_product' => $row,
        ]);
    }

    public function update(Request $request, $id)
    {
        $row = FarmerProduct::findOrFail($id);

        $data = $request->validate([
            'quantity' => 'nullable|numeric|min:0',
            'rating'   => 'nullable|integer|min:1|max:3',
        ]);

        $row = $this->stock->update(
            $row,
            $request->filled('quantity') ? (float) $data['quantity'] : null,
            isset($data['rating']) ? (int) $data['rating'] : null,
            changeRating: $request->has('rating'),
        );

        return response()->json([
            'success' => true,
            'message' => 'Farmer product updated successfully.',
            'farmer_product' => $row,
        ]);
    }

    public function destroy($id)
    {
        $this->stock->remove(FarmerProduct::findOrFail($id));

        return response()->json([
            'success' => true,
            'message' => 'Farmer product removed successfully.',
        ]);
    }
}
