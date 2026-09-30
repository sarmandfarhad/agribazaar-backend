<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Wishlist;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    /**
     * List user's wishlist products.
     */
    public function index(Request $request)
    {
        $wishlist = Wishlist::with('product.category', 'product.images')
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'wishlist' => $wishlist,
        ]);
    }

    /**
     * Add a product to the wishlist.
     */
    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
        ]);

        $userId = $request->user()->id;
        $productId = $request->product_id;

        $wishlistEntry = Wishlist::firstOrCreate([
            'user_id' => $userId,
            'product_id' => $productId,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Product added to wishlist.',
            'wishlist' => $wishlistEntry->load('product'),
        ]);
    }

    /**
     * Remove a product from the wishlist.
     */
    public function destroy(Request $request, $productId)
    {
        $deleted = Wishlist::where('user_id', $request->user()->id)
            ->where('product_id', $productId)
            ->delete();

        if ($deleted) {
            return response()->json([
                'success' => true,
                'message' => 'Product removed from wishlist.',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Product not found in your wishlist.',
        ], 404);
    }
}
