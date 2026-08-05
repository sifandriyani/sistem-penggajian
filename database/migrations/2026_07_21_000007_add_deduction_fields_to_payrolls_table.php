<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->decimal('potongan_bpjs_kesehatan', 14, 2)->default(0)->after('uang_lembur');
            $table->decimal('potongan_bpjs_jht', 14, 2)->default(0)->after('potongan_bpjs_kesehatan');
            $table->decimal('potongan_bpjs_jp', 14, 2)->default(0)->after('potongan_bpjs_jht');
            $table->decimal('potongan_pph21', 14, 2)->default(0)->after('potongan_bpjs_jp');
            $table->decimal('potongan_alpha', 14, 2)->default(0)->after('potongan_pph21');
            $table->decimal('potongan_lain', 14, 2)->default(0)->after('potongan_alpha');
            $table->decimal('total_potongan', 14, 2)->default(0)->after('potongan_lain');
        });
    }

    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropColumn([
                'potongan_bpjs_kesehatan',
                'potongan_bpjs_jht',
                'potongan_bpjs_jp',
                'potongan_pph21',
                'potongan_alpha',
                'potongan_lain',
                'total_potongan',
            ]);
        });
    }
};
