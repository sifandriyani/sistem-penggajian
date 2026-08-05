<?php

namespace Database\Factories;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Attendance>
 */
class AttendanceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'tanggal'     => $this->faker->dateTimeBetween('-1 month', 'now')->format('Y-m-d'),
            'status'      => 'hadir',
            'jam_lembur'  => 0,
        ];
    }
}
