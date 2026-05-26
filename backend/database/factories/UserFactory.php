<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
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
            'display_name' => fake()->userName(),
            'handle' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'city' => fake()->city(),
            'profile_visibility' => 'public',
            'email_verified_at' => now(),
            'locale' => 'el',
            'favorite_categories' => ['cards', 'figures'],
            'trust_status' => 'new',
            'is_verified_seller' => false,
            'is_admin' => false,
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
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

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'display_name' => 'CardoraAdmin',
            'handle' => fake()->unique()->lexify('admin????'),
            'trust_status' => 'trusted',
            'is_verified_seller' => true,
            'is_admin' => true,
            'admin_role' => 'super_admin',
            'email_verified_at' => now(),
        ]);
    }
}
