<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    /**
     * Store a new order (Buyer side)
     */
    public function store(Request $request)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'address' => 'required|string',
            'city' => 'required|string',
            'phone' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            $totalAmount = 0;
            $orderItems = [];

            foreach ($request->items as $item) {
                $product = Product::findOrFail($item['product_id']);
                $price = $product->price_per_kilo;
                $lineTotal = $price * $item['quantity'];
                $totalAmount += $lineTotal;

                $orderItems[] = [
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'price' => $price,
                ];
            }

            $order = Order::create([
                'buyer_id' => $request->user()->id,
                'total_amount' => $totalAmount,
                'status' => 'pending',
                'address' => $request->address,
                'city' => $request->city,
                'phone' => $request->phone ?? $request->user()->phone,
            ]);

            foreach ($orderItems as $item) {
                $item['order_id'] = $order->id;
                OrderItem::create($item);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Order placed successfully.',
                'order' => $order->load('items.product'),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Order placement failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to place order. ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List all orders (Admin side)
     */
    public function index(Request $request)
    {
        $orders = Order::with(['buyer', 'items.product', 'farmers'])
                    ->orderBy('created_at', 'desc')
                    ->get();

        return response()->json([
            'success' => true,
            'orders' => $orders,
        ]);
    }

    /**
     * Assign farmers to an order (Admin side)
     */
    public function assignFarmers(Request $request, $id)
    {
        $request->validate([
            'farmer_ids' => 'required|array|min:1',
            'farmer_ids.*' => 'required|exists:users,id',
        ]);

        $order = Order::findOrFail($id);
        
        // Verify all IDs are farmers
        $farmers = User::whereIn('id', $request->farmer_ids)
                       ->where('user_type', 'farmer')
                       ->where('status', 'approved')
                       ->get();

        if ($farmers->count() !== count($request->farmer_ids)) {
             return response()->json([
                'success' => false,
                'message' => 'Some of the provided IDs are not valid or not approved farmers.',
            ], 422);
        }

        $order->farmers()->sync($request->farmer_ids);
        $order->update(['status' => 'assigned']);

        return response()->json([
            'success' => true,
            'message' => 'Farmers assigned successfully.',
            'order' => $order->load('farmers'),
        ]);
    }

    /**
     * Get buyer's orders
     */
    public function buyerOrders(Request $request)
    {
        $orders = $request->user()->orders()->with(['items.product', 'farmers.farmer'])->get();

        $orders->each(function($order) {
            // If the order has been accepted/completed, narrow the view to only show those farmers
            if (in_array($order->status, ['accepted', 'completed'])) {
                $filteredFarmers = $order->farmers->filter(function($farmer) {
                    return in_array($farmer->pivot->status, ['accepted', 'completed']);
                });
                $order->setRelation('farmers', $filteredFarmers);
            }
        });

        return response()->json([
            'success' => true,
            'orders' => $orders,
        ]);
    }

    /**
     * Get farmer's assigned orders
     */
    public function farmerOrders(Request $request)
    {
        $orders = $request->user()->assignedOrders()->with(['buyer', 'items.product'])->get();
        return response()->json([
            'success' => true,
            'orders' => $orders,
        ]);
    }

    /**
     * Cancel order (Buyer side)
     */
    public function cancelOrder(Request $request, $id)
    {
        $request->validate([
            'reason' => 'nullable|string',
        ]);

        $order = $request->user()->orders()->findOrFail($id);

        if ($order->status === 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot cancel a completed order.',
            ], 422);
        }

        $order->update([
            'status' => 'cancelled',
            'cancel_reason' => $request->reason,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Order cancelled successfully.',
            'order' => $order,
        ]);
    }

    /**
     * Accept/Reject/Complete order (Farmer side)
     */
    public function updateFarmerStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:accepted,rejected,completed',
            'notes' => 'nullable|string',
        ]);

        $farmer = $request->user();
        $order = $farmer->assignedOrders()->findOrFail($id);

        $farmer->assignedOrders()->updateExistingPivot($order->id, [
            'status' => $request->status,
            'farmer_notes' => $request->notes,
        ]);

        // If a farmer accepts, update the main order status to 'accepted'
        if ($request->status === 'accepted') {
            $order->update(['status' => 'accepted']);
        }

        // If a farmer completes, and maybe we want to check if all are done?
        // Let's keep it simple: if any farmer completes, the order could be marked as completed
        if ($request->status === 'completed') {
            $order->update(['status' => 'completed']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Order status updated successfully.',
            'status' => $request->status,
        ]);
    }

    /**
     * Submit feedback for an order (Buyer side)
     */
    public function submitOrderFeedback(Request $request, $id)
    {
        $request->validate([
            'problem_type' => 'required|string',
            'priority' => 'required|in:low,medium,high',
            'description' => 'required|string',
        ]);

        $order = Order::findOrFail($id);

        // Optional: Ensure the order is completed (or at least exists for this buyer)
        if ($order->buyer_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }

        $feedback = \App\Models\OrderFeedback::create([
            'order_id' => $id,
            'user_id' => $request->user()->id,
            'problem_type' => $request->problem_type,
            'priority' => $request->priority,
            'description' => $request->description,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Feedback submitted successfully.',
            'feedback' => $feedback,
        ]);
    }
}
