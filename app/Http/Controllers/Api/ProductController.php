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
        $query = Product::with(['category', 'images', 'farmerProducts'])
            ->where('status', '!=', 'inactive');

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('search')) {
            $query->search($request->search);
        }

        $products = $query->latest()->get()->map(function ($product) {
            $good   = $product->farmerProducts->where('rating', 3)->sum('quantity');
            $normal = $product->farmerProducts->where('rating', 2)->sum('quantity');
            $bad    = $product->farmerProducts->where('rating', 1)->sum('quantity');

            $arr = $product->setHidden(['farmerProducts'])->toArray();
            $arr['good_quantity']   = $good;
            $arr['normal_quantity'] = $normal;
            $arr['bad_quantity']    = $bad;

            return $arr;
        });

        return response()->json(['success' => true, 'products' => $products]);
    }

    public function show(Product $product)
    {
        if ($product->status === 'inactive') {
            return response()->json(['success' => false, 'message' => 'Product is not available.'], 404);
        }

        $product->load(['category', 'images', 'farmerProducts']);

        $arr = $product->setHidden(['farmerProducts'])->toArray();
        $arr['good_quantity']   = $product->farmerProducts->where('rating', 3)->sum('quantity');
        $arr['normal_quantity'] = $product->farmerProducts->where('rating', 2)->sum('quantity');
        $arr['bad_quantity']    = $product->farmerProducts->where('rating', 1)->sum('quantity');

        return response()->json(['success' => true, 'product' => $arr]);
    }
}
