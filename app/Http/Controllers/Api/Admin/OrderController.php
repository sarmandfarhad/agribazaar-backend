<?php

namespace App\Http\Controllers\Api\Admin;

use App\Exceptions\ConflictException;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\OrderFeedback;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Order management (routes are limited to admins).
 */
class OrderController extends Controller
{
    public function __construct(private OrderService $orders)
    {
    }

    /**
     * Every order, newest first.
     */
    public function index()
    {
        $this->orders->confirmDueOrders();

        $orders = Order::orderBy('created_at', 'desc')->orderBy('id', 'desc')->get();

        return response()->json([
            'success' => true,
            'orders' => OrderResource::many($orders),
        ]);
    }

    /**
     * Place an order for a buyer, with prices chosen by the admin. No cancel window.
     */
    public function store(Request $request)
    {
        $request->validate([
            'buyer_id'           => 'required|exists:users,id',
            'items'              => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity'   => 'required|numeric|min:0.01',
            'items.*.quality'    => 'required|integer|min:1|max:3',
            'items.*.price'      => 'required|numeric|min:0',
            'address'            => 'nullable|string',
            'city'               => 'nullable|string',
            'phone'              => 'nullable|string',
        ]);

        $buyer = User::findOrFail($request->buyer_id);

        $order = DB::transaction(function () use ($request, $buyer) {
            $order = Order::create([
                'buyer_id'     => $buyer->id,
                'total_amount' => collect($request->items)->sum(fn ($item) => $item['price'] * $item['quantity']),
                'status'       => 'pending',
                // Fall back to the buyer's profile for missing fields
                'address'      => $request->address ?? ($buyer->buyer->address ?? ''),
                'city'         => $request->city ?? ($buyer->buyer->city ?? ''),
                'phone'        => $request->phone ?? $buyer->phone,
            ]);

            foreach ($request->items as $item) {
                $order->items()->create([
                    'product_id' => $item['product_id'],
                    'quantity'   => $item['quantity'],
                    'price'      => $item['price'],
                    'quality'    => $item['quality'],
                ]);
            }

            return $order;
        });

        return response()->json([
            'success' => true,
            'message' => 'Order placed for user successfully.',
            'order'   => OrderResource::single($order),
        ], 201);
    }

    /**
     * Assign approved farmers to a confirmed order.
     */
    public function assignFarmers(Request $request, $id)
    {
        $request->validate([
            'farmer_ids' => 'required|array|min:1',
            'farmer_ids.*' => 'required|exists:users,id',
        ]);

        $this->orders->confirmDueOrders();

        $order = Order::findOrFail($id);

        if ($order->status === Order::STATUS_AWAITING_CONFIRMATION) {
            throw new ConflictException('order_not_confirmed', 'This order is still inside its cancel window and cannot be assigned yet.');
        }

        if ($order->status === Order::STATUS_CANCELLED) {
            throw new ConflictException('order_cancelled', 'This order was cancelled and cannot be assigned.');
        }

        $approvedFarmers = User::whereIn('id', $request->farmer_ids)
            ->where('user_type', 'farmer')
            ->where('status', 'approved')
            ->count();

        if ($approvedFarmers !== count($request->farmer_ids)) {
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
            'order' => OrderResource::single($order->fresh()),
        ]);
    }

    /**
     * Move an order to "delivered to stock" or "completed".
     */
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:delivered_to_stock,completed',
        ]);

        $order = Order::findOrFail($id);
        $order->update(['status' => $request->status]);

        return response()->json([
            'success' => true,
            'message' => 'Order status updated by admin.',
            'order' => OrderResource::single($order),
        ]);
    }

    /**
     * All order feedback, newest first.
     */
    public function feedback()
    {
        return response()->json([
            'success' => true,
            'feedback' => OrderFeedback::with(['order', 'user'])->orderBy('created_at', 'desc')->get(),
        ]);
    }
}
