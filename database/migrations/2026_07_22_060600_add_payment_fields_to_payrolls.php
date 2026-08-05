<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->string('payment_method')->nullable()->after('status'); // bank|cash|other
            $table->string('payment_channel')->nullable()->after('payment_method'); // nama bank / keterangan
            $table->string('payment_account')->nullable()->after('payment_channel'); // no. rekening
            $table->string('payment_note')->nullable()->after('payment_account'); // no bukti / catatan
            $table->timestamp('paid_at')->nullable()->after('payment_note');
            $table->unsignedBigInteger('paid_by')->nullable()->after('paid_at');

            // foreign key to users (admin who menandai dibayar)
            // use nullOnDelete so we don't break payroll records when user is removed
            $table->foreign('paid_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropForeign(['paid_by']);
            $table->dropColumn([
                'payment_method',
                'payment_channel',
                'payment_account',
                'payment_note',
                'paid_at',
                'paid_by',
            ]);
        });
    }
};
