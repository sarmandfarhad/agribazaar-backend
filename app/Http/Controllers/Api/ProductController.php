<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * Display a listing of active products with optional category filtering.
     */
    public function index(Request $request)
    {
        $query = Product::with(['category', 'images'])
            ->where('status', '!=', 'inactive'); // Show active and out_of_stock

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('search')) {
            $query->search($request->search);
        }

        $products = $query->latest()->get();

        return response()->json([
            'success' => true,
            'products' => $products
        ]);
    }

    /**
     * Display the specified product.
     */
    public function show(Product $product)
    {
        if ($product->status === 'inactive') {
            return response()->json([
                'success' => false,
                'message' => 'Product is not available.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'product' => $product->load(['category', 'images'])
        ]);
    }
}
