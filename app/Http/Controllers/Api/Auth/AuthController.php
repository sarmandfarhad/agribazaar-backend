<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Farmer;
use App\Models\Buyer;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    // ─── ADMIN LOGIN ───────────────────────────────────────────────
    public function adminLogin(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)
                    ->where('user_type', 'admin')
                    ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['success' => false, 'message' => 'Invalid email or password.'], 401);
        }

        if ($user->status !== 'approved') {
            return response()->json(['success' => false, 'message' => 'Your account is not approved.'], 403);
        }

        $user->tokens()->delete();
        $token = $user->createToken('admin-token', ['admin'])->plainTextToken;
        $user->update(['token' => $token]);

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'token'   => $token,
            'user'    => [
                'id'        => $user->id,
                'email'     => $user->email,
                'phone'     => $user->phone,
                'user_type' => $user->user_type,
                'status'    => $user->status,
            ],
        ]);
    }

    // ─── FARMER SIGNUP ─────────────────────────────────────────────
    public function farmerSignup(Request $request)
    {
        $request->validate([
            'phone'       => 'required|string|unique:users,phone',
            'email'       => 'nullable|email|unique:users,email',
            'password'    => 'required|string|min:6|confirmed',
            'first_name'  => 'required|string',
            'second_name' => 'required|string',
            'address'     => 'required|string',
            'city'        => 'required|string',
        ]);

        $user = User::create([
            'phone'     => $request->phone,
            'email'     => $request->email,
            'password'  => Hash::make($request->password),
            'user_type' => 'farmer',
            'status'    => 'pending',
        ]);

        $farmer = Farmer::create([
            'user_id'     => $user->id,
            'first_name'  => $request->first_name,
            'second_name' => $request->second_name,
            'address'     => $request->address,
            'city'        => $request->city,
        ]);

        $token = $user->createToken('farmer-token')->plainTextToken;
        $user->update(['token' => $token]);

        return response()->json([
            'success' => true,
            'message' => 'Farmer registered successfully. Await admin approval.',
            'token'   => $token,
            'user'    => [
                'id'        => $user->id,
                'phone'     => $user->phone,
                'email'     => $user->email,
                'user_type' => $user->user_type,
                'status'    => $user->status,
                'profile'   => $farmer,
            ],
        ], 201);
    }

    // ─── BUYER SIGNUP ──────────────────────────────────────────────
    public function buyerSignup(Request $request)
    {
        $request->validate([
            'phone'         => 'required|string|unique:users,phone',
            'email'         => 'nullable|email|unique:users,email',
            'password'      => 'required|string|min:6|confirmed',
            'first_name'    => 'required|string',
            'second_name'   => 'required|string',
            'address'       => 'required|string',
            'city'          => 'required|string',
            'business_name' => 'required|string',
        ]);

        $user = User::create([
            'phone'     => $request->phone,
            'email'     => $request->email,
            'password'  => Hash::make($request->password),
            'user_type' => 'buyer',
            'status'    => 'pending',
        ]);

        $buyer = Buyer::create([
            'user_id'       => $user->id,
            'first_name'    => $request->first_name,
            'second_name'   => $request->second_name,
            'address'       => $request->address,
            'city'          => $request->city,
            'business_name' => $request->business_name,
        ]);

        $token = $user->createToken('buyer-token')->plainTextToken;
        $user->update(['token' => $token]);

        return response()->json([
            'success' => true,
            'message' => 'Buyer registered successfully. Await admin approval.',
            'token'   => $token,
            'user'    => [
                'id'        => $user->id,
                'phone'     => $user->phone,
                'email'     => $user->email,
                'user_type' => $user->user_type,
                'status'    => $user->status,
                'profile'   => $buyer,
            ],
        ], 201);
    }

    // ─── FARMER & BUYER LOGIN ──────────────────────────────────────
    public function login(Request $request)
    {
        $request->validate([
            'phone'    => 'required|string',
            'password' => 'required|string',
        ]);

        $user = User::where('phone', $request->phone)
                    ->whereIn('user_type', ['farmer', 'buyer'])
                    ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['success' => false, 'message' => 'Invalid phone or password.'], 401);
        }

        if ($user->status === 'blocked') {
            return response()->json(['success' => false, 'message' => 'Your account has been blocked.'], 403);
        }

        if ($user->status === 'pending') {
            return response()->json(['success' => false, 'message' => 'Your account is not approved yet.'], 403);
        }

        $user->tokens()->delete();
        $token = $user->createToken($user->user_type . '-token')->plainTextToken;
        $user->update(['token' => $token]);

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'token'   => $token,
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

    // ─── LOGOUT ────────────────────────────────────────────────────
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        $request->user()->update(['token' => null]);

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully.',
        ]);
    }

    // ─── GET CURRENT USER ──────────────────────────────────────────
    public function me(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
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
