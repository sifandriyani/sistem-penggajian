<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('nip')->unique();
            $table->string('nama');
            $table->enum('jabatan', ['staff', 'supervisor', 'manager'])->default('staff');
            $table->decimal('gaji_pokok', 14, 2);
            $table->decimal('tunjangan_transport', 14, 2)->default(0);
            $table->boolean('status_menikah')->default(false);
            $table->unsignedTinyInteger('jumlah_anak')->default(0);
            $table->timestamps();

            $table->index('jabatan');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
