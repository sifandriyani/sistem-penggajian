<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('payment_audits')) {
            return;
        }

        Schema::table('payment_audits', function (Blueprint $table) {
            if (!Schema::hasColumn('payment_audits', 'ip_address')) {
                $table->string('ip_address')->nullable()->after('performed_by');
            }
            if (!Schema::hasColumn('payment_audits', 'user_agent')) {
                $table->text('user_agent')->nullable()->after('ip_address');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('payment_audits')) {
            return;
        }

        Schema::table('payment_audits', function (Blueprint $table) {
            if (Schema::hasColumn('payment_audits', 'ip_address')) {
                $table->dropColumn('ip_address');
            }
            if (Schema::hasColumn('payment_audits', 'user_agent')) {
                $table->dropColumn('user_agent');
            }
        });
    }
};
