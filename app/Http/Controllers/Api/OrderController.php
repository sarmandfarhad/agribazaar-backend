<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PlaceOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\OrderFeedback;
use App\Services\OrderService;
use Illuminate\Http\Request;

/**
 * Buyer order endpoints, plus the single-order view shared by every user type.
 */
class OrderController extends Controller
{
    public function __construct(private OrderService $orders)
    {
    }

    /**
     * Place an order (buyers). Prices and the delivery fee are computed on the server.
     */
    public function store(PlaceOrderRequest $request)
    {
        $order = $this->orders->place($request->user(), $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Order placed successfully.',
            'order'   => OrderResource::single($order),
        ], 201);
    }

    /**
     * The buyer's orders, newest first.
     */
    public function index(Request $request)
    {
        $this->orders->confirmDueOrders();

        $orders = $request->user()->orders()
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        OrderResource::prepare($orders);

        // Once a farmer has accepted, the buyer only sees the farmers who accepted
        $orders->each(function (Order $order) {
            if (in_array($order->status, ['accepted', 'completed'])) {
                $order->setRelation('farmers', $order->farmers->filter(
                    fn ($farmer) => in_array($farmer->pivot->status, ['accepted', 'completed'])
                ));
            }
        });

        return response()->json([
            'success' => true,
            'orders' => OrderResource::many($orders),
        ]);
    }

    /**
     * One order, for the buyer who placed it, a farmer assigned to it once confirmed, or an admin.
     */
    public function show(Request $request, $id)
    {
        $this->orders->confirmDueOrders();

        $user = $request->user();
        $order = Order::find($id);

        $allowed = $order && (
            $user->isAdmin()
            || $order->buyer_id === $user->id
            || ($user->isFarmer() && $user->assignedOrders()->visibleToFarmers()->whereKey($order->id)->exists())
        );

        if (!$allowed) {
            return response()->json(['message' => 'Order not found.'], 404);
        }

        return response()->json([
            'success' => true,
            'order' => OrderResource::single($order),
        ]);
    }

    /**
     * Cancel an order, only inside its cancel window. The items go back into the basket.
     */
    public function cancel(Request $request, $id)
    {
        $request->validate([
            'reason' => 'nullable|string',
            'cancel_reason' => 'nullable|string',
        ]);

        $order = $request->user()->orders()->find($id);

        if (!$order) {
            return response()->json(['message' => 'Order not found.'], 404);
        }

        $order = $this->orders->cancel($order, $request->input('cancel_reason') ?? $request->input('reason'));

        return response()->json([
            'success' => true,
            'message' => 'Order cancelled successfully.',
            'order' => OrderResource::single($order),
        ]);
    }

    /**
     * Report a problem with one of the buyer's orders.
     */
    public function feedback(Request $request, $id)
    {
        $request->validate([
            'problem_type' => 'required|string',
            'priority' => 'required|in:low,medium,high',
            'description' => 'required|string',
        ]);

        $order = Order::findOrFail($id);

        if ($order->buyer_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }

        $feedback = OrderFeedback::create([
            'order_id' => $order->id,
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
