<?php

namespace Database\Factories;

use App\Models\Jabatan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Employee>
 */
class EmployeeFactory extends Factory
{
    public function definition(): array
    {
        $kode = $this->faker->randomElement(['staff', 'supervisor', 'manager']);
        $gajiMin = 4_000_000;
        $gajiMax = 6_000_000;
        $multiplier = 0.10;

        if ($kode === 'supervisor') {
            $gajiMin = 6_000_000;
            $gajiMax = 8_000_000;
            $multiplier = 0.20;
        } elseif ($kode === 'manager') {
            $gajiMin = 8_000_000;
            $gajiMax = 10_000_000;
            $multiplier = 0.30;
        }

        $jabatan = Jabatan::firstOrCreate(
            ['kode' => $kode],
            [
                'nama' => ucfirst($kode),
                'gaji_min' => $gajiMin,
                'gaji_max' => $gajiMax,
                'multiplier' => $multiplier,
                'transport_per_hari' => 0,
            ]
        );

        return [
            'nip'                 => 'EMP' . $this->faker->unique()->numerify('#####'),
            'nama'                => $this->faker->name(),
            'jabatan_id'          => $jabatan->id,
            'gaji_pokok'          => $jabatan->gaji_min,
            'status_menikah'      => $this->faker->boolean(),
            'jumlah_anak'         => $this->faker->numberBetween(0, 4),
        ];
    }
}
