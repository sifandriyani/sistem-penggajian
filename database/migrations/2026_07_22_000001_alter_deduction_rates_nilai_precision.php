<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deduction_rates', function (Blueprint $table) {
            $table->decimal('nilai', 15, 4)->change();
        });
    }

    public function down(): void
    {
        Schema::table('deduction_rates', function (Blueprint $table) {
            $table->decimal('nilai', 8, 4)->change();
        });
    }
};
