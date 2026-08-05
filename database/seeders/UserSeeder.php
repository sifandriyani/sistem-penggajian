<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Membuat:
 *  - 1 akun admin (HRD) — bisa kelola karyawan & generate payroll
 *  - 1 akun karyawan per baris di tabel employees — hanya bisa lihat
 *    slip gajinya sendiri (login dengan email masing-masing)
 *
 * NOTE: password default HANYA untuk demo/presentasi ke asesor.
 * Jangan dipakai di lingkungan produksi sungguhan.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@payroll.test'],
            [
                'name'     => 'Admin HRD',
                'password' => Hash::make('password'),
                'role'     => 'admin',
            ]
        );

        // Buat 1 akun login untuk setiap karyawan yang sudah di-seed
        // oleh EmployeeSeeder, agar masing-masing bisa login dan lihat
        // slip gajinya sendiri.
        Employee::all()->each(function (Employee $employee) {
            $emailSlug = strtolower(str_replace(' ', '.', $employee->nama));

            User::updateOrCreate(
                ['email' => "{$emailSlug}@payroll.test"],
                [
                    'name'        => $employee->nama,
                    'password'    => Hash::make('password'),
                    'role'        => 'karyawan',
                    'employee_id' => $employee->id,
                ]
            );
        });
    }
}
