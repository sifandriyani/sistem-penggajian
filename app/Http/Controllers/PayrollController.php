<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\DeductionRate;
use App\Models\Employee;
use App\Models\Payroll;
use App\Services\PayrollCalculationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\View\View;

/**
 * PayrollController
 * ==================
 * Menghubungkan HTTP request dengan PayrollCalculationService dan
 * database. Controller ini SENGAJA tidak berisi logika perhitungan
 * (lihat PayrollCalculationService) — hanya orkestrasi:
 *   1. Ambil data (akses basis data via Eloquent)
 *   2. Panggil service untuk menghitung
 *   3. Simpan hasil
 *   4. Tampilkan laporan (raw SQL untuk agregasi/laporan)
 */
class PayrollController extends Controller
{
    public function __construct(
        private readonly PayrollCalculationService $payrollService
    ) {
    }

    /**
     * Tampilkan daftar payroll yang sudah dibuat untuk sebuah periode.
     *
     * Default periode = BULAN LALU, bukan bulan berjalan. Payroll untuk
     * bulan yang sedang berjalan belum masuk akal untuk digenerate,
     * karena data kehadiran bulan itu belum lengkap (belum sampai akhir
     * bulan) — gajian biasanya diproses setelah satu bulan penuh selesai.
     */
    public function index(Request $request): View
    {
        $periode = $request->query('periode', now()->subMonth()->format('Y-m'));
        $statusFilter = $request->query('status', 'all');
        $paymentMethodFilter = $request->query('payment_method', 'all');

        $query = Payroll::with('employee')
            ->where('periode', $periode);

        if ($statusFilter === 'dibayar') {
            $query->where('status', 'sudah_dibayar');
        } elseif ($statusFilter === 'belum') {
            $query->where('status', 'belum_dibayar');
        }

        if ($paymentMethodFilter === 'bank') {
            $query->where('payment_method', 'bank');
        } elseif ($paymentMethodFilter === 'cash') {
            $query->where('payment_method', 'cash');
        } elseif ($paymentMethodFilter === 'other') {
            $query->where('payment_method', 'other');
        }

        $payrolls = $query->orderBy('id')->get();

        $deductionRates = DeductionRate::whereIn('kode', [
            'bpjs_kesehatan',
            'bpjs_jht',
            'bpjs_jp',
            'pph21',
            'alpha',
        ])->get()->keyBy('kode');

        return view('payroll.index', compact('payrolls', 'periode', 'deductionRates', 'statusFilter', 'paymentMethodFilter'));
    }

