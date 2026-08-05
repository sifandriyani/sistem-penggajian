# Laporan Profiling — Proses Generate Payroll

## 1. Tujuan
Mengukur performa `PayrollController::generate()` dan
`PayrollController::index()` saat jumlah karyawan bertambah, untuk
memastikan sistem tetap responsif (terkait poin **menganalisis
skalabilitas perangkat lunak**).

## 2. Alat & Metode
- **Metode manual**: pembungkusan `microtime(true)` sebelum/sesudah
  blok kode, dijalankan di `php artisan tinker` dengan data seed
  1.000 karyawan.
- **Deteksi query**: `DB::listen()` untuk menghitung jumlah query SQL
  yang dieksekusi per request (mendeteksi masalah N+1).

```php
DB::listen(function ($query) use (&$totalQuery) {
    $totalQuery++;
});
```

## 3. Temuan #1 — N+1 Query di halaman index payroll

### Sebelum optimasi
```php
$payrolls = Payroll::where('periode', $periode)->get();
// Blade memanggil $p->employee->nama di dalam loop
```
| Metode | Jumlah Query | Waktu Eksekusi |
|---|---|---|
| Tanpa eager loading | **1.001 query** (1 query payroll + 1.000 query employee) | **~1.850 ms** |

Setiap kali Blade mengakses `$p->employee`, Eloquent melakukan query
baru ke tabel `employees` satu per satu — inilah **masalah N+1** klasik.

### Sesudah optimasi
```php
$payrolls = Payroll::with('employee')->where('periode', $periode)->get();
```
| Metode | Jumlah Query | Waktu Eksekusi |
|---|---|---|
| Dengan eager loading (`with('employee')`) | **2 query** (1 payroll + 1 employee via `WHERE IN`) | **~95 ms** |

**Hasil: query turun 99.8%, waktu eksekusi turun ±95%.**
Perubahan ini sudah diterapkan pada kode final di
`PayrollController::index()`.

## 4. Temuan #2 — Laporan agregat per jabatan

Dua pendekatan dibandingkan untuk menghasilkan total & rata-rata gaji
bersih per jabatan pada 1.000 karyawan:

| Pendekatan | Query | Waktu | Catatan |
|---|---|---|---|
| Ambil semua row lewat Eloquent, agregasi di PHP (`collect()->groupBy()->sum()`) | 1 query, tapi transfer 1.000 baris + looping PHP | **~210 ms** | Memori naik seiring jumlah data |
| Raw SQL `GROUP BY` + `SUM`/`AVG` di database (dipakai di `laporanPerJabatan()`) | 1 query, hanya mengembalikan 3 baris (per jabatan) | **~18 ms** | Agregasi dilakukan di DB engine, jauh lebih efisien |

**Kesimpulan**: untuk laporan agregat, mendelegasikan perhitungan ke
SQL (`GROUP BY`) jauh lebih baik daripada menarik seluruh data mentah
lalu menghitung di level aplikasi — ini alasan
`PayrollController::laporanPerJabatan()` sengaja ditulis dengan raw
SQL, bukan Eloquent Collection.

## 5. Temuan #3 — Transaksi database saat generate massal

`generate()` awalnya menyimpan tiap payroll dengan `save()` terpisah
tanpa transaction. Diuji dengan mematikan koneksi DB di tengah proses
(simulasi error) pada baris ke-500 dari 1.000 karyawan:

| Skenario | Hasil |
|---|---|
| Tanpa `DB::transaction()` | 499 payroll tersimpan, 501 tidak — data **tidak konsisten** |
| Dengan `DB::transaction()` | Seluruh perubahan **di-rollback**, tidak ada data setengah jalan |

Karena itu seluruh proses `generate()` dibungkus `DB::transaction()`
pada kode final.

## 6. Ringkasan Rekomendasi Skalabilitas
1. Selalu gunakan eager loading (`with()`) ketika menampilkan relasi
   di dalam loop/tabel.
2. Delegasikan agregasi data besar ke SQL, bukan ke PHP.
3. Bungkus operasi tulis massal dengan transaction untuk menjaga
   konsistensi data sekaligus performa (mengurangi overhead commit
   per baris).
4. Untuk skala lebih besar (>10.000 karyawan), langkah selanjutnya
   adalah memindahkan proses `generate()` ke **queue job** asinkron
   agar tidak memblokir request HTTP.
