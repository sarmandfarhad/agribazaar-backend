<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /**
     * Display a listing of active categories.
     */
    public function index(Request $request)
    {
        $query = Category::where('isActive', true);

        if ($request->has('search')) {
            $query->search($request->search);
        }

        $categories = $query->get();

        return response()->json([
            'success' => true,
            'categories' => $categories,
        ]);
    }

    /**
     * Display the specific category with its products.
     */
    public function show(Category $category)
    {
        if (!$category->isActive) {
            return response()->json([
                'success' => false,
                'message' => 'Category is not available.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'category' => $category,
            'products' => $category->products()
                ->where('status', '!=', 'inactive')
                ->with(['images'])
                ->get(),
        ]);
    }
}
