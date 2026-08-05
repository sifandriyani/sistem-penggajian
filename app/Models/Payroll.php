<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model Payroll (Slip Gaji)
 *
 * Hasil akhir dari proses perhitungan gaji satu karyawan pada satu
 * periode (bulan-tahun). Dibuat oleh PayrollCalculationService, tidak
 * boleh dihitung ulang manual di Controller/View agar logika bisnis
 * tetap terpusat (single source of truth).
 */
class Payroll extends Model
{
    use HasFactory;

    protected $table = 'payrolls';

    protected $fillable = [
        'employee_id',
        'periode',              // format: YYYY-MM
        'gaji_pokok',
        'total_tunjangan',
        'uang_lembur',
        'potongan_bpjs',
        'potongan_bpjs_kesehatan',
        'potongan_bpjs_jht',
        'potongan_bpjs_jp',
        'potongan_pph21',
        'potongan_alpha',
        'potongan_lain',
        'total_potongan',
        'gaji_bersih',
        'status',                // belum_dibayar | sudah_dibayar — HANYA admin yang boleh ubah
        // Payment metadata (filled when admin menandai sudah_dibayar)
        'payment_method',
        'payment_channel',
        'payment_account',
        'payment_note',
        'paid_at',
        'paid_by',
    ];

    protected $casts = [
        'gaji_pokok'              => 'decimal:2',
        'total_tunjangan'         => 'decimal:2',
        'uang_lembur'             => 'decimal:2',
        'potongan_bpjs'           => 'decimal:2',
        'potongan_bpjs_kesehatan' => 'decimal:2',
        'potongan_bpjs_jht'       => 'decimal:2',
        'potongan_bpjs_jp'        => 'decimal:2',
        'potongan_pph21'          => 'decimal:2',
        'potongan_alpha'          => 'decimal:2',
        'potongan_lain'           => 'decimal:2',
        'total_potongan'          => 'decimal:2',
        'gaji_bersih'             => 'decimal:2',
        'paid_at'                 => 'datetime',
        // encrypt payment_account so it is not stored as plain text
        'payment_account'         => 'encrypted',
    ];

    public function isSudahDibayar(): bool
    {
        return $this->status === 'sudah_dibayar';
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Admin user who menandai slip ini sudah dibayar
     */
    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }
}

