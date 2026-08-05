<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payrolls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->char('periode', 7); // format YYYY-MM
            $table->decimal('gaji_pokok', 14, 2);
            $table->decimal('total_tunjangan', 14, 2);
            $table->decimal('uang_lembur', 14, 2);
            $table->decimal('potongan_bpjs', 14, 2);
            $table->decimal('gaji_bersih', 14, 2);
            $table->enum('status', ['belum_dibayar', 'sudah_dibayar'])->default('belum_dibayar');
            $table->timestamps();

            $table->unique(['employee_id', 'periode']);
            $table->index('periode');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payrolls');
    }
};
