<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Resources\UserResource;
use App\Models\Buyer;
use App\Models\Farmer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * User management (routes are limited to admins).
 */
class UserController extends Controller
{
    /**
     * Farmers and buyers, newest first.
     */
    public function index(Request $request)
    {
        $query = User::with(['farmer', 'buyer'])
            ->whereIn('user_type', ['farmer', 'buyer']);

        if ($request->has('search')) {
            $query->search($request->search);
        }

        if ($request->has('user_type')) {
            $query->where('user_type', $request->user_type);
        }

        return response()->json([
            'success' => true,
            'users'   => $query->latest()->get()->map(fn (User $user) => UserResource::plain($user)),
        ]);
    }

    /**
     * Create an admin, farmer or buyer (with the profile for farmers and buyers).
     */
    public function store(StoreUserRequest $request)
    {
        $user = DB::transaction(function () use ($request) {
            $user = User::create([
                'phone'     => $request->phone,
                'email'     => $request->email,
                'password'  => $request->password, // hashed by the model's cast
                'user_type' => $request->user_type,
                'status'    => $request->status ?? 'approved',
            ]);

            $profile = $request->only(['first_name', 'second_name', 'address', 'city']) + ['user_id' => $user->id];

            match ($user->user_type) {
                'farmer' => Farmer::create($profile),
                'buyer'  => Buyer::create($profile + ['business_name' => $request->business_name]),
                default  => null,
            };

            return $user;
        });

        return response()->json([
            'success' => true,
            'message' => "User of type {$user->user_type} created successfully.",
            'user'    => UserResource::plain($user),
        ], 201);
    }

    /**
     * Approve, reject or block a user.
     */
    public function updateStatus(Request $request, User $user)
    {
        $request->validate([
            'status' => ['required', Rule::in(['pending', 'approved', 'rejected', 'blocked'])],
        ]);

        $user->update(['status' => $request->status]);

        return response()->json([
            'success' => true,
            'message' => "User status updated to {$request->status} successfully.",
            'user'    => UserResource::plain($user),
        ]);
    }
}
