<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Payroll;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Menguji pemisahan hak akses:
 *  - Admin (HRD)  : boleh generate payroll, lihat semua data
 *  - Karyawan     : hanya boleh lihat slip gajinya sendiri
 *  - Tamu (belum login) : harus diarahkan ke halaman login
 */
class RoleAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function tamu_yang_belum_login_diarahkan_ke_halaman_login(): void
    {
        $response = $this->get(route('payroll.index'));

        $response->assertRedirect(route('login'));
    }

    /** @test */
    public function karyawan_tidak_boleh_mengakses_halaman_admin(): void
    {
        $karyawan = User::factory()->create(['role' => 'karyawan']);

        $response = $this->actingAs($karyawan)->get(route('payroll.index'));

        $response->assertForbidden(); // 403
    }

    /** @test */
    public function karyawan_tidak_boleh_generate_payroll(): void
    {
        $karyawan = User::factory()->create(['role' => 'karyawan']);

        $response = $this->actingAs($karyawan)
            ->post(route('payroll.generate'), ['periode' => '2026-07']);

        $response->assertForbidden();
        $this->assertDatabaseCount('payrolls', 0);
    }

    /** @test */
    public function admin_boleh_generate_payroll_dan_melihat_semua_data(): void
    {
        $admin = User::factory()->admin()->create();
        Employee::factory()->count(2)->create();

        $response = $this->actingAs($admin)
            ->post(route('payroll.generate'), ['periode' => '2026-07']);

        $response->assertRedirect();
        $this->assertDatabaseCount('payrolls', 2);
    }

    /** @test */
    public function karyawan_hanya_bisa_melihat_slip_gajinya_sendiri(): void
    {
        $employeeA = Employee::factory()->create();
        $employeeB = Employee::factory()->create();

        $userA = User::factory()->create(['role' => 'karyawan', 'employee_id' => $employeeA->id]);

        $slipA = Payroll::factory()->create(['employee_id' => $employeeA->id, 'periode' => '2026-07']);
        $slipB = Payroll::factory()->create(['employee_id' => $employeeB->id, 'periode' => '2026-07']);

        // Boleh lihat slip miliknya sendiri
        $this->actingAs($userA)
            ->get(route('payroll.slip', $slipA))
            ->assertOk();

        // TIDAK boleh lihat slip milik karyawan lain
        $this->actingAs($userA)
            ->get(route('payroll.slip', $slipB))
            ->assertForbidden();
    }

    /** @test */
    public function halaman_slip_saya_hanya_menampilkan_slip_milik_sendiri(): void
    {
        $employeeA = Employee::factory()->create(['nama' => 'Karyawan A']);
        $employeeB = Employee::factory()->create(['nama' => 'Karyawan B']);

        $userA = User::factory()->create(['role' => 'karyawan', 'employee_id' => $employeeA->id]);

        Payroll::factory()->create(['employee_id' => $employeeA->id, 'periode' => '2026-07']);
        Payroll::factory()->create(['employee_id' => $employeeB->id, 'periode' => '2026-07']);

        $response = $this->actingAs($userA)->get(route('karyawan.slip-saya'));

        $response->assertOk();
        $response->assertViewHas('payrolls', fn ($payrolls) => $payrolls->every(
            fn ($p) => $p->employee_id === $employeeA->id
        ));
    }

    /** @test */
    public function login_dengan_password_salah_gagal_dan_tidak_membuat_sesi(): void
    {
        $user = User::factory()->create(['email' => 'test@payroll.test']);

        $response = $this->post(route('login.attempt'), [
            'email'    => 'test@payroll.test',
            'password' => 'password-salah',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    /** @test */
    public function admin_hanya_bisa_melihat_kehadiran_tidak_bisa_menambah(): void
    {
        $admin = User::factory()->admin()->create();

        // Halaman pantauan tetap bisa diakses (read-only)
        $this->actingAs($admin)->get(route('attendance.index'))->assertOk();

        // Tapi tidak ada lagi endpoint untuk admin menambah/mengedit/
        // menghapus kehadiran — hanya karyawan sendiri yang boleh (lewat
        // route karyawan.kehadiran.*), jadi POST langsung ke /kehadiran
        // harus 404 (route tidak terdaftar untuk admin).
        $this->actingAs($admin)->post('/kehadiran', [
            'employee_id' => Employee::factory()->create()->id,
            'tanggal'     => '2026-07-01',
            'status'      => 'hadir',
            'jam_lembur'  => 0,
        ])->assertNotFound();
    }

    /** @test */
    public function halaman_beranda_menampilkan_isi_berbeda_untuk_admin_dan_karyawan(): void
    {
        $admin    = User::factory()->admin()->create();
        $karyawan = User::factory()->create(['role' => 'karyawan']);

        Employee::factory()->count(3)->create();

        // Admin melihat ringkasan seluruh karyawan & payroll (isAdmin = true)
        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('isAdmin', true)
            ->assertViewHas('totalKaryawan', 3);

        // Karyawan hanya melihat slip gajinya sendiri (isAdmin = false)
        $this->actingAs($karyawan)->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('isAdmin', false);
    }

    /** @test */
    public function jabatan_apapun_tetap_punya_hak_akses_yang_sama_sebagai_karyawan(): void
    {
        // "Jabatan" (staff/supervisor/manager) itu atribut kepegawaian
        // buat hitung tunjangan — BUKAN role login. Karyawan dengan
        // jabatan manager sekalipun TETAP role 'karyawan', jadi TETAP
        // kena blokir dari halaman admin, persis sama seperti staff.
        $manager = Employee::factory()->create(['jabatan' => 'manager']);
        $userManager = User::factory()->create(['role' => 'karyawan', 'employee_id' => $manager->id]);

        $staff = Employee::factory()->create(['jabatan' => 'staff']);
        $userStaff = User::factory()->create(['role' => 'karyawan', 'employee_id' => $staff->id]);

        $this->actingAs($userManager)->get(route('payroll.index'))->assertForbidden();
        $this->actingAs($userStaff)->get(route('payroll.index'))->assertForbidden();

        // Keduanya cuma bisa akses halaman self-service yang sama persis.
        $this->actingAs($userManager)->get(route('karyawan.slip-saya'))->assertOk();
        $this->actingAs($userStaff)->get(route('karyawan.slip-saya'))->assertOk();
    }
}
