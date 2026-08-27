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
            'student_id' => strtoupper(fake()->lexify('?')) . fake()->unique()->numerify('##########'),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => 'student',
            'department' => fake()->randomElement(['BSIT', 'BSED', 'BSBA', 'BSCrim', 'BSN']),
            'program' => 'Sample Program',
            'level' => fake()->randomElement(['1st Year', '2nd Year', '3rd Year', '4th Year']),
            'is_approved' => true,
            'must_change_password' => false,
        ];
    }

    /**
     * Pending approval — is_approved false, matching the migration
     * default. Useful for testing the admin approve/reject flow.
     */
    public function unapproved(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_approved' => false,
        ]);
    }

    /**
     * Admin role. student_id still passes the ^[A-Za-z]\d{10}$ pattern —
     * doesn't need to start with "A" to be valid, but tests reading the
     * ID back are easier to eyeball this way.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'student_id' => 'A' . fake()->unique()->numerify('##########'),
            'role' => 'admin',
            'department' => 'SGO',
            'program' => 'N/A',
            'level' => 'N/A',
        ]);
    }

    /**
     * Officer role. Does NOT create an OfficerPosition row — pair with
     * OfficerPosition::factory() in tests that need tier()/can() to
     * resolve to a real tier, otherwise this account has role=officer
     * but no active position (see OfficerPermission::tier()).
     */
    public function officer(): static
    {
        return $this->state(fn (array $attributes) => [
            'student_id' => 'O' . fake()->unique()->numerify('##########'),
            'role' => 'officer',
        ]);
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
}
