# Catatan Revisi — Sistem Informasi Penggajian

Dokumen ini mencatat 4 perbaikan yang dilakukan atas hasil review awal,
sesuai kompetensi Analis Program (debugging, code review, implementasi
algoritma, akses basis data, dokumentasi kode).

## 1. Login & pemisahan akses admin/karyawan

**Temuan:** route/middleware login sebenarnya sudah ada
(`routes/web.php`, `EnsureIsAdmin`), tetapi `AuthController::dashboard()`
langsung `redirect()` tanpa pernah benar-benar merender halaman Beranda —
sehingga terkesan "tidak ada beranda" dan hanya admin yang punya halaman
berguna (karyawan langsung dilempar ke `slip-saya`).

**Perbaikan:** `AuthController::dashboard()` sekarang merender view
`dashboard.blade.php` dengan data yang BERBEDA sesuai role:
- Admin: total karyawan, total payroll bulan berjalan, grafik jumlah
  karyawan per jabatan, 5 payroll terbaru.
- Karyawan: 5 slip gaji terakhir miliknya sendiri.

Middleware `auth` + `admin` (alias di `bootstrap/app.php`) tetap
memisahkan route `/karyawan`, `/payroll`, `/payroll/laporan-per-jabatan`
hanya untuk admin.

## 2. CRUD Data Karyawan

**Temuan:** `EmployeeController` hanya punya `index()` dan `store()` —
tidak ada `update()`/`destroy()`, dan view `payroll/employees.blade.php`
adalah HTML polos tanpa tombol edit/hapus.

**Perbaikan:**
- Tambah `EmployeeController::update()` dan `destroy()` + route
  `PUT /karyawan/{employee}` dan `DELETE /karyawan/{employee}`.
- View diubah total: pakai layout sidebar, tombol "Tambah Karyawan"
  (modal), tombol edit (modal, prefilled) dan hapus (modal konfirmasi)
  di setiap baris. Field jabatan pakai dropdown, status menikah pakai
  checkbox (dengan hidden fallback `0` supaya checkbox yang tidak
  dicentang tetap terkirim sebagai `false`, bukan hilang dari request).
- Menghapus karyawan otomatis menghapus riwayat payroll-nya karena
  `cascadeOnDelete()` sudah ada di migration.

## 3. Edit Data Penggajian & gaji bersih otomatis

**Temuan:** `PayrollController` hanya bisa `generate()` (hitung ulang
semua karyawan) — tidak ada cara mengoreksi satu baris payroll (mis.
lembur susulan, koreksi potongan). Gaji bersih juga statis, tidak
mengikuti perubahan komponen lain.

**Perbaikan:**
- Tambah `PayrollController::update()` + route
  `PUT /payroll/{payroll}`. Field yang bisa diedit: tunjangan, lembur,
  potongan BPJS, potongan PPh21, potongan alpha.
- `gaji_pokok` SENGAJA tidak ikut diedit di form ini — ia snapshot dari
  `Employee::gaji_pokok` saat payroll digenerate. Mengubah gaji pokok
  karyawan dilakukan lewat Data Karyawan, dan baru berlaku saat payroll
  di-generate ULANG (sesuai permintaan: histori payroll lama tidak
  perlu berubah).
- `gaji_bersih` **selalu dihitung ulang di server** setiap kali salah
  satu komponen diubah:
  `gaji_pokok + tunjangan + lembur - bpjs - pph21 - alpha`.
  Form juga punya live-preview (JavaScript) supaya user langsung lihat
  perkiraan gaji bersih saat mengetik, tapi nilai final tetap dihitung
  ulang di server (tidak dipercaya dari input klien).
- Tambah `PayrollController::destroy()` untuk menghapus baris payroll
  yang salah generate.

## 4. Laporan rinci, bukan cuma ringkasan

**Temuan:** `laporanPerJabatan()` hanya menampilkan agregat per jabatan
(SUM/AVG via raw SQL) — tidak ada detail per karyawan.

**Perbaikan:** `laporanPerJabatan()` sekarang mengembalikan dua data:
1. `rows` — ringkasan agregat per jabatan (raw SQL, tidak diubah).
2. `detail` — rincian LENGKAP tiap karyawan pada periode tsb (semua
   komponen gaji), plus `grandTotal` (SUM semua kolom).

View laporan menampilkan kedua tabel tsb + tombol cetak (`window.print()`).

