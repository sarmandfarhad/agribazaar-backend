<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * List all users (farmer or buyer).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        // Ensure only admin can perform this action
        if (!$request->user() || !$request->user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only admins can perform this action.'
            ], 403);
        }

        $query = User::with(['farmer', 'buyer'])
            ->whereIn('user_type', ['farmer', 'buyer']);

        if ($request->has('search')) {
            $query->search($request->search);
        }

        if ($request->has('user_type')) {
            $query->where('user_type', $request->user_type);
        }

        $users = $query->latest()->get()
            ->map(function ($user) {
                return [
                    'id'        => $user->id,
                    'phone'     => $user->phone,
                    'email'     => $user->email,
                    'user_type' => $user->user_type,
                    'status'    => $user->status,
                    'profile'   => $user->profile(),
                ];
            });

        return response()->json([
            'success' => true,
            'users'   => $users,
        ]);
    }

    /**
     * Store a new user (admin, farmer, or buyer).
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        // Ensure only admin can perform this action
        if (!$request->user() || !$request->user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only admins can perform this action.'
            ], 403);
        }

        $request->validate([
            'phone'         => 'required|string|unique:users,phone',
            'email'         => 'nullable|email|unique:users,email',
            'password'      => 'required|string|min:6',
            'user_type'     => ['required', Rule::in(['admin', 'farmer', 'buyer'])],
            'status'        => ['nullable', Rule::in(['pending', 'approved', 'rejected', 'blocked'])],
            // Profile fields for farmers and buyers
            'first_name'    => 'required_if:user_type,farmer,buyer|string',
            'second_name'   => 'required_if:user_type,farmer,buyer|string',
            'address'       => 'required_if:user_type,farmer,buyer|string',
            'city'          => 'required_if:user_type,farmer,buyer|string',
            'business_name' => 'required_if:user_type,buyer|string',
        ]);

        try {
            return \Illuminate\Support\Facades\DB::transaction(function () use ($request) {
                $user = User::create([
                    'phone'     => $request->phone,
                    'email'     => $request->email,
                    'password'  => \Illuminate\Support\Facades\Hash::make($request->password),
                    'user_type' => $request->user_type,
                    'status'    => $request->status ?? 'approved',
                ]);

                if ($request->user_type === 'farmer') {
                    \App\Models\Farmer::create([
                        'user_id'     => $user->id,
                        'first_name'  => $request->first_name,
                        'second_name' => $request->second_name,
                        'address'     => $request->address,
                        'city'        => $request->city,
                    ]);
                } elseif ($request->user_type === 'buyer') {
                    \App\Models\Buyer::create([
                        'user_id'       => $user->id,
                        'first_name'    => $request->first_name,
                        'second_name'   => $request->second_name,
                        'address'       => $request->address,
                        'city'          => $request->city,
                        'business_name' => $request->business_name,
                    ]);
                }

                return response()->json([
                    'success' => true,
                    'message' => "User of type {$request->user_type} created successfully.",
                    'user'    => [
                        'id'        => $user->id,
                        'phone'     => $user->phone,
                        'email'     => $user->email,
                        'user_type' => $user->user_type,
                        'status'    => $user->status,
                        'profile'   => $user->profile(),
                    ],
                ], 201);
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create user: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Change the status of a user (approve, reject, block).
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\User  $user
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateStatus(Request $request, User $user)
    {
        // Ensure only admin can perform this action
        if (!$request->user() || !$request->user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only admins can perform this action.'
            ], 403);
        }

        $request->validate([
            'status' => ['required', Rule::in(['pending', 'approved', 'rejected', 'blocked'])],
        ]);

        $user->status = $request->status;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => "User status updated to {$request->status} successfully.",
            'user'    => [
                'id'        => $user->id,
                'phone'     => $user->phone,
                'email'     => $user->email,
                'user_type' => $user->user_type,
                'status'    => $user->status,
                'profile'   => $user->profile(),
            ],
        ]);
    }
}
