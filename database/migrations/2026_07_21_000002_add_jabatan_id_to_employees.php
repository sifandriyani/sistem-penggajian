<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('jabatan_id')
                ->default(1)
                ->after('nama')
                ->constrained('jabatans')
                ->cascadeOnDelete();
        });

        $employees = DB::table('employees')->select('id', 'jabatan')->get();

        foreach ($employees as $employee) {
            $jabatanId = DB::table('jabatans')
                ->where('kode', $employee->jabatan)
                ->value('id');

            if ($jabatanId) {
                DB::table('employees')
                    ->where('id', $employee->id)
                    ->update(['jabatan_id' => $jabatanId]);
            }
        }

        Schema::table('employees', function (Blueprint $table) {
            $table->dropIndex(['jabatan']);
            $table->dropColumn('jabatan');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->enum('jabatan', ['staff', 'supervisor', 'manager'])->default('staff')->after('nama');
        });

        $employees = DB::table('employees')->select('id', 'jabatan_id')->get();

        foreach ($employees as $employee) {
            $kode = DB::table('jabatans')->where('id', $employee->jabatan_id)->value('kode');

            if ($kode) {
                DB::table('employees')
                    ->where('id', $employee->id)
                    ->update(['jabatan' => $kode]);
            }
        }

        Schema::table('employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('jabatan_id');
        });
    }
};