## Perbaikan tampilan (sidebar & Beranda)

- Sidebar dirapikan: label diganti "Beranda" (bukan "Dashboard") dan
  urutan menu mengikuti permintaan: Beranda → Data Karyawan → Data
  Penggajian → Laporan → Logout. Menu aktif kini di-highlight otomatis.
- Semua halaman (`employees`, `payroll/index`, `laporan`, `slip-saya`,
  `slip`) diubah dari HTML polos berdiri sendiri menjadi
  `@extends('layouts.app')` supaya konsisten memakai sidebar + navbar
  Bootstrap yang sama.
## 5. Penghapusan komponen "Potongan Alpha"

**Permintaan:** Sistem tidak perlu ada potongan gaji berdasarkan status
kehadiran "alpha" (tanpa keterangan).

**Perubahan:**
- `attendances.status` enum diubah dari `hadir|izin|sakit|alpha` menjadi
  `hadir|izin|sakit` (kolom `alpha` dihapus dari opsi status).
- Kolom `payrolls.potongan_alpha` dihapus dari migration, model
  `Payroll`, dan seluruh tampilan (daftar payroll, slip gaji, laporan,
  modal edit + live-preview JavaScript-nya).
- `PayrollCalculationService::calculateAlphaDeduction()` dan konstanta
  `HARI_KERJA_PER_BULAN` (yang hanya dipakai method itu) dihapus.
  Rumus `gaji_bersih` sekarang:
  `gaji_pokok + tunjangan + lembur - bpjs - pph21` (tanpa potongan alpha).
- **PPh21 tidak terpengaruh sama sekali** oleh perubahan ini — dari awal
  potongan alpha dan PPh21 dihitung terpisah; PPh21 hanya berbasis
  `gaji_pokok + tunjangan + lembur` (lihat `calculatePph21()`), bukan
  dari komponen potongan apa pun. Jadi penghapusan alpha tidak mengubah
  hasil perhitungan pajak.
- Unit test `potongan_alpha_dihitung_berdasar_22_hari_kerja` dihapus;
  test skenario "gaji bersih tidak boleh negatif" diganti memakai
  `gaji_pokok = 0` (skenario alpha yang lama sudah tidak relevan tanpa
  komponen alpha). Test integrasi `absensi_alpha_mengurangi_gaji_bersih`
  diganti jadi `absensi_dengan_jam_lembur_menambah_gaji_bersih` yang
  tetap membuktikan data absensi memengaruhi hasil generate payroll,
  lewat komponen lembur (satu-satunya yang tersisa dari data absensi).

## 6. Akun login otomatis saat tambah karyawan baru

**Temuan:** `EmployeeController::store()` hanya membuat baris di tabel
`employees`, tidak membuat akun `users` — karyawan baru yang ditambah
lewat halaman admin tidak bisa login sama sekali.

**Perbaikan:** `store()` sekarang, dalam satu `DB::transaction()`,
sekaligus membuat akun `User` (role `karyawan`, `employee_id` merujuk
ke karyawan baru) dengan email yang di-generate otomatis dari nama
(disambiguasi otomatis jika ada nama kembar) dan password acak 8
karakter. Password plain-text ini ditampilkan SEKALI ke admin lewat
modal konfirmasi setelah submit (bukan disimpan plain di database —
tetap di-hash dengan `Hash::make()`), supaya admin bisa menyampaikannya
ke karyawan bersangkutan.

## 7. Tampilan login disamakan dengan tema dashboard

**Keluhan:** halaman login masih pakai gaya lama (font Arial polos,
warna beda sendiri), tidak senada dengan dashboard yang sudah pakai
Bootstrap 5 + sidebar navy gelap + aksen biru.

