<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model Attendance (Absensi)
 *
 * Satu baris = satu hari kehadiran seorang karyawan.
 *
 * @property string $status        hadir|izin|sakit
 * @property float  $jam_lembur    Jumlah jam lembur pada hari tersebut
 */
class Attendance extends Model
{
    use HasFactory;

    protected $table = 'attendances';

    protected $fillable = [
        'employee_id',
        'tanggal',
        'status',
        'jam_lembur',
        'keterangan',
        'bukti',
    ];

    protected $casts = [
        'tanggal'    => 'date',
        'jam_lembur' => 'decimal:2',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
