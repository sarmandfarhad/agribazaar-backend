<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AuthController extends Controller
{
     public function adminLogin(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        // Find admin by email
        $user = User::where('email', $request->email)
                    ->where('user_type', 'admin')
                    ->first();

        // Check user exists and password is correct
        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid email or password.',
            ], 401);
        }

        // Check status
        if ($user->status !== 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Your account is not approved.',
            ], 403);
        }

        // Revoke old tokens
        $user->tokens()->delete();

        // Create new token
        $token = $user->createToken('admin-token', ['admin'])->plainTextToken;

        // Save token to users table
        $user->update(['token' => $token]);

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'token'   => $token,
            'user'    => [
                'id'        => $user->id,
                'email'     => $user->email,
                'user_type' => $user->user_type,
                'status'    => $user->status,
            ],
        ]);
    }
}
