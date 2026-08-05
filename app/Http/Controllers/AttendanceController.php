<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * AttendanceController
 * ====================
 * Mengelola data kehadiran harian karyawan (hadir/izin/sakit +
 * jam lembur). Data inilah yang dipakai PayrollCalculationService saat
 * generate payroll untuk menghitung uang_lembur — bukan diketik manual
 * tiap bulan. Tanpa data di sini, uang lembur pada payroll akan selalu
 * Rp 0.
 *
 * Ada 2 jalur akses, SENGAJA dipisah haknya:
 *   1. index()  -> khusus admin (HRD), HANYA MELIHAT (read-only) — untuk
 *      memantau kelengkapan data dan menjadi dasar generate payroll.
 *      Admin TIDAK bisa menambah/mengedit/menghapus di sini, supaya
 *      data kehadiran benar-benar mencerminkan input asli karyawan
 *      (tidak ada celah HRD "mengarang" kehadiran orang lain).
 *   2. mine/storeMine -> self-service, dipakai karyawan input kehadiran
 *      & lemburnya SENDIRI sehari-hari.
 *
 * PENTING: kehadiran bersifat APPEND-ONLY (write-once). Begitu
 * disimpan, TIDAK ADA cara mengubah atau menghapusnya lagi — oleh
 * karyawan itu sendiri MAUPUN oleh admin. Ini sengaja, supaya data
 * kehadiran berfungsi sebagai log/audit trail yang bisa dipercaya:
 * karyawan tidak bisa mengarang ulang jam lemburnya setelah tau
 * gajinya akan dihitung dari situ. Konsekuensinya: kalau ada salah
 * input, TIDAK ADA mekanisme koreksi di sistem ini saat ini — kalau ke
 * depan dibutuhkan, itu harus jadi fitur terpisah yang disengaja
 * (misal alur "pengajuan koreksi" yang butuh approval), bukan edit
 * bebas seperti sebelumnya.
 */
class AttendanceController extends Controller
{
    /**
     * Read-only untuk admin: rekap per karyawan (jumlah hadir/izin/sakit
     * & total jam lembur) + daftar harian lengkap, untuk periode terpilih.
     */
    public function index(Request $request): View
    {
        $periode = $request->query('periode', now()->format('Y-m'));

        $periodeParts = explode('-', $periode);
        $year = $periodeParts[0] ?? now()->format('Y');
        $month = $periodeParts[1] ?? now()->format('m');

        $attendances = Attendance::with('employee')
            ->whereYear('tanggal', $year)
            ->whereMonth('tanggal', $month)
            ->orderBy('tanggal')
            ->get();

        $rekap = $attendances
            ->groupBy('employee_id')
            ->map(function ($rows) {
                return [
                    'employee'    => $rows->first()->employee,
                    'hadir'       => $rows->where('status', 'hadir')->count(),
                    'izin'        => $rows->where('status', 'izin')->count(),
                    'sakit'       => $rows->where('status', 'sakit')->count(),
                    'jam_lembur'  => $rows->sum('jam_lembur'),
                ];
            })
            ->sortBy(fn ($r) => $r['employee']->nama)
            ->values();

        return view('payroll.attendance', compact('attendances', 'rekap', 'periode'));
    }

    // -----------------------------------------------------------------
    // SELF-SERVICE (role: karyawan) — karyawan input kehadiran & lembur
    // MILIKNYA SENDIRI. Ini yang dipakai sehari-hari; menu "Data
    // Kehadiran" di atas tetap ada untuk HRD sebagai kontrol/koreksi,
    // bukan input rutin harian.
    // -----------------------------------------------------------------

    /**
     * Daftar kehadiran milik karyawan yang sedang login, sekaligus form
     * tambah cepat untuk hari ini/hari lain.
     */
    public function mine(Request $request): View
    {
        $user = $request->user();
        abort_if($user->employee_id === null, 403, 'Akun ini tidak terhubung ke data karyawan.');

        $periode = $request->query('periode', now()->format('Y-m'));

        $dateParts = explode('-', $periode);
        $year = $dateParts[0] ?? now()->format('Y');
        $month = $dateParts[1] ?? now()->format('m');

        $attendances = Attendance::where('employee_id', $user->employee_id)
            ->whereYear('tanggal', $year)
            ->whereMonth('tanggal', $month)
            ->orderByDesc('tanggal')
            ->get();

        return view('payroll.attendance-saya', compact('attendances', 'periode'));
    }

    /**
     * Karyawan menambah kehadirannya sendiri. employee_id TIDAK diambil
     * dari input form (supaya karyawan tidak bisa input atas nama orang
     * lain) — selalu dipaksa dari employee_id akun yang sedang login.
     */
    public function storeMine(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_if($user->employee_id === null, 403, 'Akun ini tidak terhubung ke data karyawan.');

        $validated = $request->validate([
            'tanggal'    => [
                'required',
                'date',
                'date_equals:today',
                function ($attribute, $value, $fail) use ($user) {
                    if (Attendance::where('employee_id', $user->employee_id)
                        ->whereDate('tanggal', $value)
                        ->exists()) {
                        $fail('Kamu sudah punya catatan kehadiran pada tanggal tersebut.');
                    }
                },
            ],
            'status'     => ['required', 'in:hadir,izin,sakit'],
            'jam_lembur' => ['required', 'numeric', 'min:0', 'max:24'],
            'keterangan' => ['nullable', 'string', 'max:255', Rule::requiredIf($request->input('status') === 'izin')],
            'bukti'      => [Rule::requiredIf($request->input('status') === 'sakit'), 'file', 'max:5120', 'mimes:jpg,jpeg,png,pdf,doc,docx'],
        ], [
            'tanggal.date_equals' => 'Kehadiran hanya boleh diisi untuk hari ini.',
            'keterangan.required' => 'Keterangan wajib diisi untuk izin.',
            'bukti.required'      => 'Bukti wajib diunggah saat memilih sakit.',
            'bukti.file'          => 'Bukti harus berupa file yang valid.',
            'bukti.max'           => 'Ukuran file bukti maksimal 5 MB.',
            'bukti.mimes'         => 'Bukti harus berupa JPG, PNG, PDF, DOC, atau DOCX.',
        ]);

        if ($request->hasFile('bukti')) {
            $validated['bukti'] = $request->file('bukti')->store('attendance-bukti', 'public');
        }

        Attendance::create([...$validated, 'employee_id' => $user->employee_id]);

        return back()->with('status', 'Data kehadiran berhasil ditambahkan.');
    }
}
