<?php

namespace App\Http\Controllers\Api\Farmer;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Services\OrderService;
use Illuminate\Http\Request;

/**
 * Orders assigned to the authenticated farmer (routes are limited to farmers).
 */
class OrderController extends Controller
{
    public function __construct(private OrderService $orders)
    {
    }

    /**
     * Assigned orders, newest first. Orders still inside their cancel window, or cancelled inside it, are never shown.
     */
    public function index(Request $request)
    {
        $this->orders->confirmDueOrders();

        $orders = $request->user()->assignedOrders()
            ->visibleToFarmers()
            ->orderBy('orders.created_at', 'desc')
            ->orderBy('orders.id', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'orders' => OrderResource::many($orders),
        ]);
    }

    /**
     * Accept or reject an assigned order.
     */
    public function respond(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:accepted,rejected',
            'notes' => 'nullable|string',
        ]);

        $farmer = $request->user();
        $order = $farmer->assignedOrders()->findOrFail($id);

        $farmer->assignedOrders()->updateExistingPivot($order->id, [
            'status' => $request->status,
            'farmer_notes' => $request->notes,
        ]);

        // One farmer accepting is enough for the order to count as accepted
        if ($request->status === 'accepted') {
            $order->update(['status' => 'accepted']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Your status for this order has been updated.',
            'status' => $request->status,
        ]);
    }
}
