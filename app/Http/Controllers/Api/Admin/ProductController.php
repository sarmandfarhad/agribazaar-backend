<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    /**
     * Display a listing of products.
     */
    public function index(Request $request)
    {
        $query = Product::with(['category', 'images']);

        if ($request->has('search')) {
            $query->where('title', 'like', '%' . $request->search . '%');
        }

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $products = $query->latest()->get();

        return response()->json([
            'success' => true,
            'products' => $products
        ]);
    }

    /**
     * Store a newly created product in storage.
     */
    public function store(Request $request)
    {
        // Ensure only admin can perform this action
        if (!$request->user() || !$request->user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only admins can perform this action.'
            ], 403);
        }

        $request->validate([
            'title'          => 'required|string|max:255',
            'price_per_kilo' => 'required|numeric|min:0',
            'image'          => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048', // Primary image
            'media'          => 'nullable|array', // Sub-images
            'media.*'        => 'image|mimes:jpeg,png,jpg,gif|max:2048',
            'quantity'       => 'required|integer|min:0',
            'information'    => 'required|string',
            'total_orders'   => 'nullable|integer|min:0',
            'total_quantity' => 'nullable|integer|min:0',
            'category_id'    => 'nullable|exists:categories,id',
            'status'         => ['nullable', Rule::in(['active', 'inactive', 'out_of_stock'])],
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('products', 'public');
        }

        $product = Product::create([
            'title'          => $request->title,
            'price_per_kilo' => $request->price_per_kilo,
            'image'          => $imagePath,
            'quantity'       => $request->quantity,
            'information'    => $request->information,
            'total_orders'   => $request->total_orders ?? 0,
            'total_quantity' => $request->total_quantity ?? $request->quantity,
            'category_id'    => $request->category_id,
            'status'         => $request->status ?? 'active',
        ]);

        // Handle sub-images (media)
        if ($request->hasFile('media')) {
            foreach ($request->file('media') as $file) {
                $path = $file->store('products/media', 'public');
                ProductImage::create([
                    'product_id' => $product->id,
                    'image_path' => $path,
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Product created successfully.',
            'product' => $product->load(['images', 'category']),
        ], 201);
    }

    /**
     * Update the specified product in storage.
     */
    public function update(Request $request, Product $product)
    {
        // Ensure only admin can perform this action
        if (!$request->user() || !$request->user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only admins can perform this action.'
            ], 403);
        }

        $request->validate([
            'title'          => 'sometimes|required|string|max:255',
            'price_per_kilo' => 'sometimes|required|numeric|min:0',
            'image'          => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'media'          => 'nullable|array',
            'media.*'        => 'image|mimes:jpeg,png,jpg,gif|max:2048',
            'remove_media'   => 'nullable|array', // IDs of product_images to delete
            'remove_media.*' => 'integer|exists:product_images,id',
            'quantity'       => 'sometimes|required|integer|min:0',
            'information'    => 'sometimes|required|string',
            'total_orders'   => 'nullable|integer|min:0',
            'total_quantity' => 'nullable|integer|min:0',
            'category_id'    => 'nullable|exists:categories,id',
            'status'         => ['nullable', Rule::in(['active', 'inactive', 'out_of_stock'])],
        ]);

        $data = $request->only([
            'title', 'price_per_kilo', 'quantity', 'information', 'total_orders', 'total_quantity', 'category_id', 'status'
        ]);

        if ($request->hasFile('image')) {
            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }
            $data['image'] = $request->file('image')->store('products', 'public');
        }

        $product->update($data);

        // Handle new sub-images
        if ($request->hasFile('media')) {
            foreach ($request->file('media') as $file) {
                $path = $file->store('products/media', 'public');
                ProductImage::create([
                    'product_id' => $product->id,
                    'image_path' => $path,
                ]);
            }
        }

        // Handle media removal
        if ($request->remove_media) {
            $imagesToRemove = ProductImage::whereIn('id', $request->remove_media)
                                          ->where('product_id', $product->id)
                                          ->get();
            foreach ($imagesToRemove as $img) {
                Storage::disk('public')->delete($img->image_path);
                $img->delete();
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Product updated successfully.',
            'product' => $product->load(['images', 'category']),
        ]);
    }

    /**
     * Remove the specified product from storage.
     */
    public function destroy(Request $request, Product $product)
    {
        // Ensure only admin can perform this action
        if (!$request->user() || !$request->user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only admins can perform this action.'
            ], 403);
        }

        // Delete primary image
        if ($product->image) {
            Storage::disk('public')->delete($product->image);
        }

        // Delete sub-images
        foreach ($product->images as $img) {
            Storage::disk('public')->delete($img->image_path);
            $img->delete();
        }

        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully.'
        ]);
    }
}
