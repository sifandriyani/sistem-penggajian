<?php

namespace Database\Seeders;

use App\Models\Jabatan;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class JabatanSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $jabatans = [
            ['kode' => 'staff', 'nama' => 'Staff', 'keterangan' => 'Jabatan administrasi dan operasional dasar', 'gaji_min' => 4_000_000, 'multiplier' => 0.10, 'transport_per_hari' => 25_000],
            ['kode' => 'supervisor', 'nama' => 'Supervisor', 'keterangan' => 'Jabatan pengawas tim dan koordinasi operasional', 'gaji_min' => 6_000_000, 'multiplier' => 0.20, 'transport_per_hari' => 37_500],
            ['kode' => 'manager', 'nama' => 'Manager', 'keterangan' => 'Jabatan pimpinan departemen dan pengambilan keputusan', 'gaji_min' => 8_000_000, 'multiplier' => 0.30, 'transport_per_hari' => 50_000],
        ];

        foreach ($jabatans as $data) {
            Jabatan::updateOrCreate(['kode' => $data['kode']], $data);
        }
    }
}
