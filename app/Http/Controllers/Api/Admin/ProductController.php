<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductRequest;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Product catalog management (routes are limited to admins).
 */
class ProductController extends Controller
{
    /**
     * All products, including inactive ones.
     */
    public function index(Request $request)
    {
        $query = Product::with(['category', 'images']);

        if ($request->has('search')) {
            $query->search($request->search);
        }

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        return response()->json([
            'success' => true,
            'products' => $query->latest()->get(),
        ]);
    }

    public function show(Product $product)
    {
        return response()->json([
            'success' => true,
            'product' => $product->load(['category', 'images']),
        ]);
    }

    public function store(ProductRequest $request)
    {
        $product = Product::create([
            'title'          => $request->title,
            'price_good'     => $request->price_good,
            'price_normal'   => $request->price_normal,
            'price_bad'      => $request->price_bad,
            'image'          => $request->file('image')?->store('products', 'public'),
            'quantity'       => $request->quantity,
            'information'    => $request->information,
            'total_orders'   => $request->total_orders ?? 0,
            'total_quantity' => $request->total_quantity ?? $request->quantity,
            'category_id'    => $request->category_id,
            'status'         => $request->status ?? 'active',
        ]);

        $this->storeMedia($request, $product);

        return response()->json([
            'success' => true,
            'message' => 'Product created successfully.',
            'product' => $product->load(['images', 'category']),
        ], 201);
    }

    public function update(ProductRequest $request, Product $product)
    {
        $data = $request->only([
            'title', 'price_good', 'price_normal', 'price_bad', 'quantity', 'information',
            'total_orders', 'total_quantity', 'category_id', 'status',
        ]);

        if ($request->hasFile('image')) {
            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }
            $data['image'] = $request->file('image')->store('products', 'public');
        }

        $product->update($data);

        $this->storeMedia($request, $product);

        if ($request->remove_media) {
            $product->images()->whereIn('id', $request->remove_media)->get()
                ->each(fn (ProductImage $image) => $this->deleteImage($image));
        }

        return response()->json([
            'success' => true,
            'message' => 'Product updated successfully.',
            'product' => $product->load(['images', 'category']),
        ]);
    }

    public function destroy(Product $product)
    {
        if ($product->image) {
            Storage::disk('public')->delete($product->image);
        }

        $product->images->each(fn (ProductImage $image) => $this->deleteImage($image));
        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully.',
        ]);
    }

    /**
     * Saves uploaded extra images ("media[]").
     */
    private function storeMedia(Request $request, Product $product): void
    {
        foreach ($request->file('media', []) as $file) {
            $product->images()->create([
                'image_path' => $file->store('products/media', 'public'),
            ]);
        }
    }

    private function deleteImage(ProductImage $image): void
    {
        Storage::disk('public')->delete($image->image_path);
        $image->delete();
    }
}