    /**
     * Tandai beberapa payroll sekaligus sebagai sudah_dibayar (bulk).
     * Menggunakan default payment dari masing-masing employee bila tersedia;
     * bila tidak tersedia, daftar yang gagal dikembalikan dalam respon.
     */
    public function bulkMarkPaid(Request $request)
    {
        $validated = $request->validate([
            'payroll_ids' => ['required', 'array'],
            'payroll_ids.*' => ['integer'],
            // optional fallback info untuk payrolls yang tidak punya default
            'fallback_payment_method' => ['nullable', 'in:bank,cash,other'],
            'fallback_payment_channel' => ['nullable', 'string', 'max:255'],
            'fallback_payment_account' => ['nullable', 'string', 'max:255'],
            'fallback_payment_note' => ['nullable', 'string', 'max:255'],
        ]);

        $results = [];
        foreach ($validated['payroll_ids'] as $pid) {
            $payroll = Payroll::with('employee')->find($pid);
            if (! $payroll) {
                $results[$pid] = ['status' => 'not_found'];
                continue;
            }

            $employee = $payroll->employee;
            // prefer employee defaults
            $method = $employee->default_payment_method ?? $validated['fallback_payment_method'] ?? null;
            $channel = $employee->default_payment_channel ?? $validated['fallback_payment_channel'] ?? null;
            $account = $employee->default_payment_account ?? $validated['fallback_payment_account'] ?? null;
            $note = $validated['fallback_payment_note'] ?? null;

            if (! $method) {
                $results[$pid] = ['status' => 'no_payment_info'];
                continue;
            }

            // perform update
            $payroll->status = 'sudah_dibayar';
            $payroll->payment_method = $method;
            $payroll->payment_channel = $channel;
            $payroll->payment_account = $account;
            $payroll->payment_note = $note;
            $payroll->paid_at = now();
            $payroll->paid_by = $request->user()->id;
            $payroll->save();

            $this->notifyPayrollPaid($payroll, $request->user());

            // insert audit
            \Illuminate\Support\Facades\DB::table('payment_audits')->insert([
                'payroll_id' => $payroll->id,
                'employee_id' => $employee->id,
                'action' => 'bulk_tandai_dibayar',
                'metadata' => json_encode(['method'=>$method,'channel'=>$channel,'account'=> $account ? '***'.substr($account,-4) : null,'note'=>$note]),
                'performed_by' => $request->user()->id,
                'performed_at' => now(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            $results[$pid] = ['status' => 'ok'];
        }

        return back()->with('bulk_results', $results);
    }

    /**
     * Tandai semua payroll belum dibayar pada periode tertentu sebagai lunas,
     * memakai default payment yang sudah ada di data karyawan.
     */
    public function markAllUnpaid(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'periode' => ['required', 'date_format:Y-m'],
            'fallback_payment_method' => ['nullable', 'in:bank,cash,other'],
            'fallback_payment_channel' => ['nullable', 'string', 'max:255'],
            'fallback_payment_account' => ['nullable', 'string', 'max:255'],
            'fallback_payment_note' => ['nullable', 'string', 'max:255'],
        ]);

        $payrolls = Payroll::with('employee')
            ->where('periode', $validated['periode'])
            ->where('status', 'belum_dibayar')
            ->get();

        $results = [
            'updated' => 0,
            'skipped' => 0,
        ];

        foreach ($payrolls as $payroll) {
            $employee = $payroll->employee;
            $method = $employee->default_payment_method ?? $validated['fallback_payment_method'] ?? null;
            $channel = $employee->default_payment_channel ?? $validated['fallback_payment_channel'] ?? null;
            $account = $employee->default_payment_account ?? $validated['fallback_payment_account'] ?? null;
            $note = $validated['fallback_payment_note'] ?? null;

            if (! $method) {
                $results['skipped']++;
                continue;
            }

            $payroll->status = 'sudah_dibayar';
            $payroll->payment_method = $method;
            $payroll->payment_channel = $channel;
            $payroll->payment_account = $account;
            $payroll->payment_note = $note;
            $payroll->paid_at = now();
            $payroll->paid_by = $request->user()->id;
            $payroll->save();

            $this->notifyPayrollPaid($payroll, $request->user());

            DB::table('payment_audits')->insert([
                'payroll_id' => $payroll->id,
                'employee_id' => $employee->id,
                'action' => 'bulk_tandai_dibayar',
                'metadata' => json_encode(['method' => $method, 'channel' => $channel, 'account' => $account ? '***'.substr($account, -4) : null, 'note' => $note]),
                'performed_by' => $request->user()->id,
                'performed_at' => now(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            $results['updated']++;
        }

        return back()->with('status', "Ditandai {$results['updated']} payroll lunas untuk periode {$validated['periode']}.")
            ->with('bulk_results', ['mark_all_unpaid' => $results]);
    }

    /**
     * Balik semua payroll yang dibayar pada periode tertentu menjadi belum dibayar.
     */
    public function resetAllPaid(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'periode' => ['required', 'date_format:Y-m'],
        ]);

        $payrolls = Payroll::with('employee')
            ->where('periode', $validated['periode'])
            ->where('status', 'sudah_dibayar')
            ->get();

        $updated = 0;
        foreach ($payrolls as $payroll) {
            $payroll->status = 'belum_dibayar';
            $payroll->payment_method = null;
            $payroll->payment_channel = null;
            $payroll->payment_account = null;
            $payroll->payment_note = null;
            $payroll->paid_at = null;
            $payroll->paid_by = null;
            $payroll->save();

            $updated++;
        }

        return back()->with('status', "Dikembalikan {$updated} payroll menjadi belum dibayar untuk periode {$validated['periode']}.");
    }

    private function notifyPayrollPaid(Payroll $payroll, $adminUser): void
    {
        if (! $payroll->employee || ! $payroll->employee->user) {
            return;
        }

        $message = 'Gaji Anda untuk periode ' . $payroll->periode . ' telah ditandai lunas.';
        $payload = [
            'type' => 'payroll_paid',
            'payroll_id' => $payroll->id,
            'periode' => $payroll->periode,
            'message' => $message,
            'admin_name' => $adminUser->name ?? $adminUser->email,
        ];

        Session::push('notifications.' . $payroll->employee->user->id, $payload);
    }

    /**
     * Proses generate payroll untuk SELURUH karyawan pada periode tertentu.
     * Ini adalah endpoint yang diuji di tests/Feature/PayrollIntegrationTest.php
     * (integrasi: HTTP -> Controller -> Service -> Database).
     */
    public function generate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'periode' => ['required', 'date_format:Y-m'],
        ]);

