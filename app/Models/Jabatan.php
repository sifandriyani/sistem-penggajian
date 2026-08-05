<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Model master untuk daftar jabatan kepegawaian.
 *
 * @property int    $id
 * @property string $kode              staff|supervisor|manager
 * @property string $nama
 * @property string $keterangan        Deskripsi jabatan
 * @property float  $gaji_min          Gaji pokok jabatan
 * @property float  $multiplier        Multiplier untuk tunjangan (tunjangan = gaji_pokok × multiplier)
 * @property float  $transport_per_hari Tunjangan transport per hari kerja
 */
class Jabatan extends Model
{
    use HasFactory;

    protected $table = 'jabatans';

    protected $fillable = [
        'kode',
        'nama',
        'keterangan',
        'gaji_min',
        'multiplier',
        'transport_per_hari',
    ];

    protected $casts = [
        'gaji_min'         => 'decimal:2',
        'multiplier'       => 'decimal:2',
        'transport_per_hari' => 'decimal:2',
    ];

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'jabatan_id');
    }
}
