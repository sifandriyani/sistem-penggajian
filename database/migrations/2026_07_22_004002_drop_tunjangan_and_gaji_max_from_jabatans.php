<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jabatans', function (Blueprint $table) {
            if (Schema::hasColumn('jabatans', 'tunjangan')) {
                $table->dropColumn('tunjangan');
            }
            if (Schema::hasColumn('jabatans', 'gaji_max')) {
                $table->dropColumn('gaji_max');
            }
        });
    }

    public function down(): void
    {
        Schema::table('jabatans', function (Blueprint $table) {
            $table->decimal('gaji_max', 14, 2)->nullable()->after('gaji_min');
            $table->decimal('tunjangan', 14, 2)->default(0)->after('gaji_max');
        });
    }
};
