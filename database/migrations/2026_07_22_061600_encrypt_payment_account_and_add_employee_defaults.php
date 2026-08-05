<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;

return new class extends Migration
{
    public function up(): void
    {
        // 1) Tambah kolom sementara untuk menyimpan versi terenkripsi
        Schema::table('payrolls', function (Blueprint $table) {
            $table->text('payment_account_encrypted')->nullable()->after('payment_channel');
        });

        // 2) Encrypt existing payment_account values into payment_account_encrypted
        $rows = DB::table('payrolls')->select('id', 'payment_account')->whereNotNull('payment_account')->get();
        foreach ($rows as $row) {
            try {
                $enc = Crypt::encryptString($row->payment_account);
                DB::table('payrolls')->where('id', $row->id)->update(['payment_account_encrypted' => $enc]);
            } catch (\Throwable $e) {
                // skip problematic rows but continue
            }
        }

        // 3) Drop old payment_account column and rename encrypted column
        Schema::table('payrolls', function (Blueprint $table) {
            if (Schema::hasColumn('payrolls', 'payment_account')) {
                $table->dropColumn('payment_account');
            }
            $table->renameColumn('payment_account_encrypted', 'payment_account');
        });

        // 4) Tambah kolom default payment di employees (simpan default method/nama bank/akun)
        Schema::table('employees', function (Blueprint $table) {
            // don't assume specific column order (some schemas differ); just add nullable columns
            if (! Schema::hasColumn('employees', 'default_payment_method')) {
                $table->string('default_payment_method')->nullable(); // bank|cash|other
            }
            if (! Schema::hasColumn('employees', 'default_payment_channel')) {
                $table->string('default_payment_channel')->nullable();
            }
            if (! Schema::hasColumn('employees', 'default_payment_account')) {
                $table->text('default_payment_account')->nullable();
            }
        });

        // Note: default_payment_account will be treated as encrypted at model level (cast).
    }

    public function down(): void
    {
        // revert employees additions
        Schema::table('employees', function (Blueprint $table) {
            if (Schema::hasColumn('employees', 'default_payment_account')) $table->dropColumn('default_payment_account');
            if (Schema::hasColumn('employees', 'default_payment_channel')) $table->dropColumn('default_payment_channel');
            if (Schema::hasColumn('employees', 'default_payment_method')) $table->dropColumn('default_payment_method');
        });

        // revert payrolls: we cannot recover plaintext payment_account, so just drop column
        Schema::table('payrolls', function (Blueprint $table) {
            if (Schema::hasColumn('payrolls', 'payment_account')) $table->dropColumn('payment_account');
            // add back original nullable column to avoid breaking app expectations
            $table->string('payment_account')->nullable()->after('payment_channel');
        });
    }
};
