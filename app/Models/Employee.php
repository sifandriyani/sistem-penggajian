<?php

namespace App\Models;

use App\Models\Jabatan;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Model Employee (Karyawan)
 *
 * Merepresentasikan data induk karyawan yang digunakan sebagai basis
 * perhitungan gaji bulanan.
 *
 * @property int    $id
 * @property string $nip                 Nomor Induk Pegawai (unik)
 * @property int    $jabatan_id
 * @property string $jabatan              staff|supervisor|manager
 * @property float  $gaji_pokok
 * @property bool   $status_menikah
 * @property int    $jumlah_anak          Maks. 3 sesuai aturan PTKP
 */
class Employee extends Model
{
    use HasFactory;

    protected $table = 'employees';

    protected $fillable = [
        'nip',
        'nama',
        'jabatan',
        'jabatan_id',
        'gaji_pokok',
        'status_menikah',
        'jumlah_anak',
        // default payment preferences (optional)
        'default_payment_method',
        'default_payment_channel',
        'default_payment_account',
    ];

    protected $casts = [
        'gaji_pokok'          => 'decimal:2',
        'status_menikah'      => 'boolean',
        'jumlah_anak'         => 'integer',
        // treat default_payment_account as encrypted to protect sensitive data
        'default_payment_account' => 'encrypted',
    ];

    /**
     * Riwayat absensi karyawan pada periode tertentu.
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    /**
     * Riwayat slip gaji karyawan per periode.
     */
    public function payrolls(): HasMany
    {
        return $this->hasMany(Payroll::class);
    }

    /**
     * Akun login milik karyawan ini (dibuat otomatis saat karyawan
     * ditambahkan lewat EmployeeController::store()).
     */
    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    public function jabatanMaster(): BelongsTo
    {
        return $this->belongsTo(Jabatan::class, 'jabatan_id');
    }

    public function getJabatanAttribute(): ?string
    {
        return optional($this->jabatanMaster)->kode;
    }

    public function setJabatanAttribute($value): void
    {
        if (is_numeric($value)) {
            $this->attributes['jabatan_id'] = (int) $value;
            return;
        }

        $jabatanId = Jabatan::where('kode', $value)->value('id');

        if ($jabatanId) {
            $this->attributes['jabatan_id'] = $jabatanId;
        }
    }

    /**
     * Multiplier tunjangan jabatan terhadap gaji pokok.
     * Dipisah sebagai method agar mudah diuji (unit test) dan
     * mudah diubah tanpa menyentuh service perhitungan.
     */
    public function tunjanganJabatanMultiplier(): float
    {
        if ($multiplier = optional($this->jabatanMaster)->multiplier) {
            return $multiplier;
        }

        if ($this->jabatan === 'manager') {
            return 0.30;
        }

        if ($this->jabatan === 'supervisor') {
            return 0.20;
        }

        if ($this->jabatan === 'staff') {
            return 0.10;
        }

        return 0.0;
    }

    /**
     * Rentang gaji pokok (skala upah) per jabatan — kebijakan internal
     * perusahaan, BUKAN angka bebas. Batas bawah tiap jabatan mengacu
     * ke UMK setempat sebagai standar minimum; batas atas mengacu ke
     * anggaran/jenjang jabatan. Dipakai untuk validasi saat Tambah/Edit
     * Karyawan (lihat EmployeeController) supaya admin tidak bisa input
     * gaji pokok sembarangan di luar kebijakan jabatannya.
     *
     * Hanya ada 3 jenjang jabatan (Staff, Supervisor, Manager) — dibuat
     * sengaja BEDA JUMLAHNYA dari role login (cuma admin & karyawan)
     * supaya keduanya tidak tertukar secara konsep: "role" itu soal HAK
     * AKSES ke sistem, sedangkan "jabatan" itu cuma atribut kepegawaian
     * yang mempengaruhi besaran tunjangan — sama sekali tidak mengubah
     * apa yang boleh diakses karyawan itu di aplikasi.
     *
     * @return array{min: int, max: int}
     */
    public static function rentangGajiPokok($jabatan): array
    {
        if ($jabatan instanceof Jabatan) {
            return [
                'min' => (int) $jabatan->gaji_min,
                'max' => (int) $jabatan->gaji_max,
            ];
        }

        $rentang = [
            'manager'    => ['min' => 8_000_000, 'max' => 10_000_000],
            'supervisor' => ['min' => 6_000_000, 'max' => 8_000_000],
            'staff'      => ['min' => 4_000_000, 'max' => 6_000_000],
        ];

        return isset($rentang[$jabatan]) ? $rentang[$jabatan] : ['min' => 0, 'max' => PHP_INT_MAX];
    }
}
