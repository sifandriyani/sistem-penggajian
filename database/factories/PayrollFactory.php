<?php

namespace Database\Factories;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Payroll>
 */
class PayrollFactory extends Factory
{
    public function definition(): array
    {
        $gajiPokok = $this->faker->numberBetween(5_000_000, 12_000_000);

        $bpjsKesehatan = $gajiPokok * 0.01;
        $bpjsJht = $gajiPokok * 0.02;
        $bpjsJp = $gajiPokok * 0.01;
        $pph21 = 0;
        $alpha = 0;
        $potonganLain = 0;
        $totalPotongan = $bpjsKesehatan + $bpjsJht + $bpjsJp + $pph21 + $alpha + $potonganLain;

        return [
            'employee_id'               => Employee::factory(),
            'periode'                   => now()->format('Y-m'),
            'gaji_pokok'                => $gajiPokok,
            'total_tunjangan'           => 500_000,
            'uang_lembur'               => 0,
            'potongan_bpjs'             => round($bpjsKesehatan + $bpjsJht + $bpjsJp, 2),
            'potongan_bpjs_kesehatan'   => round($bpjsKesehatan, 2),
            'potongan_bpjs_jht'         => round($bpjsJht, 2),
            'potongan_bpjs_jp'          => round($bpjsJp, 2),
            'potongan_pph21'            => round($pph21, 2),
            'potongan_alpha'            => round($alpha, 2),
            'potongan_lain'             => round($potonganLain, 2),
            'total_potongan'            => round($totalPotongan, 2),
            'gaji_bersih'               => round($gajiPokok + 500_000 - $totalPotongan, 2),
            'status'                    => 'belum_dibayar',
        ];
    }

    public function sudahDibayar(): static
    {
        return $this->state(fn () => ['status' => 'sudah_dibayar']);
    }
}
