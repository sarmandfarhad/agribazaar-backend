<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Farmer;
use App\Models\FarmerProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FarmerProductController extends Controller
{
    /**
     * Store product quantity for a specific farmer.
     */
    public function store(Request $request)
    {
        if (!$request->user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.'
            ], 403);
        }

        $request->validate([
            'farmer_id'  => 'required|exists:farmers,id',
            'product_id' => 'required|exists:products,id',
            'quantity'   => 'required|numeric|min:0',
            'rating'     => 'nullable|integer|min:1|max:3',
        ]);

        try {
            DB::transaction(function () use ($request) {
                $farmerProduct = FarmerProduct::where('farmer_id', $request->farmer_id)
                    ->where('product_id', $request->product_id)
                    ->first();

                if ($farmerProduct) {
                    $farmerProduct->quantity += $request->quantity;
                    if ($request->has('rating')) {
                        $farmerProduct->rating = $request->rating;
                    }
                    $farmerProduct->save();
                } else {
                    FarmerProduct::create([
                        'farmer_id' => $request->farmer_id,
                        'product_id' => $request->product_id,
                        'quantity' => $request->quantity,
                        'rating' => $request->rating,
                    ]);
                }

                // Update the product's total_quantity
                $totalQuantity = FarmerProduct::where('product_id', $request->product_id)->sum('quantity');
                Product::where('id', $request->product_id)->update(['total_quantity' => $totalQuantity]);
            });

            return response()->json([
                'success' => true,
                'message' => 'Product quantity added for farmer successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add product quantity: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Rate a specific product entry of a specific farmer.
     */
    public function rate(Request $request, $id)
    {
        if (!$request->user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.'
            ], 403);
        }

        $request->validate([
            'rating' => 'required|integer|min:1|max:3',
        ]);

        $farmerProduct = FarmerProduct::findOrFail($id);
        $farmerProduct->rating = $request->rating;
        $farmerProduct->save();

        return response()->json([
            'success' => true,
            'message' => 'Farmer product rated successfully.',
            'farmer_product' => $farmerProduct
        ]);
    }

    /**
     * Update product quantity for a specific farmer.
     */
    public function update(Request $request, $id)
    {
        if (!$request->user()->isAdmin()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $farmerProduct = FarmerProduct::findOrFail($id);

        $request->validate([
            'quantity' => 'nullable|numeric|min:0',
            'rating'   => 'nullable|integer|min:1|max:3',
        ]);

        try {
            DB::transaction(function () use ($farmerProduct, $request) {
                $updateData = [];

                if ($request->filled('quantity')) {
                    $updateData['quantity'] = $request->quantity;
                }

                if ($request->has('rating')) {
                    $updateData['rating'] = $request->rating;
                }

                if (!empty($updateData)) {
                    $farmerProduct->update($updateData);
                }

                $totalQuantity = FarmerProduct::where('product_id', $farmerProduct->product_id)->sum('quantity');
                Product::where('id', $farmerProduct->product_id)->update(['total_quantity' => $totalQuantity]);
            });

            return response()->json([
                'success' => true,
                'message' => 'Farmer product updated successfully.',
                'farmer_product' => $farmerProduct
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the farmer product entry.
     */
    public function destroy(Request $request, $id)
    {
        if (!$request->user()->isAdmin()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $farmerProduct = FarmerProduct::findOrFail($id);
        $productId = $farmerProduct->product_id;

        try {
            DB::transaction(function () use ($farmerProduct, $productId) {
                $farmerProduct->delete();

                $totalQuantity = FarmerProduct::where('product_id', $productId)->sum('quantity');
                Product::where('id', $productId)->update(['total_quantity' => $totalQuantity]);
            });

            return response()->json([
                'success' => true,
                'message' => 'Farmer product removed successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * List all farmer product entries.
     */
    public function index()
    {
        $entries = FarmerProduct::with(['farmer.user', 'product'])->latest()->get();

        return response()->json([
            'success' => true,
            'farmer_products' => $entries
        ]);
    }
}
