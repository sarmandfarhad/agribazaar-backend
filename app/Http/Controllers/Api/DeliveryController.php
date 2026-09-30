<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateDeliverySettingsRequest;
use App\Services\DeliveryService;

class DeliveryController extends Controller
{
    public function __construct(private DeliveryService $delivery)
    {
    }

    /**
     * The warehouse and distance tiers, loaded by the app at checkout (and by the admin panel).
     */
    public function show()
    {
        return response()->json($this->delivery->settingsPayload());
    }

    /**
     * Replace the warehouse and the whole tier list in one request (admins only).
     */
    public function update(UpdateDeliverySettingsRequest $request)
    {
        $this->delivery->replaceSettings($request->validated('warehouse'), $request->tiers());

        return response()->json($this->delivery->settingsPayload());
    }
}
