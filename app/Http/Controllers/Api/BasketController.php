<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BasketItem;
use App\Services\BasketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The buyer's server-side basket (routes are limited to buyers). Every route returns the whole basket.
 * Quantities above available stock are allowed here; checkout re-checks stock.
 */
class BasketController extends Controller
{
    public function __construct(private BasketService $basket)
    {
    }

    public function index(Request $request)
    {
        return $this->basketResponse($request);
    }

    /**
     * Add kg of a product at a quality; merges into the existing line for that product and quality.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('status', 'active')],
            'quality'    => 'required|integer|in:1,2,3',
            'quantity'   => 'required|integer|min:1',
        ]);

        $this->basket->add(
            $request->user()->id,
            (int) $data['product_id'],
            (int) $data['quality'],
            (int) $data['quantity'],
        );

        return $this->basketResponse($request);
    }

    /**
     * Set a line's quantity; 0 or less removes the line.
     */
    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'quantity' => 'required|integer',
        ]);

        $item = $this->ownItem($request, $id);

        if ((int) $data['quantity'] <= 0) {
            $item->delete();
        } else {
            $item->update(['quantity' => (int) $data['quantity']]);
        }

        return $this->basketResponse($request);
    }

    public function destroy(Request $request, $id)
    {
        $this->ownItem($request, $id)->delete();

        return $this->basketResponse($request);
    }

    /**
     * A line in another buyer's basket is reported as not found.
     */
    private function ownItem(Request $request, $id): BasketItem
    {
        $item = BasketItem::where('buyer_id', $request->user()->id)->find($id);

        abort_if(!$item, 404, 'Basket item not found.');

        return $item;
    }

    private function basketResponse(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'basket'  => $this->basket->payload($request->user()->id),
        ]);
    }
}
