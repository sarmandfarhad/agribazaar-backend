<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Buyer;
use App\Models\Farmer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'token'   => $this->issueToken($user, 'admin-token', ['admin']),
            'user'    => UserResource::plain($user),
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

        $user = $this->register($request, 'farmer', fn (User $user) => Farmer::create(
            $request->only(['first_name', 'second_name', 'address', 'city']) + ['user_id' => $user->id]
        ));

        return response()->json([
            'success' => true,
            'message' => 'Farmer registered successfully. Await admin approval.',
            'token'   => $user->token,
            'user'    => UserResource::plain($user),
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

        $user = $this->register($request, 'buyer', fn (User $user) => Buyer::create(
            $request->only(['first_name', 'second_name', 'address', 'city', 'business_name']) + ['user_id' => $user->id]
        ));

        return response()->json([
            'success' => true,
            'message' => 'Buyer registered successfully. Await admin approval.',
            'token'   => $user->token,
            'user'    => UserResource::plain($user),
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

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'token'   => $this->issueToken($user, $user->user_type . '-token'),
            'user'    => UserResource::plain($user),
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

    // ─── CURRENT USER ──────────────────────────────────────────────

    /**
     * GET /api/user: the full user model with its profile (the app's session check).
     */
    public function user(Request $request)
    {
        return response()->json([
            'user' => $request->user()->toArrayWithProfile(),
        ]);
    }

    /**
     * GET /api/me: the compact user object.
     */
    public function me(Request $request)
    {
        return response()->json([
            'success' => true,
            'user'    => UserResource::plain($request->user()),
        ]);
    }

    // ─── HELPERS ───────────────────────────────────────────────────

    /**
     * Creates a pending user of the given type with its profile, and a first token.
     */
    private function register(Request $request, string $type, callable $createProfile): User
    {
        return DB::transaction(function () use ($request, $type, $createProfile) {
            $user = User::create([
                'phone'     => $request->phone,
                'email'     => $request->email,
                'password'  => $request->password, // hashed by the model's cast
                'user_type' => $type,
                'status'    => 'pending',
            ]);

            $createProfile($user);

            $user->update(['token' => $user->createToken("{$type}-token")->plainTextToken]);

            return $user;
        });
    }

    /**
     * Replaces the user's tokens with a new one (one active session per user).
     */
    private function issueToken(User $user, string $name, array $abilities = ['*']): string
    {
        $user->tokens()->delete();
        $token = $user->createToken($name, $abilities)->plainTextToken;
        $user->update(['token' => $token]);

        return $token;
    }
}
