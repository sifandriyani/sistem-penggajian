<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_audits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payroll_id')->nullable();
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->string('action'); // tandai_dibayar, ubah_default_payment, bulk_tandai_dibayar
            $table->json('metadata')->nullable();
            $table->unsignedBigInteger('performed_by')->nullable();
            $table->timestamp('performed_at')->useCurrent();

            $table->foreign('payroll_id')->references('id')->on('payrolls')->cascadeOnDelete();
            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
            $table->foreign('performed_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_audits');
    }
};
