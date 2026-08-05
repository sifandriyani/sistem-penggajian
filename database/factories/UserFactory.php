<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'        => $this->faker->name(),
            'email'       => $this->faker->unique()->safeEmail(),
            'password'    => Hash::make('password'),
            'role'        => 'karyawan',
            'employee_id' => null,
        ];
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role' => 'admin', 'employee_id' => null]);
    }
}
