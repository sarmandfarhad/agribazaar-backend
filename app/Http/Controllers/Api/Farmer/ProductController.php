<?php

namespace App\Http\Controllers\Api\Farmer;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\FarmerProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    /**
     * Add or update product quantity for the authenticated farmer.
     */
    public function store(Request $request)
    {
        $user = $request->user();
        if (!$user->isFarmer()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only farmers can perform this action.'
            ], 403);
        }

        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity'   => 'required|numeric|min:0',
            'rating'     => 'nullable|integer|min:1|max:3',
        ]);

        $farmer = $user->farmer;

        try {
            DB::transaction(function () use ($farmer, $request) {
                $farmerProduct = FarmerProduct::where('farmer_id', $farmer->id)
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
                        'farmer_id' => $farmer->id,
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
                'message' => 'Product quantity updated successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update product quantity: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update product quantity for the authenticated farmer.
     */
    public function update(Request $request, $id)
    {
        $farmer = $request->user()->farmer;
        $farmerProduct = FarmerProduct::where('farmer_id', $farmer->id)->findOrFail($id);

        $request->validate([
            'quantity' => 'required|numeric|min:0',
            'rating'   => 'nullable|integer|min:1|max:3',
        ]);

        try {
            DB::transaction(function () use ($farmerProduct, $request) {
                $farmerProduct->update([
                    'quantity' => $request->quantity,
                    'rating'   => $request->rating ?? $farmerProduct->rating,
                ]);

                // Update the product's total_quantity
                $totalQuantity = FarmerProduct::where('product_id', $farmerProduct->product_id)->sum('quantity');
                Product::where('id', $farmerProduct->product_id)->update(['total_quantity' => $totalQuantity]);
            });

            return response()->json([
                'success' => true,
                'message' => 'Product quantity updated successfully.',
                'farmer_product' => $farmerProduct
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update product quantity: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the product contribution.
     */
    public function destroy(Request $request, $id)
    {
        $farmer = $request->user()->farmer;
        $farmerProduct = FarmerProduct::where('farmer_id', $farmer->id)->findOrFail($id);
        $productId = $farmerProduct->product_id;

        try {
            DB::transaction(function () use ($farmerProduct, $productId) {
                $farmerProduct->delete();

                // Update the product's total_quantity
                $totalQuantity = FarmerProduct::where('product_id', $productId)->sum('quantity');
                Product::where('id', $productId)->update(['total_quantity' => $totalQuantity]);
            });

            return response()->json([
                'success' => true,
                'message' => 'Product contribution removed successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove product contribution: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * List farmer's products.
     */
    public function index(Request $request)
    {
        $farmer = $request->user()->farmer;
        $query = FarmerProduct::with('product')
            ->where('farmer_id', $farmer->id);

        if ($request->has('search')) {
            $query->whereHas('product', function ($q) use ($request) {
                $q->search($request->search);
            });
        }

        $products = $query->get()->map(function ($farmerProduct) {
            $product = $farmerProduct->product;
            if ($product && $product->image) {
                $clean = ltrim($product->image, '/');

                if (str_starts_with($clean, 'storage/')) {
                    $clean = substr($clean, strlen('storage/'));
                }

                $product->image_url = route('media.show', ['path' => $clean]);
            }
            return $farmerProduct;
        });

        return response()->json([
            'success' => true,
            'farmer_products' => $products
        ]);
    }
}
