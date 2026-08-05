# Code Review — Sistem Penggajian

**File yang direview**: `PayrollController.php`, `PayrollCalculationService.php`, `Employee.php`
**Reviewer**: (diisi nama asesi sebagai self-review, atau rekan sejawat)
**Tanggal**: (diisi saat presentasi)

## Checklist

| # | Kriteria | Status | Catatan |
|---|---|---|---|
| 1 | Penamaan variabel/fungsi jelas & konsisten (Bahasa Indonesia untuk istilah domain, Inggris untuk teknis) | ✅ | `calculateOvertimePay`, `$gajiPokok` — konsisten |
| 2 | Tidak ada logika bisnis di Controller (Fat Controller) | ✅ | Semua perhitungan dipindah ke `PayrollCalculationService` |
| 3 | Tidak ada "magic number" tanpa penjelasan | ✅ | Semua angka aturan (173 jam, 22 hari, rate BPJS) jadi `const` bernama + komentar |
| 4 | Query database efisien (hindari N+1) | ✅ (setelah revisi) | Lihat `docs/PROFILING_REPORT.md` — awalnya N+1, sudah diperbaiki dengan `with('employee')` |
| 5 | Validasi input di layer Controller, bukan di View | ✅ | `$request->validate()` di `generate()` dan `EmployeeController::store()` |
| 6 | Mass assignment aman (`$fillable` didefinisikan eksplisit) | ✅ | Semua Model punya `$fillable`, tidak pakai `$guarded = []` |
| 7 | Operasi tulis massal dibungkus transaction | ✅ | `DB::transaction()` di `PayrollController::generate()` |
| 8 | Raw SQL menggunakan parameter binding (bukan string concat) — cegah SQL Injection | ✅ | `DB::select($sql, [$periode])` dan `whereRaw(..., [$periode])`, bukan interpolasi string |
| 9 | Fungsi cukup kecil & fokus (Single Responsibility) | ✅ | `PayrollCalculationService` dipecah jadi 6 method kecil, bukan satu method raksasa |
| 10 | Ada dokumentasi (PHPDoc) untuk method non-trivial | ✅ | Semua method publik di Service & Controller punya PHPDoc |
| 11 | Ada test yang mengcover happy path dan edge case | ✅ | Edge case: gaji bersih tidak boleh negatif, PTKP, generate dua kali tidak duplikat |
| 12 | Penanganan error/validasi periode format salah | ✅ | Diuji di `periode_dengan_format_salah_ditolak_validasi()` |

## Temuan yang Diperbaiki Selama Review

> **Catatan:** komponen PPh21 yang disebut di temuan #1 di bawah ini
> kemudian **dihapus total** dari sistem pada revisi berikutnya (lihat
> `docs/CATATAN_REVISI.md`) untuk menyederhanakan perhitungan gaji.
> Temuan ini tetap dipertahankan sebagai bukti proses code review yang
> pernah dilakukan terhadap kode tersebut.

1. **Sebelum**: `calculatePph21()` sempat ditulis langsung memanggil
   `Employee::find()` di dalam Service — ini melanggar prinsip Service
   seharusnya menerima data, bukan mengambil sendiri dari DB.
   **Sesudah**: Employee dipassing sebagai parameter dari Controller,
   Service jadi murni (pure) dan lebih mudah diuji.

2. **Sebelum**: Konstanta rate BPJS ditulis inline `0.04` langsung di
   dalam formula.
   **Sesudah**: Dipecah jadi 3 konstanta terpisah
   (`RATE_BPJS_KESEHATAN`, `RATE_BPJS_JHT`, `RATE_BPJS_JP`) agar bila
   pemerintah mengubah salah satu komponen, tidak perlu mengubah rumus
   secara keseluruhan.

3. **Sebelum**: Endpoint `generate` tidak memvalidasi format periode,
   berisiko menyimpan data dengan format tanggal tidak konsisten.
   **Sesudah**: Ditambahkan rule `date_format:Y-m` pada validasi.

## Catatan untuk Presentasi
Bagian ini sengaja dijadikan dokumen terpisah agar saat asesor bertanya
*"bagaimana proses code review Anda?"*, asesi dapat menunjukkan bukti
konkret — bukan hanya klaim lisan.
