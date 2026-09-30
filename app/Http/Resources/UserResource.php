<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The compact user object returned by login, signup, /me and the admin user endpoints.
 *
 * @mixin User
 */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'        => $this->id,
            'phone'     => $this->phone,
            'email'     => $this->email,
            'user_type' => $this->user_type,
            'status'    => $this->status,
            'profile'   => $this->profile(),
        ];
    }

    /**
     * As a plain array, ready to nest in a response ({"user": ...}).
     */
    public static function plain(User $user): array
    {
        return (new self($user))->resolve();
    }
}
