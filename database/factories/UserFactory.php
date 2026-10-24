<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'phone' => '+998'.fake()->unique()->numerify('9########'),
            'phone_verified_at' => now(),
            'seller_type' => 'individual',
            'seller_onboarded_at' => now(),
            'city' => 'Buxoro',
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function unverifiedPhone(): static
    {
        return $this->state(fn () => ['phone_verified_at' => null]);
    }

    public function sellerProfileIncomplete(): static
    {
        return $this->state(fn () => ['seller_type' => null, 'seller_onboarded_at' => null, 'city' => null]);
    }
}
