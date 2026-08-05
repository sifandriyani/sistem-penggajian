<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\Jabatan;
use Illuminate\Database\Seeder;

class EmployeeSeeder extends Seeder
{
    /**
     * Data contoh dipakai untuk demo saat presentasi kepada asesor.
     * Ada 3 jenjang jabatan (Staff, Supervisor, Manager) — lihat
     * Employee::rentangGajiPokok() untuk rentang gaji tiap jenjang.
     * 
     * Tunjangan Transport sekarang master data di Jabatan, bukan per Employee.
     */
    public function run(): void
    {
        $data = [
            ['nip' => 'EMP00001', 'nama' => 'Andi Saputra', 'jabatan' => 'manager',    'gaji_pokok' => 9_000_000, 'status_menikah' => true,  'jumlah_anak' => 2],
            ['nip' => 'EMP00002', 'nama' => 'Budi Santoso', 'jabatan' => 'supervisor', 'gaji_pokok' => 7_000_000, 'status_menikah' => true,  'jumlah_anak' => 1],
            ['nip' => 'EMP00003', 'nama' => 'Citra Dewi',   'jabatan' => 'staff',      'gaji_pokok' => 6_000_000, 'status_menikah' => false, 'jumlah_anak' => 0],
            ['nip' => 'EMP00004', 'nama' => 'Dewi Lestari', 'jabatan' => 'staff',      'gaji_pokok' => 5_800_000, 'status_menikah' => true,  'jumlah_anak' => 3],
            ['nip' => 'EMP00005', 'nama' => 'Eko Prasetyo', 'jabatan' => 'supervisor', 'gaji_pokok' => 6_500_000, 'status_menikah' => false, 'jumlah_anak' => 0],
        ];

        foreach ($data as $row) {
            $jabatan = Jabatan::where('kode', $row['jabatan'])->first();

            Employee::updateOrCreate(
                ['nip' => $row['nip']],
                [
                    'nama'           => $row['nama'],
                    'jabatan_id'     => $jabatan->id,
                    'gaji_pokok'     => $jabatan->gaji_min,
                    'status_menikah' => $row['status_menikah'],
                    'jumlah_anak'    => $row['jumlah_anak'],
                ]
            );
        }
    }
}
