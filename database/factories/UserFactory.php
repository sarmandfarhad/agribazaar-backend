<?php

namespace Database\Factories;

use App\Models\Buyer;
use App\Models\Farmer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state: an approved buyer with a profile.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'phone' => '0750' . fake()->unique()->numerify('#######'),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'user_type' => 'buyer',
            'status' => 'approved',
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (User $user) {
            $profile = [
                'user_id' => $user->id,
                'first_name' => fake()->firstName(),
                'second_name' => fake()->lastName(),
                'address' => fake()->streetAddress(),
                'city' => 'Erbil',
            ];

            match ($user->user_type) {
                'buyer' => Buyer::create($profile + ['business_name' => fake()->company()]),
                'farmer' => Farmer::create($profile),
                default => null,
            };
        });
    }

    public function buyer(): static
    {
        return $this->state(fn () => ['user_type' => 'buyer']);
    }

    public function farmer(): static
    {
        return $this->state(fn () => ['user_type' => 'farmer']);
    }

    public function admin(): static
    {
        return $this->state(fn () => ['user_type' => 'admin']);
    }
}
