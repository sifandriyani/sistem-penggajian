# Log Debugging — Perhitungan Uang Lembur

## Ringkasan
| | |
|---|---|
| **Modul** | `PayrollCalculationService::calculateOvertimePay()` |
| **Ditemukan saat** | Menjalankan unit test setelah implementasi pertama |
| **Severity** | Tinggi — berdampak langsung ke gaji bersih karyawan |
| **Status** | Fixed & regression test ditambahkan |

## 1. Gejala
Saat menjalankan:
```bash
php artisan test --filter=upah_lembur_dihitung_berdasar_173_jam_kerja_per_bulan
```
Test **gagal** dengan output kurang lebih:
```
Failed asserting that 450000.0 matches expected 300000.0.
```

## 2. Kode Awal (buggy)
```php
public function calculateOvertimePay(float $gajiPokok, float $jamLembur): float
{
    if ($jamLembur <= 0) {
        return 0.0;
    }

    // BUG: memakai 30 hari kalender x 8 jam, bukan jam kerja efektif bulanan
    $upahPerJam = $gajiPokok / (30 * 8);

    return $upahPerJam * 1.5 * $jamLembur;
}
```

## 3. Proses Investigasi
1. **Reproduksi** — Menjalankan ulang test unit dengan input tetap
   (`gaji_pokok = 8.650.000`, `jam_lembur = 4`) untuk memastikan bug
   konsisten, bukan flaky test.
2. **Isolasi** — Menambahkan `dd($upahPerJam)` sementara sebelum baris
   `return` untuk melihat nilai upah per jam yang dihasilkan:
   nilainya **Rp 36.041**, sedangkan seharusnya **Rp 50.000**
   (`8.650.000 / 173`).
3. **Root cause analysis** — Ternyata pembagi yang dipakai adalah
   `30 hari x 8 jam = 240 jam`, padahal standar jam kerja efektif
   bulanan di Indonesia adalah **173 jam** (memperhitungkan hari
   libur/akhir pekan). Nilai `240` diambil asumsi awal yang keliru
   saat coding cepat, tidak dicek ulang terhadap referensi aturan
   ketenagakerjaan.
4. **Dampak jika tidak diperbaiki**: upah lembur per jam menjadi lebih
   kecil ±28% dari seharusnya, merugikan karyawan secara sistemik
   setiap kali lembur dihitung.

## 4. Perbaikan (fix)
```php
private const JAM_KERJA_PER_BULAN = 173;

public function calculateOvertimePay(float $gajiPokok, float $jamLembur): float
{
    if ($jamLembur <= 0) {
        return 0.0;
    }

    $upahPerJam = $gajiPokok / self::JAM_KERJA_PER_BULAN;

    return $upahPerJam * 1.5 * $jamLembur;
}
```
Konstanta `JAM_KERJA_PER_BULAN` juga didokumentasikan di bagian atas
class agar tidak ada "magic number" tersembunyi lagi di masa depan.

## 5. Verifikasi
```bash
php artisan test --filter=PayrollCalculationServiceTest
```
Hasil: **semua 10 test PASS**, termasuk test baru yang khusus dibuat
untuk mengunci nilai `173` ini sebagai regression test, sehingga bug
yang sama tidak akan lolos lagi di masa depan tanpa terdeteksi.

## 6. Pelajaran (Lessons Learned)
- Nilai konstanta bisnis (173 jam kerja, 22 hari kerja, dsb.) harus
  divalidasi ke sumber aturan resmi, bukan diasumsikan.
- Unit test yang menguji angka aktual (bukan hanya "tidak error")
  sangat efektif menangkap bug logika seperti ini sejak awal,
  sebelum sampai ke tahap integrasi apalagi produksi.
