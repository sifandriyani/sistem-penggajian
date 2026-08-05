<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Payroll;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * AuthController
 * ==============
 * Autentikasi sederhana berbasis session (bukan Breeze/Jetstream) —
 * cukup untuk mendemonstrasikan pemisahan akses admin vs karyawan.
 */
class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()
                ->withErrors(['email' => 'Email atau password salah.'])
                ->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /**
     * Halaman Beranda: titik masuk setelah login. Isinya BERBEDA sesuai
     * role, bukan halaman generik yang sama untuk semua orang:
     *   - admin    : ringkasan jumlah karyawan, total payroll bulan
     *                berjalan, grafik jumlah karyawan per jabatan, dan
     *                5 slip gaji terbaru yang digenerate.
     *   - karyawan : 5 slip gaji terakhir miliknya sendiri saja.
     */
    public function dashboard(Request $request): View
    {
        $user = $request->user();
        $isAdmin = $user->isAdmin();

        if ($isAdmin) {
            // Bulan lalu, karena payroll bulan berjalan belum digenerate
            // (data kehadiran bulan ini belum lengkap sampai akhir bulan).
            $periode = now()->subMonth()->format('Y-m');

            $totalKaryawan  = Employee::count();
            $totalPayroll   = Payroll::where('periode', $periode)->sum('gaji_bersih');
            $payrollTerbaru = Payroll::with('employee')->latest('id')->take(5)->get();

            // Akses basis data via query builder untuk data grafik (jumlah
            // karyawan per jabatan), ditampilkan di Beranda sebagai chart.
            $perJabatan = DB::table('employees')
                ->join('jabatans', 'employees.jabatan_id', '=', 'jabatans.id')
                ->select('jabatans.kode as jabatan', DB::raw('COUNT(*) as jumlah'))
                ->groupBy('jabatans.kode')
                ->orderBy('jabatans.kode')
                ->get();

            return view('dashboard', compact(
                'isAdmin', 'periode', 'totalKaryawan', 'totalPayroll', 'payrollTerbaru', 'perJabatan'
            ));
        }

        // Karyawan biasa: tidak semua karyawan punya akun bertaut ke data
        // induk (mis. akun baru yang belum ditautkan HRD).
        $payrolls = $user->employee_id
            ? Payroll::where('employee_id', $user->employee_id)->orderByDesc('periode')->take(5)->get()
            : collect();

        $notifications = session('notifications.' . $user->id, []);
        if ($notifications) {
            session()->forget('notifications.' . $user->id);
        }

        // Dipakai untuk kartu ajakan "isi kehadiran hari ini" di Beranda —
        // kartu itu HANYA muncul kalau memang belum diisi, supaya tidak
        // membingungkan (lihat resources/views/dashboard.blade.php).
        $sudahAbsenHariIni = $user->employee_id
            ? Attendance::where('employee_id', $user->employee_id)->whereDate('tanggal', now())->exists()
            : true;

        return view('dashboard', compact('isAdmin', 'payrolls', 'sudahAbsenHariIni', 'notifications'));
    }
}