**Perbaikan:** `resources/views/auth/login.blade.php` ditulis ulang
memakai Bootstrap 5 dan Bootstrap Icons yang sama persis dengan
`layouts/app.blade.php`, dengan brand "Payroll" dan ikon yang sama
seperti di sidebar (`bi-cash-stack`), palet warna navy (#1e293b) +
biru (#2563eb) yang konsisten dengan seluruh aplikasi.

## 8. PPh21 dihapus total dari sistem

**Alasan:** disederhanakan atas permintaan, supaya perhitungan gaji
lebih mudah dijelaskan dan tidak menyusahkan. BPJS TETAP dipertahankan
(rumusnya jauh lebih sederhana — flat 4% dari gaji pokok, tanpa
PTKP/tarif progresif seperti PPh21) supaya sistem tetap punya contoh
logika potongan gaji, bukan cuma penjumlahan.

**Perubahan:**
- Kolom `payrolls.potongan_pph21` dihapus dari migration, model
  `Payroll`, dan seluruh tampilan (daftar payroll, slip gaji, laporan,
  modal edit + live-preview JavaScript-nya).
- `PayrollCalculationService::calculatePph21()` dan
  `applyProgressiveTax()` dihapus beserta seluruh konstanta PTKP.
  Rumus `gaji_bersih` sekarang: `gaji_pokok + tunjangan + lembur - bpjs`.
- Unit test `pph21_nol_jika_penghasilan_tahunan_di_bawah_ptkp` dan
  `pph21_menikah_dengan_anak_memiliki_ptkp_lebih_besar` dihapus.
- Dicatat juga di `docs/CODE_REVIEW_CHECKLIST.md` sebagai catatan
  histori supaya temuan review lama tidak membingungkan pembaca.

**Diklarifikasi ke asesi:** BPJS TIDAK bisa digabung jadi "tunjangan"
karena arah efeknya berlawanan — tunjangan menambah gaji kotor,
sedangkan BPJS mengurangi gaji bersih (potongan). Menggabungkannya
akan membuat perhitungan jadi salah secara akuntansi.

## 9. Self-service kehadiran: karyawan input sendiri

**Keluhan:** data kehadiran & lembur sebelumnya cuma bisa diinput HRD
untuk semua karyawan — tidak realistis, karena HRD tidak mungkin tahu
jam lembur tiap karyawan tiap hari.

**Perbaikan:** `AttendanceController` mendapat 4 method self-service
baru (`mine()`, `storeMine()`, `updateMine()`, `destroyMine()`) yang
dipakai karyawan lewat menu baru "Kehadiran Saya" di sidebar:
- `employee_id` SELALU dipaksa dari akun yang sedang login (bukan dari
  input form), supaya karyawan tidak bisa input atas nama orang lain.
- Karyawan tidak bisa edit/hapus data kehadiran karyawan lain
  (otorisasi kepemilikan dicek eksplisit, mengembalikan 403).
- Tidak bisa input tanggal yang belum terjadi (`before_or_equal:today`).
- Tidak bisa input dobel di tanggal yang sama (constraint unique tetap
  berlaku).

Menu admin "Data Kehadiran" tetap ada (diganti label jadi
**"Data Kehadiran (Semua)"**) — fungsinya sekarang jadi alat kontrol/
koreksi HRD (misal karyawan lupa input), bukan jalur input rutin
harian. Dashboard karyawan juga dapat tombol cepat "Input Kehadiran".

Ditambahkan `tests/Feature/AttendanceSelfServiceTest.php` (8 test)
yang membuktikan seluruh batasan otorisasi di atas.

**Catatan soal jabatan vs role:** sempat muncul kebingungan bahwa ada
"3 role" (manager/supervisor/staff). Diklarifikasi: itu adalah kolom
`jabatan` di tabel `employees` (dipakai untuk menghitung besaran
tunjangan saja), BUKAN role login. Role login di sistem ini tetap
cuma 2: `admin` dan `karyawan` — karyawan dengan jabatan apapun
(staff/supervisor/manager) sama-sama login sebagai role `karyawan` dan
punya akses yang identik (kelola kehadiran & lihat slip gaji sendiri).

## Revisi Lanjutan: Admin read-only untuk Kehadiran + UX Beranda

**Temuan:** Admin (HRD) masih punya route & tombol untuk menambah/mengedit/
menghapus data kehadiran di menu "Data Kehadiran". Ini janggal secara
konsep karena kehadiran seharusnya input asli dari karyawan sendiri —
kalau admin bisa ikut menambah/mengedit, tidak ada jaminan datanya
benar-benar mencerminkan kehadiran nyata.

**Perbaikan:**
- Route `POST/PUT/DELETE /kehadiran` (khusus admin) **dihapus total**.
  Admin sekarang hanya punya `GET /kehadiran` (read-only).
- `AttendanceController::store()/update()/destroy()` versi admin
  dihapus. Yang tersisa untuk admin cuma `index()`.
- View `payroll/attendance.blade.php` diganti jadi halaman pantauan:
  rekap per karyawan (jumlah hadir/izin/sakit + total jam lembur) +
  log harian lengkap — tanpa tombol Tambah/Edit/Hapus sama sekali.
- Karyawan tetap satu-satunya yang bisa CRUD kehadiran miliknya sendiri
  lewat `karyawan.kehadiran.*` (sudah ada otorisasi kepemilikan di
  `updateMine()`/`destroyMine()`).
- Ditambah test `admin_hanya_bisa_melihat_kehadiran_tidak_bisa_menambah`
  yang memastikan POST admin ke `/kehadiran` sekarang 404.

**Perbaikan UX Beranda karyawan:**
- Kartu "Belum input kehadiran hari ini?" sebelumnya SELALU muncul dan
  linknya cuma mengarah ke halaman kehadiran (masih harus klik "Tambah
  Kehadiran" lagi di sana — dua langkah, membingungkan).
- Sekarang `AuthController::dashboard()` mengecek dulu apakah karyawan
  sudah punya catatan kehadiran untuk **hari ini**. Kartu ajakan hanya
  muncul kalau memang belum, dan tombolnya ("Isi Kehadiran Sekarang")
  langsung membuka modal Tambah Kehadiran (via parameter `?tambah=1`
  yang di-baca JS di halaman Kehadiran Saya) — jadi satu klik, bukan
  dua. Tanggal juga otomatis terisi hari ini di form.

## Revisi Lanjutan: Validasi Rentang Gaji Pokok per Jabatan

**Pertanyaan:** Gaji pokok ditentukan oleh siapa? Apa admin boleh input
sesuka hati?

**Jawaban konsep:** Di dunia nyata, gaji pokok mengacu ke UMK/UMP
(batas bawah wajib secara hukum) dan Struktur & Skala Upah internal
perusahaan (Permenaker No. 1/2017) — bukan angka bebas.

**Implementasi:** Ditambah `Employee::rentangGajiPokok($jabatan)` — pola
konstanta yang SAMA seperti `tunjanganJabatanMultiplier()` yang sudah
ada, supaya konsisten dan gampang dijelaskan satu napas:
- Staff: Rp4.000.000 - Rp8.000.000
- Supervisor: Rp8.000.000 - Rp15.000.000
- Manager: Rp15.000.000 - Rp30.000.000

`EmployeeController::store()` dan `update()` sekarang validasi gaji
pokok terhadap rentang jabatan yang dipilih (custom validation rule).
Kalau di luar rentang, muncul pesan error spesifik. Form Tambah/Edit
Karyawan juga menampilkan rentang yang berlaku secara live (JavaScript)
begitu jabatan dipilih, supaya admin tahu batasnya SEBELUM submit,
bukan baru tahu setelah ditolak.

Ditambah `tests/Feature/EmployeeSalaryRangeTest.php` dan 1 unit test
tambahan di `PayrollCalculationServiceTest.php`.

## Revisi Lanjutan: Sederhanakan Jenjang Jabatan (Staff & Manager saja) + Perkecil Rentang Gaji

**Permintaan:** Rentang gaji sebelumnya (Staff 4-8jt, Supervisor 8-15jt,
Manager 15-30jt) dianggap terlalu besar. Jenjang Supervisor juga
dianggap tidak perlu — karyawan yang tadinya Supervisor cukup jadi
Staff.

**Perubahan:**
- Kolom `jabatan` di migration `employees` sekarang cuma `enum('staff', 'manager')`.
- `Employee::tunjanganJabatanMultiplier()`: Staff 10%, Manager 30% (multiplier Supervisor 20% dihapus).
- `Employee::rentangGajiPokok()`: Staff Rp4.000.000–Rp6.000.000, Manager Rp7.000.000–Rp10.000.000.
- Dropdown jabatan di form Tambah/Edit Karyawan: opsi Supervisor dihapus.
- `EmployeeSeeder`: Budi Santoso & Eko Prasetyo (tadinya Supervisor)
  diubah jadi Staff, gaji pokok disesuaikan ke rentang Staff yang baru.
- `EmployeeFactory` & tests yang menyebut "supervisor" diperbarui.

**PENTING — perlu migrasi ulang database:** Karena enum kolom `jabatan`
berubah (baris `supervisor` yang sudah ada di database Kakak sekarang
akan error kalau tetap dipertahankan), jalankan ulang:

```
php artisan migrate:fresh --seed
```

Ini akan **menghapus semua data** yang ada sekarang (termasuk karyawan
yang Kakak tambahkan manual lewat form, seperti "Sudi Satosyo") dan
menggantinya dengan data seeder yang baru (Staff & Manager saja).
Kalau ada data manual yang mau dipertahankan, catat dulu sebelum
menjalankan perintah di atas, lalu input ulang lewat form setelah
migrasi selesai.

## Revisi Lanjutan: Default Periode Payroll = Bulan Lalu

**Masalah:** Halaman Data Penggajian, Laporan, dan ringkasan di Beranda
admin defaultnya menampilkan bulan BERJALAN (mis. Juli kalau sekarang
tanggal 19 Juli) — padahal payroll bulan berjalan belum masuk akal
untuk digenerate, karena data kehadiran bulan itu belum lengkap (belum
sampai akhir bulan).

**Perbaikan:** Default periode di `PayrollController::index()`,
`laporanPerJabatan()`, dan `AuthController::dashboard()` (ringkasan
admin) diubah jadi `now()->subMonth()->format('Y-m')` — bulan lalu.
Admin tetap BISA pilih bulan lain (termasuk bulan berjalan) lewat
input periode kalau memang perlu; ini cuma mengubah nilai DEFAULT saat
halaman pertama dibuka.

(Catatan: revisi pencegahan kehadiran ganda di sisi form/JavaScript
sempat ditambahkan lalu dibatalkan atas permintaan — validasi server
`Rule::unique('attendances')` di `storeMine()`/`updateMine()` tetap
ada seperti semula dan tetap mencegah data dobel, cuma tanpa
peringatan tambahan di sisi form.)

## 10. Kehadiran dikunci total (append-only) — karyawan tidak bisa edit/hapus sama sekali

**Keluhan:** waktu didemo, ternyata karyawan masih bisa edit data
kehadiran yang sudah tersimpan lewat tombol edit di halaman "Kehadiran
Saya". Ini celah keamanan — karyawan bisa saja absen dengan data wajar
dulu, lalu belakangan mengubah jam lembur jadi lebih besar sebelum
payroll digenerate.

**Keputusan:** kehadiran dibuat **APPEND-ONLY** (write-once). Begitu
disimpan, tidak ada jalan mengubah/menghapusnya lagi — bukan cuma oleh
karyawan lain, tapi oleh SIAPAPUN, termasuk karyawan itu sendiri dan
admin (HRD). Ini konsisten dengan desain `AttendanceController::index()`
untuk admin yang dari awal sudah read-only.

**Konsekuensi yang disadari:** kalau ada salah input (misal salah
pilih status), saat ini **tidak ada mekanisme koreksi apapun** di
sistem. Ini trade-off yang diambil secara sadar demi mencegah
manipulasi data — kalau ke depan dibutuhkan jalur koreksi, itu harus
dirancang khusus (misal alur "pengajuan koreksi" yang perlu approval
terpisah), bukan sekadar mengaktifkan lagi tombol edit bebas.

**Perubahan teknis:**
- `AttendanceController::updateMine()` dan `destroyMine()` dihapus
  total dari kode.
- Route `PUT /kehadiran-saya/{attendance}` dan
  `DELETE /kehadiran-saya/{attendance}` dihapus dari `routes/web.php`
  — bukan cuma diblokir otorisasi, tapi endpoint-nya memang tidak ada.
- Tombol edit/hapus, modal edit, dan modal hapus dihapus dari
  `attendance-saya.blade.php`. Tabel kehadiran sekarang murni
  tampilan baca saja (tanpa kolom "Aksi").
- Alert info di halaman "Kehadiran Saya" diperbarui, memperingatkan
  karyawan untuk cek data dengan teliti sebelum submit karena tidak
  bisa diubah lagi.
- Test lama yang menguji otorisasi edit/hapus diganti dengan test yang
  membuktikan endpoint-nya memang tidak ada sama sekali (404), baik
  untuk karyawan maupun admin.

## 11. Password ditentukan admin sendiri (bukan random) + status pembayaran gaji

**Keluhan 1:** password random 8 karakter yang digenerate otomatis
susah diingat/disampaikan ke karyawan.

**Perbaikan:** `EmployeeController::store()` sekarang mewajibkan admin
mengisi field `password` + `password_confirmation` sendiri (minimal 6
karakter) waktu tambah karyawan baru — tidak ada lagi `Str::password()`
random. Modal konfirmasi setelah submit juga disederhanakan, cuma
nampilin email login (password tidak perlu ditampilkan ulang karena
admin sudah tau sendiri apa yang diketik).

Di `update()`, ditambahkan field password OPSIONAL — dikosongkan
berarti password lama tetap dipakai; diisi berarti admin me-reset
password karyawan tersebut (misal karyawan lupa password).

**Keluhan 2:** belum ada status "sudah dibayar / belum dibayar" untuk
tiap slip gaji.

**Perbaikan:** kolom `payrolls.status` (enum `belum_dibayar` |
`sudah_dibayar`, default `belum_dibayar`) ditambahkan. Perubahan:
- `PayrollController::updateStatus()` — endpoint baru, HANYA ada di
  grup middleware `['auth','admin']`, jadi karyawan tidak punya jalan
  sama sekali untuk mengubah status gajinya sendiri (bukan cuma
  diblokir otorisasi, endpoint-nya memang di luar jangkauan karyawan).
- Tombol toggle 1-klik di halaman "Data Penggajian" (badge hijau
  "Sudah Dibayar" / badge kuning "Belum Dibayar").
- Status ditampilkan (read-only) di slip gaji dan "Slip Gaji Saya"
  milik karyawan — supaya karyawan tau gajinya sudah cair atau belum,
  tanpa bisa mengubahnya.
- **PENTING:** `generate()` (yang memakai `updateOrCreate`) SENGAJA
  tidak menyertakan `status` di data yang di-update, supaya generate
  ulang payroll (misal ada koreksi kehadiran) TIDAK mereset status
  yang sudah ditandai "Sudah Dibayar" kembali ke "Belum Dibayar".
  Status hanya berubah lewat aksi eksplisit admin di
  `updateStatus()`.
- Ditambahkan `tests/Feature/PayrollRevisionTest.php` (11 test) yang
  membuktikan: password sesuai input admin (bukan random) dan bisa
  dipakai login, validasi password, reset password lewat edit, default
  status saat generate, admin bisa ubah status, karyawan 100% tidak
  bisa (403), dan status tidak ke-reset saat generate ulang.

## 12. Jabatan dibuat 3 jenjang (Staff, Supervisor, Manager) — sengaja beda jumlah dari role

**Permintaan:** role login tetap 2 (admin & karyawan), tapi jabatan
jangan sama jumlahnya kayak role — biar nggak ketuker konsepnya.
Dulu jabatan sempat disederhanakan jadi cuma 2 (Staff & Manager).

**Perbaikan:** jabatan dikembalikan jadi 3 jenjang: **Staff,
Supervisor, Manager**. Ini keputusan bagus untuk kejelasan konsep,
karena dengan jumlah yang beda (2 role vs 3 jabatan), jadi lebih susah
ketuker "role = hak akses ke sistem" vs "jabatan = atribut kepegawaian
buat hitung tunjangan" — dua hal yang secara sengaja TIDAK saling
mempengaruhi (lihat section 4 & 9 di atas soal ini).

**Perubahan teknis:**
- Migration `employees`: enum `jabatan` jadi `['staff','supervisor','manager']`.
- `Employee::tunjanganJabatanMultiplier()`: staff 10%, supervisor 20%, manager 30%.
- `Employee::rentangGajiPokok()`: staff Rp4-6jt, supervisor Rp6-8jt, manager Rp8-10jt.
- Validasi di `EmployeeController` (store & update) ikut diperbarui.
- Dropdown jabatan di form Tambah/Edit Karyawan nambah opsi Supervisor.
- `EmployeeSeeder`: Budi Santoso & Eko Prasetyo dikembalikan jadi
  Supervisor (gajinya disesuaikan ke rentang Rp6-8jt).
- Unit test baru: `tunjangan_supervisor_dihitung_dengan_multiplier_20_persen`,
  test rentang gaji 3 jenjang.
- Feature test baru: `jabatan_supervisor_punya_rentang_gaji_sendiri_di_antara_staff_dan_manager`,
  dan `jabatan_apapun_tetap_punya_hak_akses_yang_sama_sebagai_karyawan`
  (membuktikan karyawan jabatan Manager tetap kena blokir 403 dari
  halaman admin, sama persis kayak Staff — karena role-nya tetap
  'karyawan', bukan ikut naik jadi admin).
