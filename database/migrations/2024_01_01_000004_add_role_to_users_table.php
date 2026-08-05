<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menambahkan role & tautan ke Employee pada tabel `users` bawaan Laravel.
 *
 *  - role = 'admin'    -> HRD, bisa kelola karyawan & generate payroll
 *  - role = 'karyawan' -> hanya bisa login & lihat slip gajinya sendiri
 *    (employee_id WAJIB diisi untuk role karyawan, NULL untuk admin)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'karyawan'])->default('karyawan')->after('email');
            $table->foreignId('employee_id')->nullable()->after('role')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('employee_id');
            $table->dropColumn('role');
        });
    }
};
