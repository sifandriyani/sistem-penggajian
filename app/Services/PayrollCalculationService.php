<?php

namespace App\Services;

use App\Models\DeductionRate;
use App\Models\Employee;
use Illuminate\Support\Collection;

/**
 * PayrollCalculationService
 * =========================
 * Berisi seluruh ALGORITMA perhitungan gaji karyawan. Sengaja dipisah
 * dari Controller (Single Responsibility Principle) supaya:
 *   1. Bisa diuji secara unit tanpa perlu HTTP request / database.
 *   2. Bisa dipakai ulang (CLI command, job antrian, API, dsb).
 *
 * Referensi aturan yang disederhanakan untuk keperluan demo/uji kompetensi:
 *   - Jam kerja per hari           : 8 jam  -> 173 jam/bulan (standar Kemenaker)
 *   - Lembur                       : 1.5x upah per jam
 *   - BPJS Kesehatan (karyawan)    : 1% dari gaji pokok
 *   - BPJS Ketenagakerjaan (JHT)   : 2% dari gaji pokok
 *   - BPJS Ketenagakerjaan (JP)    : 1% dari gaji pokok
 *
 * CATATAN: PPh21 (pajak penghasilan) sengaja TIDAK dihitung di sistem
 * ini — disederhanakan atas keputusan sadar untuk keperluan demo/uji
 * kompetensi, supaya perhitungan gaji lebih mudah dijelaskan dan
 * dipahami. Di sistem penggajian produksi sungguhan, PPh21 wajib
 * dipotong sesuai peraturan perpajakan yang berlaku.
 */
class PayrollCalculationService
{
    private const JAM_KERJA_PER_BULAN = 173;

    private const DEFAULT_RATE_BPJS_KESEHATAN = 0.01;
    private const DEFAULT_RATE_BPJS_JHT       = 0.02;
    private const DEFAULT_RATE_BPJS_JP        = 0.01;
    private const DEFAULT_RATE_PPH21          = 0.0;
    private const DEFAULT_AMOUNT_ALPHA        = 0.0;

    private array $deductionRates = [];

    public function calculate(Employee $employee, Collection $attendances): array
    {
        $gajiPokok      = (float) $employee->gaji_pokok;
        $hariHadir      = (int) $attendances->where('status', 'hadir')->count();
        $totalTunjangan = $this->calculateTunjangan($employee, $hariHadir);
        $jamLembur      = (float) $attendances->sum('jam_lembur');
        $uangLembur     = $this->calculateOvertimePay($gajiPokok, $jamLembur);
        $bpjsKesehatan  = $this->calculateBpjsKesehatan($gajiPokok);
        $bpjsJht        = $this->calculateBpjsJht($gajiPokok);
        $bpjsJp         = $this->calculateBpjsJp($gajiPokok);
        $pph21          = $this->calculatePph21($employee);
        $alpha          = $this->calculateAlpha($attendances);
        $potonganLain   = 0.0;

        $totalPotongan = $bpjsKesehatan + $bpjsJht + $bpjsJp + $pph21 + $alpha + $potonganLain;
        $penghasilanBruto = $gajiPokok + $totalTunjangan + $uangLembur;
        $gajiBersih       = $penghasilanBruto - $totalPotongan;

        return [
            'gaji_pokok'              => round($gajiPokok, 2),
            'total_tunjangan'         => round($totalTunjangan, 2),
            'uang_lembur'             => round($uangLembur, 2),
            'potongan_bpjs'           => round($bpjsKesehatan + $bpjsJht + $bpjsJp, 2),
            'potongan_bpjs_kesehatan' => round($bpjsKesehatan, 2),
            'potongan_bpjs_jht'       => round($bpjsJht, 2),
            'potongan_bpjs_jp'        => round($bpjsJp, 2),
            'potongan_pph21'          => round($pph21, 2),
            'potongan_alpha'          => round($alpha, 2),
            'potongan_lain'           => round($potonganLain, 2),
            'total_potongan'          => round($totalPotongan, 2),
            'gaji_bersih'             => round(max($gajiBersih, 0), 2),
        ];
    }

    public function calculateTunjangan(Employee $employee, int $hariHadir = 0): float
    {
        $tunjanganJabatan  = $employee->gaji_pokok * $employee->tunjanganJabatanMultiplier();
        $transportPerHari  = (float) optional($employee->jabatanMaster)->transport_per_hari;
        $tunjanganTransport = $transportPerHari * max($hariHadir, 0);
        $tunjanganAnak     = $employee->status_menikah
            ? min($employee->jumlah_anak, 3) * 150_000
            : 0;

        return $tunjanganJabatan + $tunjanganTransport + $tunjanganAnak;
    }

    public function calculateOvertimePay(float $gajiPokok, float $jamLembur): float
    {
        if ($jamLembur <= 0) {
            return 0.0;
        }

        $upahPerJam = $gajiPokok / self::JAM_KERJA_PER_BULAN;

        return $upahPerJam * 1.5 * $jamLembur;
    }

    public function calculateBpjsDeduction(float $gajiPokok): float
    {
        return $this->calculateBpjsKesehatan($gajiPokok)
            + $this->calculateBpjsJht($gajiPokok)
            + $this->calculateBpjsJp($gajiPokok);
    }

    public function calculateBpjsKesehatan(float $gajiPokok): float
    {
        return $this->calculateDeduction('bpjs_kesehatan', $gajiPokok, self::DEFAULT_RATE_BPJS_KESEHATAN);
    }

    public function calculateBpjsJht(float $gajiPokok): float
    {
        return $this->calculateDeduction('bpjs_jht', $gajiPokok, self::DEFAULT_RATE_BPJS_JHT);
    }

    public function calculateBpjsJp(float $gajiPokok): float
    {
        return $this->calculateDeduction('bpjs_jp', $gajiPokok, self::DEFAULT_RATE_BPJS_JP);
    }

    public function calculatePph21(Employee $employee): float
    {
        return $this->calculateDeduction('pph21', $employee->gaji_pokok, self::DEFAULT_RATE_PPH21);
    }

    public function calculateAlpha(Collection $attendances): float
    {
        $absensiTakSah = $attendances->where('status', 'izin')->count() + $attendances->where('status', 'sakit')->count();
        $amountPerAlpha = $this->calculateDeduction('alpha', 0.0, self::DEFAULT_AMOUNT_ALPHA);

        return $absensiTakSah * $amountPerAlpha;
    }

    private function loadDeductionRates(): void
    {
        if (!empty($this->deductionRates)) {
            return;
        }

        $this->deductionRates = DeductionRate::pluck('nilai', 'kode')->toArray();
    }

    private function calculateDeduction(string $kode, float $gajiPokok, float $fallback): float
    {
        $this->loadDeductionRates();

        $rate = isset($this->deductionRates[$kode]) ? $this->deductionRates[$kode] : $fallback;

        if ($kode === 'alpha') {
            return $rate;
        }

        if ($kode === 'pph21') {
            return $rate; // PPh21 disimpan sebagai nilai tetap dalam seeder/konfigurasi
        }

        return $gajiPokok * $rate;
    }
}
