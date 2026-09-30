<?php

namespace App\Http\Controllers\Api\Farmer;

use App\Http\Controllers\Controller;
use App\Models\FarmerProduct;
use App\Services\FarmerStockService;
use Illuminate\Http\Request;

/**
 * The authenticated farmer's stock (routes are limited to farmers).
 */
class ProductController extends Controller
{
    public function __construct(private FarmerStockService $stock)
    {
    }

    /**
     * List the farmer's stock rows.
     */
    public function index(Request $request)
    {
        $query = FarmerProduct::with('product')
            ->where('farmer_id', $request->user()->farmer->id);

        if ($request->has('search')) {
            $query->whereHas('product', fn ($q) => $q->search($request->search));
        }

        return response()->json([
            'success' => true,
            'farmer_products' => $query->get(),
        ]);
    }

    /**
     * Add kg of a product at a quality. The app sends the quality as both "quality" and "rating".
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity'   => 'required|numeric|min:0',
            'rating'     => 'nullable|integer|min:1|max:3',
            'quality'    => 'nullable|integer|min:1|max:3',
        ]);

        $this->stock->add(
            $request->user()->farmer->id,
            (int) $data['product_id'],
            $this->rating($data),
            (float) $data['quantity'],
        );

        return response()->json([
            'success' => true,
            'message' => 'Product quantity updated successfully.',
        ]);
    }

    /**
     * Set the quantity (and optionally the quality) of one of the farmer's rows.
     */
    public function update(Request $request, $id)
    {
        $row = $this->ownRow($request, $id);

        $data = $request->validate([
            'quantity' => 'required|numeric|min:0',
            'rating'   => 'nullable|integer|min:1|max:3',
            'quality'  => 'nullable|integer|min:1|max:3',
        ]);

        $rating = $this->rating($data);
        $row = $this->stock->update($row, (float) $data['quantity'], $rating, changeRating: $rating !== null);

        return response()->json([
            'success' => true,
            'message' => 'Product quantity updated successfully.',
            'farmer_product' => $row,
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $this->stock->remove($this->ownRow($request, $id));

        return response()->json([
            'success' => true,
            'message' => 'Product contribution removed successfully.',
        ]);
    }

    private function ownRow(Request $request, $id): FarmerProduct
    {
        return FarmerProduct::where('farmer_id', $request->user()->farmer->id)->findOrFail($id);
    }

    private function rating(array $data): ?int
    {
        $rating = $data['rating'] ?? $data['quality'] ?? null;

        return $rating === null ? null : (int) $rating;
    }
}
