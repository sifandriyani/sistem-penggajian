<?php

namespace Database\Seeders;

use App\Models\DeductionRate;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DeductionRateSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $rates = [
            [
                'kode' => 'bpjs_kesehatan',
                'nama' => 'BPJS Kesehatan',
                'jenis' => 'percentage',
                'nilai' => 0.01,
                'keterangan' => 'Potongan untuk BPJS Kesehatan karyawan',
            ],
            [
                'kode' => 'bpjs_jht',
                'nama' => 'BPJS Ketenagakerjaan (JHT)',
                'jenis' => 'percentage',
                'nilai' => 0.02,
                'keterangan' => 'Potongan JHT karyawan dari gaji pokok',
            ],
            [
                'kode' => 'bpjs_jp',
                'nama' => 'BPJS Ketenagakerjaan (JP)',
                'jenis' => 'percentage',
                'nilai' => 0.01,
                'keterangan' => 'Potongan JP karyawan dari gaji pokok',
            ],
            [
                'kode' => 'pph21',
                'nama' => 'PPh21',
                'jenis' => 'fixed',
                'nilai' => 0,
                'keterangan' => 'Potongan pajak penghasilan PPh21. Besaran disesuaikan manual atau aturan fiscal.',
            ],
            [
                'kode' => 'alpha',
                'nama' => 'Potongan Alpha',
                'jenis' => 'fixed',
                'nilai' => 0,
                'keterangan' => 'Potongan untuk alpha atau denda absensi. Dihitung dari jumlah hari tidak hadir yang dilaporkan.',
            ],
        ];

        foreach ($rates as $rate) {
            DeductionRate::updateOrCreate(['kode' => $rate['kode']], $rate);
        }
    }
}
