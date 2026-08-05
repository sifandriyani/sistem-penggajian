<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jabatans', function (Blueprint $table) {
            if (!Schema::hasColumn('jabatans', 'keterangan')) {
                $table->text('keterangan')->nullable()->after('nama');
            }
            if (!Schema::hasColumn('jabatans', 'tunjangan')) {
                $table->decimal('tunjangan', 14, 2)->default(0)->after('gaji_min');
            }
        });
    }

    public function down(): void
    {
        Schema::table('jabatans', function (Blueprint $table) {
            if (Schema::hasColumn('jabatans', 'keterangan')) {
                $table->dropColumn('keterangan');
            }
            if (Schema::hasColumn('jabatans', 'tunjangan')) {
                $table->dropColumn('tunjangan');
            }
        });
    }
};
