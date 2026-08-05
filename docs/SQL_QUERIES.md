# Dokumentasi Query SQL

Dokumen ini merangkum seluruh query SQL signifikan yang dipakai aplikasi,
baik yang ditulis lewat Eloquent (query builder) maupun raw SQL murni,
untuk kebutuhan penilaian poin kompetensi **"Menggunakan SQL"**.

## 1. DDL — Skema Tabel (dari migration)

```sql
CREATE TABLE employees (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nip VARCHAR(255) UNIQUE NOT NULL,
    nama VARCHAR(255) NOT NULL,
    jabatan ENUM('staff','supervisor','manager') DEFAULT 'staff',
    gaji_pokok DECIMAL(14,2) NOT NULL,
    status_menikah BOOLEAN DEFAULT FALSE,
    jumlah_anak TINYINT UNSIGNED DEFAULT 0,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX (jabatan)
);

CREATE TABLE jabatans (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kode VARCHAR(255) UNIQUE NOT NULL,
    nama VARCHAR(255) NOT NULL,
    gaji_min DECIMAL(14,2) NOT NULL,
    multiplier DECIMAL(5,2) NOT NULL,
    transport_per_hari DECIMAL(13,2) DEFAULT 0,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);

CREATE TABLE attendances (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id BIGINT UNSIGNED NOT NULL,
    tanggal DATE NOT NULL,
    status ENUM('hadir','izin','sakit') DEFAULT 'hadir',
    jam_lembur DECIMAL(5,2) DEFAULT 0,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE (employee_id, tanggal),
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
);

CREATE TABLE payrolls (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id BIGINT UNSIGNED NOT NULL,
    periode CHAR(7) NOT NULL,
    gaji_pokok DECIMAL(14,2) NOT NULL,
    total_tunjangan DECIMAL(14,2) NOT NULL,
    uang_lembur DECIMAL(14,2) NOT NULL,
    potongan_bpjs DECIMAL(14,2) NOT NULL,
    gaji_bersih DECIMAL(14,2) NOT NULL,
    status ENUM('belum_dibayar','sudah_dibayar') NOT NULL DEFAULT 'belum_dibayar',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE (employee_id, periode),
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
);
```

## 2. DML — Query Operasional

### a. Mengambil absensi karyawan pada periode berjalan (whereRaw)
```sql
SELECT * FROM attendances
WHERE employee_id = ?
  AND DATE_FORMAT(tanggal, '%Y-%m') = ?;
```
Dipakai di: `PayrollController::generate()`

### b. Simpan/Update hasil payroll tanpa duplikat (upsert logis)
```sql
-- Setara dengan Payroll::updateOrCreate(...)
INSERT INTO payrolls (employee_id, periode, gaji_pokok, ..., gaji_bersih)
VALUES (?, ?, ?, ..., ?)
ON DUPLICATE KEY UPDATE
    gaji_pokok = VALUES(gaji_pokok),
    total_tunjangan = VALUES(total_tunjangan),
    ...
    gaji_bersih = VALUES(gaji_bersih);
```

### c. Laporan agregat per jabatan (raw SQL, JOIN + GROUP BY)
```sql
SELECT
    e.jabatan,
    COUNT(*)                AS jumlah_karyawan,
    SUM(p.gaji_bersih)      AS total_gaji_bersih,
    AVG(p.gaji_bersih)      AS rata_rata_gaji_bersih,
    SUM(p.uang_lembur)      AS total_uang_lembur
FROM payrolls p
INNER JOIN employees e ON e.id = p.employee_id
WHERE p.periode = ?
GROUP BY e.jabatan
ORDER BY total_gaji_bersih DESC;
```
Dipakai di: `PayrollController::laporanPerJabatan()`
Lihat perbandingan performa raw SQL vs agregasi PHP di
`docs/PROFILING_REPORT.md` (Temuan #2).

### d. Mengambil payroll + relasi karyawan tanpa N+1 (Eloquent eager load)
```sql
SELECT * FROM payrolls WHERE periode = ? ORDER BY id;
SELECT * FROM employees WHERE id IN (?, ?, ?, ...); -- 1 query untuk semua employee
```
Dihasilkan otomatis oleh `Payroll::with('employee')->where(...)->get()`.

## 3. Keamanan Query
Seluruh raw SQL di atas menggunakan **parameter binding** (`?`) —
bukan penggabungan string — untuk mencegah SQL Injection. Ini dicek
ulang saat code review (lihat `docs/CODE_REVIEW_CHECKLIST.md`, poin 8).
