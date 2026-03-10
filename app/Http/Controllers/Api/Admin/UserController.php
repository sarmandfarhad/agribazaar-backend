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

        $users = User::with(['farmer', 'buyer'])
            ->whereIn('user_type', ['farmer', 'buyer'])
            ->get()
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
