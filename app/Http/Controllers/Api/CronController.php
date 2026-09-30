<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OrderService;
use Illuminate\Http\Request;

/**
 * Endpoints for Vercel Cron. Vercel sends "Authorization: Bearer <CRON_SECRET>";
 * without CRON_SECRET set they are disabled.
 */
class CronController extends Controller
{
    /**
     * Confirm orders whose cancel window has ended.
     */
    public function confirmOrders(Request $request, OrderService $orders)
    {
        $secret = config('orders.cron_secret');

        if (!$secret || !hash_equals('Bearer ' . $secret, (string) $request->header('Authorization'))) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        return response()->json(['success' => true, 'confirmed' => $orders->confirmDueOrders()]);
    }
}
