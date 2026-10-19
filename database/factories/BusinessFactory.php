<?php

namespace Database\Factories;

use App\Enums\BusinessType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class BusinessFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(), 'type' => BusinessType::CarDealership,
            'name' => fake()->company(), 'slug' => (string) Str::uuid(),
            'city' => 'Buxoro', 'phone' => '+998901234567', 'status' => 'active',
        ];
    }
}
