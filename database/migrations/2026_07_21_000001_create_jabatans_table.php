<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jabatans', function (Blueprint $table) {
            $table->id();
            $table->string('kode')->unique();
            $table->string('nama');
            $table->decimal('gaji_min', 14, 2);
            $table->decimal('gaji_max', 14, 2);
            $table->decimal('multiplier', 5, 2);
            $table->timestamps();
        });

        DB::table('jabatans')->insert([
            [
                'kode'       => 'staff',
                'nama'       => 'Staff',
                'gaji_min'   => 4_000_000,
                'gaji_max'   => 6_000_000,
                'multiplier' => 0.10,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'kode'       => 'supervisor',
                'nama'       => 'Supervisor',
                'gaji_min'   => 6_000_000,
                'gaji_max'   => 8_000_000,
                'multiplier' => 0.20,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'kode'       => 'manager',
                'nama'       => 'Manager',
                'gaji_min'   => 8_000_000,
                'gaji_max'   => 10_000_000,
                'multiplier' => 0.30,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('jabatans');
    }
};