        $periode = $validated['periode'];

        $employees = Employee::all();
        [$year, $month] = explode('-', $periode) + [now()->format('Y'), now()->format('m')];

        DB::transaction(function () use ($employees, $year, $month, $periode) {
            foreach ($employees as $employee) {
                $attendances = Attendance::where('employee_id', $employee->id)
                    ->whereYear('tanggal', $year)
                    ->whereMonth('tanggal', $month)
                    ->get();

                $hasil = $this->payrollService->calculate($employee, $attendances);

                Payroll::updateOrCreate(
                    ['employee_id' => $employee->id, 'periode' => $periode],
                    $hasil
                );
            }
        });

        return redirect()
            ->route('payroll.index', ['periode' => $periode])
            ->with('status', "Payroll periode {$periode} berhasil digenerate untuk {$employees->count()} karyawan.");
    }

    public function updateDeductionRates(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'bpjs_kesehatan' => ['required', 'numeric', 'min:0'],
            'bpjs_jht'       => ['required', 'numeric', 'min:0'],
            'bpjs_jp'        => ['required', 'numeric', 'min:0'],
            'pph21'          => ['required', 'numeric', 'min:0'],
            'alpha'          => ['required', 'numeric', 'min:0'],
        ]);

        $rates = [
            'bpjs_kesehatan' => ['jenis' => 'percentage', 'nilai' => $validated['bpjs_kesehatan'] / 100],
            'bpjs_jht'       => ['jenis' => 'percentage', 'nilai' => $validated['bpjs_jht'] / 100],
            'bpjs_jp'        => ['jenis' => 'percentage', 'nilai' => $validated['bpjs_jp'] / 100],
            'pph21'          => ['jenis' => 'fixed', 'nilai' => $validated['pph21']],
            'alpha'          => ['jenis' => 'fixed', 'nilai' => $validated['alpha']],
        ];

        foreach ($rates as $kode => $data) {
            DeductionRate::updateOrCreate(
                ['kode' => $kode],
                array_merge($data, [
                    'nama'       => $this->deductionName($kode),
                    'keterangan' => "Tarif {$kode} diperbarui oleh admin.",
                ])
            );
        }

        return back()->with('status', 'Tarif potongan payroll berhasil disimpan.');
    }

    private function deductionName(string $kode): string
    {
        return match ($kode) {
            'bpjs_kesehatan' => 'BPJS Kesehatan',
            'bpjs_jht'       => 'BPJS Ketenagakerjaan (JHT)',
            'bpjs_jp'        => 'BPJS Ketenagakerjaan (JP)',
            'pph21'          => 'PPh21',
            'alpha'          => 'Potongan Alpha',
            default          => ucfirst(str_replace('_', ' ', $kode)),
        };
    }

    /**
     * Perbarui komponen slip gaji SATU karyawan secara manual (misal
     * ada koreksi tunjangan, lembur susulan, atau potongan BPJS yang
     * berbeda dari perhitungan otomatis).
     *
     * gaji_pokok TIDAK ikut diedit di sini — ia hanya snapshot dari
     * Employee::gaji_pokok pada saat payroll digenerate (lihat
     * generate()). Kalau gaji pokok karyawan berubah, itu diubah lewat
     * Data Karyawan dan baru berlaku saat payroll digenerate ULANG.
     *
     * gaji_bersih SELALU dihitung ulang di server (bukan dipercaya dari
     * input user) = gaji_pokok + total_tunjangan + uang_lembur
     *               - total_potongan
     * supaya nilainya konsisten dan tidak bisa dimanipulasi dari luar.
     */
    public function update(Request $request, Payroll $payroll): RedirectResponse
    {
        $validated = $request->validate([
            'total_tunjangan'         => ['required', 'numeric', 'min:0'],
            'uang_lembur'             => ['required', 'numeric', 'min:0'],
            'potongan_bpjs_kesehatan' => ['required', 'numeric', 'min:0'],
            'potongan_bpjs_jht'       => ['required', 'numeric', 'min:0'],
            'potongan_bpjs_jp'        => ['required', 'numeric', 'min:0'],
            'potongan_pph21'          => ['required', 'numeric', 'min:0'],
            'potongan_alpha'          => ['required', 'numeric', 'min:0'],
            'potongan_lain'           => ['required', 'numeric', 'min:0'],
        ]);

        $totalPotongan = $validated['potongan_bpjs_kesehatan']
            + $validated['potongan_bpjs_jht']
            + $validated['potongan_bpjs_jp']
            + $validated['potongan_pph21']
            + $validated['potongan_alpha']
            + $validated['potongan_lain'];

        $gajiBersih = (float) $payroll->gaji_pokok
            + $validated['total_tunjangan']
            + $validated['uang_lembur']
            - $totalPotongan;

        $payroll->update([
            ...$validated,
            'potongan_bpjs'  => round($validated['potongan_bpjs_kesehatan'] + $validated['potongan_bpjs_jht'] + $validated['potongan_bpjs_jp'], 2),
            'total_potongan' => round($totalPotongan, 2),
            'gaji_bersih'    => round(max($gajiBersih, 0), 2),
        ]);

        return back()->with(
            'status',
            "Slip gaji {$payroll->employee->nama} periode {$payroll->periode} berhasil diperbarui."
        );
    }

    /**
     * Hapus satu baris payroll (misal salah generate periode / karyawan).
     */
    public function destroy(Payroll $payroll): RedirectResponse
    {
        $periode = $payroll->periode;
        $nama = $payroll->employee->nama;
        $payroll->delete();

        return back()->with('status', "Slip gaji {$nama} periode {$periode} berhasil dihapus.")
            ->withInput(['periode' => $periode]);
    }

    /**
     * Ubah status pembayaran satu slip gaji (belum_dibayar <-> sudah_dibayar).
     *
     * SENGAJA dipisah dari update() dan endpoint-nya sendiri, bukan
     * digabung jadi satu field di form edit komponen gaji — supaya bisa
     * dikasih middleware/otorisasi terpisah kalau nanti dibutuhkan, dan
     * supaya "menandai lunas" adalah aksi eksplisit (1 klik jelas),
     * bukan kesenggol tidak sengaja waktu ngedit komponen lain.
     *
     * Route ini ada di grup middleware ['auth','admin'] (lihat
     * routes/web.php) — jadi karyawan TIDAK PERNAH bisa memanggil
     * endpoint ini sama sekali, apalagi mengubah status gajinya sendiri.
     */
    public function updateStatus(Request $request, Payroll $payroll): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:belum_dibayar,sudah_dibayar'],
            'payment_method' => ['nullable', 'in:bank,cash,other'],
            'payment_channel' => ['nullable', 'string', 'max:255'],
            'payment_account' => ['nullable', 'string', 'max:255'],
            'payment_note' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validated['status'] === 'sudah_dibayar') {
            // Simpan metadata pembayaran dan timestamp/admin
            $payroll->update(array_merge($validated, [
                'paid_at' => now(),
                'paid_by' => $request->user()->id,
            ]));
        } else {
            // Jika dibalik ke belum dibayar, hapus metadata pembayaran (opsional)
            $payroll->update([
                'status' => 'belum_dibayar',
                'payment_method' => null,
                'payment_channel' => null,
                'payment_account' => null,
                'payment_note' => null,
                'paid_at' => null,
                'paid_by' => null,
            ]);
        }

        $label = $validated['status'] === 'sudah_dibayar' ? 'Sudah Dibayar' : 'Belum Dibayar';

        return back()->with(
            'status',
            "Status gaji {$payroll->employee->nama} periode {$payroll->periode} diubah jadi \"{$label}\"."
        );
    }

    /**
     * Mencatat akses (audit) ketika admin menampilkan nomor rekening penuh
     * pada slip gaji. Endpoint ini hanya untuk keperluan pencatatan;
     * nilai rekening penuh sudah ada di view untuk admin (decrypted via
     * Eloquent cast), tapi kita ingin menyimpan jejak akses.
     */
    public function viewPaymentAccount(Request $request, Payroll $payroll)
    {
        $user = $request->user();
        abort_unless($user && $user->isAdmin(), 403);

        // Simpan audit — hanya metadata tertulis, tidak menyimpan
        // nomor rekening plaintext di audit (simpan yang dimask saja).
        DB::table('payment_audits')->insert([
            'payroll_id'   => $payroll->id,
            'employee_id'  => $payroll->employee_id,
            'action'       => 'view_account',
            'metadata'     => json_encode([ 'account' => $payroll->payment_account ? '***'.substr($payroll->payment_account, -4) : null ]),
            'performed_by' => $user->id,
            'performed_at' => now(),
            'ip_address'   => $request->ip(),
            'user_agent'   => $request->userAgent(),
        ]);

        return response()->json(['ok' => true]);
    }

    /**
     * Laporan ringkas menggunakan RAW SQL (agregasi per jabatan).
     * Ditulis dengan query builder DB::select agar sesuai poin
     * kompetensi "Menggunakan SQL" secara eksplisit.
     */
    public function laporanPerJabatan(Request $request): View
    {
        $periode = $request->query('periode', now()->subMonth()->format('Y-m'));

        $rows = DB::select(<<<'SQL'
            SELECT
                j.kode                 AS jabatan,
                COUNT(*)               AS jumlah_karyawan,
                SUM(p.gaji_bersih)     AS total_gaji_bersih,
                AVG(p.gaji_bersih)     AS rata_rata_gaji_bersih,
                SUM(p.uang_lembur)     AS total_uang_lembur
            FROM payrolls p
            INNER JOIN employees e ON e.id = p.employee_id
            INNER JOIN jabatans j ON j.id = e.jabatan_id
            WHERE p.periode = ?
            GROUP BY j.kode
            ORDER BY total_gaji_bersih DESC
        SQL, [$periode]);

        // Detail lengkap per karyawan (bukan cuma ringkasan per jabatan),
        // supaya laporan bisa jadi bukti rinci ke pihak keuangan/audit,
        // sekaligus dasar cetak/rekap slip massal.
        $detail = DB::select(<<<'SQL'
            SELECT
                e.nip,
                e.nama,
                j.kode              AS jabatan,
                p.gaji_pokok,
                p.total_tunjangan,
                p.uang_lembur,
                p.potongan_bpjs_kesehatan,
                p.potongan_bpjs_jht,
                p.potongan_bpjs_jp,
                p.potongan_pph21,
                p.potongan_alpha,
                p.potongan_lain,
                p.total_potongan,
                p.gaji_bersih,
                p.status
            FROM payrolls p
            INNER JOIN employees e ON e.id = p.employee_id
            INNER JOIN jabatans j ON j.id = e.jabatan_id
            WHERE p.periode = ?
            ORDER BY j.kode, e.nama
        SQL, [$periode]);

        $grandTotal = [
            'gaji_pokok'      => array_sum(array_column($detail, 'gaji_pokok')),
            'total_tunjangan' => array_sum(array_column($detail, 'total_tunjangan')),
            'uang_lembur'     => array_sum(array_column($detail, 'uang_lembur')),
            'potongan_bpjs_kesehatan' => array_sum(array_column($detail, 'potongan_bpjs_kesehatan')),
            'potongan_bpjs_jht'       => array_sum(array_column($detail, 'potongan_bpjs_jht')),
            'potongan_bpjs_jp'        => array_sum(array_column($detail, 'potongan_bpjs_jp')),
            'potongan_pph21'          => array_sum(array_column($detail, 'potongan_pph21')),
            'potongan_alpha'          => array_sum(array_column($detail, 'potongan_alpha')),
            'potongan_lain'           => array_sum(array_column($detail, 'potongan_lain')),
            'total_potongan'          => array_sum(array_column($detail, 'total_potongan')),
            'gaji_bersih'             => array_sum(array_column($detail, 'gaji_bersih')),
        ];

        return view('payroll.laporan', [
            'rows'       => $rows,
            'detail'     => $detail,
            'grandTotal' => $grandTotal,
            'periode'    => $periode,
        ]);
    }

    /**
     * Slip gaji satu karyawan.
     *
     * Otorisasi: admin boleh lihat slip siapa saja; karyawan HANYA
     * boleh lihat slip miliknya sendiri (dicocokkan lewat employee_id
     * pada akun user yang sedang login).
     */
    public function slip(Request $request, Payroll $payroll): View
    {
        $user = $request->user();

        abort_unless(
            $user->isAdmin() || $payroll->employee_id === $user->employee_id,
            403,
            'Anda tidak berhak melihat slip gaji karyawan lain.'
        );

        $payroll->load('employee');

        return view('payroll.slip', compact('payroll'));
    }

    /**
     * Daftar slip gaji milik karyawan yang sedang login (self-service).
     * Ini yang dilihat oleh role 'karyawan' setelah login — mereka
     * TIDAK bisa mengakses payroll.index (daftar semua karyawan).
     */
    public function slipSaya(Request $request): View
    {
        $user = $request->user();

        abort_if($user->employee_id === null, 403, 'Akun ini tidak terhubung ke data karyawan.');

        $payrolls = Payroll::where('employee_id', $user->employee_id)
            ->orderByDesc('periode')
            ->get();

        return view('payroll.slip-saya', compact('payrolls'));
    }
}
