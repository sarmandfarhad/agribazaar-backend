<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\StockService;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(private StockService $stock)
    {
    }

    /**
     * Display a listing of active products with optional category filtering.
     * Quantities are the available (not yet ordered) kg, see StockService.
     */
    public function index(Request $request)
    {
        $query = Product::with(['category', 'images'])
            ->where('status', '!=', 'inactive');

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('search')) {
            $query->search($request->search);
        }

        $products = $query->latest()->get();
        $this->stock->preload($products);

        return response()->json(['success' => true, 'products' => $products]);
    }

    public function show(Product $product)
    {
        if ($product->status === 'inactive') {
            return response()->json(['success' => false, 'message' => 'Product is not available.'], 404);
        }

        $product->load(['category', 'images']);

        return response()->json(['success' => true, 'product' => $product]);
    }
}
