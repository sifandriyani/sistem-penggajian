<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Jabatan;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * EmployeeController
 * Mengelola data induk karyawan (bukan fokus utama kompetensi, dibuat
 * ringkas hanya agar aplikasi bisa dipakai end-to-end saat presentasi).
 */
class EmployeeController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->input('search', ''));

        $employees = Employee::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('nama', 'like', "%{$search}%")
                        ->orWhere('nip', 'like', "%{$search}%");
                });
            })
            ->orderBy('id', 'desc')
            ->paginate(15);
        
        $jabatans = Jabatan::orderBy('id')->get();

        return view('payroll.employees', compact('employees', 'jabatans', 'search'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nip'                 => ['required', 'string', 'unique:employees,nip'],
            'nama'                => ['required', 'string', 'max:255'],
            'jabatan'             => ['required', 'string', 'exists:jabatans,kode'],
            'status_menikah'      => ['boolean'],
            'jumlah_anak'         => ['integer', 'min:0', 'max:10'],
            'password'            => ['required', 'string', 'min:6', 'confirmed'],
            // default payment fields (optional)
            'default_payment_method' => ['nullable', 'in:bank,cash,other'],
            'default_payment_channel' => ['nullable', 'string', 'max:255'],
            'default_payment_account' => ['nullable', 'string', 'max:255'],
        ]);

        $jabatan = Jabatan::where('kode', $validated['jabatan'])->first();

        $validated['status_menikah'] = $request->boolean('status_menikah');
        $validated['gaji_pokok'] = $jabatan ? (float) $jabatan->gaji_min : 0;
        $password = $validated['password'];
        unset($validated['password']);

        $employee = DB::transaction(function () use ($validated, $password, $request) {
            $employee = Employee::create($validated);

            User::create([
                'name'        => $employee->nama,
                'email'       => $this->generateLoginEmail($employee->nama),
                'password'    => Hash::make($password),
                'role'        => 'karyawan',
                'employee_id' => $employee->id,
            ]);

            // If default payment specified at creation, add audit
            if (!empty($validated['default_payment_method']) || !empty($validated['default_payment_channel']) || !empty($validated['default_payment_account'])) {
                \Illuminate\Support\Facades\DB::table('payment_audits')->insert([
                    'employee_id' => $employee->id,
                    'action' => 'ubah_default_payment',
                    'metadata' => json_encode([
                        'default_payment_method' => $employee->default_payment_method,
                        'default_payment_channel' => $employee->default_payment_channel,
                    ]),
                    'performed_by' => auth()->id() ?? null,
                    'performed_at' => now(),
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
            }

            return $employee;
        });

        return back()->with('status', "Karyawan {$employee->nama} berhasil ditambahkan.")
            ->with('kredensial_baru', [
                'nama'  => $employee->nama,
                'email' => $employee->user->email,
            ]);
    }


    /**
     * Buat email login unik dari nama karyawan.
     * "Budi Santoso" -> budi.santoso@payroll.test
     * Kalau sudah dipakai (nama kembar), ditambah angka urut:
     * budi.santoso2@payroll.test, budi.santoso3@payroll.test, dst.
     */
    private function generateLoginEmail(string $nama): string
    {
        $slug = Str::of($nama)->lower()->replace(' ', '.')->replaceMatches('/[^a-z0-9.]/', '');
        $base = $slug->toString();

        $email = "{$base}@payroll.test";
        $suffix = 2;

        while (User::where('email', $email)->exists()) {
            $email = "{$base}{$suffix}@payroll.test";
            $suffix++;
        }

        return $email;
    }

    /**
     * Perbarui data induk karyawan (nama, jabatan, gaji pokok, status
     * pernikahan, jumlah anak, dst). Ini TIDAK mengubah slip gaji yang
     * sudah pernah digenerate (payrolls) — payroll periode lama tetap
     * memakai angka gaji_pokok saat itu digenerate (snapshot), hanya
     * payroll yang di-generate ULANG setelah ini yang memakai nilai baru.
     *
     * Password bersifat OPSIONAL di sini — dikosongkan berarti password
     * lama tetap dipakai. Admin cuma isi kalau memang mau reset password
     * karyawan (misal karyawan lupa passwordnya).
     */
    public function update(Request $request, Employee $employee): RedirectResponse
    {
        $validated = $request->validate([
            'nip'                 => ['required', 'string', 'unique:employees,nip,' . $employee->id],
            'nama'                => ['required', 'string', 'max:255'],
            'jabatan'             => ['required', 'string', 'exists:jabatans,kode'],
            'jumlah_anak'         => ['integer', 'min:0', 'max:10'],
            'password'            => ['nullable', 'string', 'min:6', 'confirmed'],
            // default payment fields (optional)
            'default_payment_method' => ['nullable', 'in:bank,cash,other'],
            'default_payment_channel' => ['nullable', 'string', 'max:255'],
            'default_payment_account' => ['nullable', 'string', 'max:255'],
        ]);

        $jabatan = Jabatan::where('kode', $validated['jabatan'])->first();

        $validated['status_menikah'] = $request->boolean('status_menikah');
        $validated['gaji_pokok'] = $jabatan ? (float) $jabatan->gaji_min : 0;
        $password = $validated['password'] ?? null;
        unset($validated['password']);

        $employee->update($validated);

        // Nama di akun login ikut disinkronkan (email login TIDAK diubah,
        // supaya karyawan tidak perlu diberi tahu email baru tiap edit nama).
        $employee->user()->update([
            'name' => $employee->nama,
            ...($password ? ['password' => Hash::make($password)] : []),
        ]);

        // Jika default payment diubah, simpan audit
        if ($request->filled('default_payment_method') || $request->filled('default_payment_channel') || $request->filled('default_payment_account')) {
            \Illuminate\Support\Facades\DB::table('payment_audits')->insert([
                'employee_id' => $employee->id,
                'action' => 'ubah_default_payment',
                'metadata' => json_encode([
                    'default_payment_method' => $employee->default_payment_method,
                    'default_payment_channel' => $employee->default_payment_channel,
                ]),
                'performed_by' => auth()->id(),
                'performed_at' => now(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        }

        $pesan = "Data karyawan {$employee->nama} berhasil diperbarui.";
        if ($password) {
            $pesan .= ' Password login juga sudah direset.';
        }

        return back()->with('status', $pesan);
    }

    /**
     * Hapus data karyawan. Slip gaji (payrolls) miliknya ikut terhapus
     * otomatis karena foreign key employees->payrolls di-set
     * cascadeOnDelete() pada migration.
     *
     * Akun login (users) TIDAK otomatis ikut terhapus oleh database
     * (foreign key employees->users sengaja pakai nullOnDelete, bukan
     * cascade, supaya penghapusan karyawan tidak diam-diam menghapus
     * akun tanpa jejak) — jadi dihapus eksplisit di sini agar tidak ada
     * akun "nyantol" tanpa data karyawan yang masih bisa dipakai login.
     */
    public function destroy(Employee $employee): RedirectResponse
    {
        $nama = $employee->nama;

        DB::transaction(function () use ($employee) {
            $employee->user()->delete();
            $employee->delete();
        });

        return back()->with('status', "Karyawan {$nama} berhasil dihapus.");
    }

    public function storeJabatan(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'kode' => ['required', 'string', 'max:50', 'alpha_dash', 'unique:jabatans,kode'],
            'nama' => ['required', 'string', 'max:255'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
            'gaji_min' => ['required', 'numeric', 'min:0'],
            'multiplier' => ['required', 'numeric', 'min:0', 'max:1'],
            'transport_per_hari' => ['required', 'numeric', 'min:0'],
        ]);

        Jabatan::create($validated);

        return back()->with('status', 'Data jabatan berhasil ditambahkan.');
    }

    public function updateJabatan(Request $request, Jabatan $jabatan): RedirectResponse
    {
        $validated = $request->validate([
            'kode' => ['required', 'string', 'max:50', 'alpha_dash', 'unique:jabatans,kode,' . $jabatan->id],
            'nama' => ['required', 'string', 'max:255'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
            'gaji_min' => ['required', 'numeric', 'min:0'],
            'multiplier' => ['required', 'numeric', 'min:0', 'max:1'],
            'transport_per_hari' => ['required', 'numeric', 'min:0'],
        ]);

        $jabatan->update($validated);

        return back()->with('status', 'Data jabatan berhasil diperbarui.');
    }

    public function destroyJabatan(Jabatan $jabatan): RedirectResponse
    {
        $jabatan->delete();

        return back()->with('status', 'Data jabatan berhasil dihapus.');
    }

    /**
     * Catat audit ketika admin menampilkan default payment account milik karyawan
     * dari UI (modal pembayaran). Hanya admin yang boleh memanggilnya.
     */
    public function viewDefaultPaymentAccount(Request $request, Employee $employee)
    {
        $user = $request->user();
        abort_unless($user && $user->isAdmin(), 403);

        DB::table('payment_audits')->insert([
            'employee_id' => $employee->id,
            'action' => 'view_default_account',
            'metadata' => json_encode([ 'account' => $employee->default_payment_account ? '***'.substr($employee->default_payment_account, -4) : null ]),
            'performed_by' => $user->id,
            'performed_at' => now(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json(['ok' => true]);
    }

    /**
     * Tampilkan halaman Data Jabatan
     */
    public function indexJabatan(Request $request): View
    {
        $search = trim((string) $request->input('search', ''));

        $jabatans = Jabatan::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('kode', 'like', "%{$search}%")
                        ->orWhere('nama', 'like', "%{$search}%")
                        ->orWhere('keterangan', 'like', "%{$search}%");
                });
            })
            ->orderBy('id')
            ->get();
        
        return view('payroll.jabatan', compact('jabatans', 'search'));
    }
}

