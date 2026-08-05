# Dokumentasi Kode Program — Sistem Penggajian Berbasis Web

## 1. Ringkasan
Aplikasi ini dibangun dengan **Laravel 11** untuk mendemonstrasikan siklus
analisis-desain-implementasi sebuah sistem informasi penggajian, sesuai
studi kasus proyek singkat skema **Analis Program (SKM-2019-62010-02)**.

## 2. Arsitektur & Alasan Desain
Aplikasi mengikuti pola **MVC + Service Layer**:

```
HTTP Request
    │
    ▼
routes/web.php ──► Http/Controllers (orkestrasi, validasi, HTTP)
    │
    ▼
Services/PayrollCalculationService (ALGORITMA murni, tanpa DB/HTTP)
    │
    ▼
Models (Eloquent, akses basis data) ──► MySQL
    │
    ▼
resources/views (Blade, tampilan)
```

**Alasan** memisahkan `PayrollCalculationService` dari Controller:
1. **Skalabilitas** — logika bisa dipanggil dari Controller, Artisan
   Command, Queue Job, maupun API tanpa duplikasi kode.
2. **Testability** — dapat diuji unit tanpa perlu HTTP request/DB
   (lihat `tests/Unit/PayrollCalculationServiceTest.php`).
3. **Single Responsibility** — Controller hanya bertugas menerima
   request dan mengembalikan response; Model hanya representasi data;
   Service berisi aturan bisnis.

## 3. Struktur Direktori
| Path | Isi |
|---|---|
| `app/Models/` | Employee, Attendance, Payroll (Eloquent ORM) |
| `app/Services/PayrollCalculationService.php` | Algoritma perhitungan gaji |
| `app/Http/Controllers/` | PayrollController, EmployeeController |
| `database/migrations/` | Skema tabel: employees, attendances, payrolls |
| `database/seeders/` | Data contoh untuk demo |
| `resources/views/payroll/` | Tampilan Blade |
| `tests/Unit/` | Unit test algoritma perhitungan |
| `tests/Feature/` | Integration test alur end-to-end |
| `docs/` | Dokumen pendukung (dokumen ini, debugging, profiling, review, SQL) |

## 4. Skema Basis Data (ERD sederhana)

```
employees (1) ────< (N) attendances
employees (1) ────< (N) payrolls
```

| Tabel | Kolom Kunci | Keterangan |
|---|---|---|
| `employees` | nip (unik), jabatan, gaji_pokok | Data induk karyawan |
| `attendances` | employee_id, tanggal, status, jam_lembur | Log kehadiran harian |
| `payrolls` | employee_id, periode (YYYY-MM), gaji_bersih | Hasil perhitungan gaji per periode |

## 5. Alur Bisnis Utama: Generate Payroll
1. User memilih periode (bulan-tahun) dan menekan "Generate Payroll".
2. `PayrollController::generate()` mengambil seluruh `Employee` beserta
   `Attendance` pada periode tersebut (akses basis data).
3. Untuk setiap karyawan, `PayrollCalculationService::calculate()`
   menghitung: tunjangan, lembur, potongan BPJS, hingga gaji bersih
   (algoritma pemrograman).
4. Hasil disimpan dengan `updateOrCreate` di dalam **DB transaction**
   agar proses generate ulang tidak menghasilkan duplikat maupun data
   setengah jalan bila terjadi error di tengah proses.
5. Hasil ditampilkan di `payroll.index`, detail slip di `payroll.slip`,
   dan agregat laporan (raw SQL) di `payroll.laporan`.

## 6. Autentikasi & Otorisasi (Role)
Aplikasi punya 2 peran login, disimpan di kolom `users.role`:

| Role | Ditandai oleh | Akses |
|---|---|---|
| `admin` | `employee_id = null` | Semua route di bawah middleware `admin` (kelola karyawan, generate payroll, laporan) |
| `karyawan` | `employee_id` terisi, menunjuk ke 1 baris `employees` | Hanya route `/slip-saya` dan `/slip/{payroll}` **miliknya sendiri** |

Alasan `employee_id` diletakkan di tabel `users` (bukan sebaliknya):
satu karyawan pasti hanya punya satu akun login, jadi relasi
one-to-one ini paling natural ditaruh sebagai foreign key di `users`.

Pengecekan hak akses dilakukan di dua lapis:
1. **Middleware** (`EnsureIsAdmin`) — memblokir role selain admin dari
   route kelompok admin sebelum masuk ke Controller sama sekali.
2. **Controller** (`PayrollController::slip()`) — karena admin dan
   karyawan sama-sama boleh mengakses route `payroll.slip`, tapi
   karyawan hanya boleh untuk `Payroll` miliknya sendiri, pengecekan
   kepemilikan data (bukan cuma role) harus dilakukan di level
   Controller, bukan middleware.

## 7. Konvensi Kode
- **PSR-12** untuk gaya penulisan PHP.
- Nama variabel & komentar bisnis dalam **Bahasa Indonesia** (mengikuti
  domain penggajian lokal — istilah BPJS tidak diterjemahkan agar
  tetap presisi secara hukum).
- Setiap method publik pada `PayrollCalculationService` memiliki
  PHPDoc yang menjelaskan formula dan satuan yang dipakai.
- Tidak ada query mentah di dalam Model — raw SQL hanya di
  `PayrollController::laporanPerJabatan()` untuk kebutuhan agregasi
  yang lebih efisien dibanding memuat seluruh Collection ke memori.
