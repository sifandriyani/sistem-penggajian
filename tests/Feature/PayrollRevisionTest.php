<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Payroll;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Menguji 2 fitur revisi:
 *  1. Password akun karyawan baru DITENTUKAN ADMIN sendiri (bukan
 *     random) — dan karyawan bisa langsung login pakai password itu.
 *  2. Status pembayaran payroll (belum_dibayar/sudah_dibayar) HANYA
 *     bisa diubah oleh admin, karyawan sama sekali tidak punya akses.
 */
class PayrollRevisionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    // -----------------------------------------------------------------
    // Password ditentukan admin
    // -----------------------------------------------------------------

    /** @test */
    public function admin_menentukan_password_sendiri_saat_tambah_karyawan(): void
    {
        $response = $this->actingAs($this->admin)->post(route('employee.store'), [
            'nip'                   => 'EMP777',
            'nama'                  => 'Karyawan Baru',
            'jabatan'               => 'staff',
            'gaji_pokok'            => 6_000_000,
            'jumlah_anak'           => 0,
            'password'              => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ]);

        $response->assertSessionDoesntHaveErrors();

        $employee = Employee::where('nip', 'EMP777')->firstOrFail();
        $user = $employee->user;

        // Password TERSIMPAN TER-HASH (bukan plain text), tapi harus
        // cocok dengan yang diinput admin, BUKAN string random.
        $this->assertTrue(Hash::check('rahasia123', $user->password));

        // Karyawan itu langsung bisa login pakai password yang admin tentukan.
        $this->post(route('login.attempt'), [
            'email'    => $user->email,
            'password' => 'rahasia123',
        ])->assertRedirect(route('dashboard'));
    }

    /** @test */
    public function password_kurang_dari_6_karakter_ditolak(): void
    {
        $response = $this->actingAs($this->admin)->post(route('employee.store'), [
            'nip'                   => 'EMP776',
            'nama'                  => 'Karyawan Baru',
            'jabatan'               => 'staff',
            'gaji_pokok'            => 6_000_000,
            'jumlah_anak'           => 0,
            'password'              => '123',
            'password_confirmation' => '123',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertDatabaseMissing('employees', ['nip' => 'EMP776']);
    }

    /** @test */
    public function password_dan_konfirmasi_tidak_cocok_ditolak(): void
    {
        $response = $this->actingAs($this->admin)->post(route('employee.store'), [
            'nip'                   => 'EMP775',
            'nama'                  => 'Karyawan Baru',
            'jabatan'               => 'staff',
            'gaji_pokok'            => 6_000_000,
            'jumlah_anak'           => 0,
            'password'              => 'rahasia123',
            'password_confirmation' => 'beda123',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertDatabaseMissing('employees', ['nip' => 'EMP775']);
    }

    /** @test */
    public function edit_karyawan_tanpa_isi_password_tidak_mengubah_password_lama(): void
    {
        $employee = Employee::factory()->create();
        $user = User::factory()->create([
            'employee_id' => $employee->id,
            'password'    => Hash::make('passwordlama'),
        ]);
        $employee->refresh();

        $this->actingAs($this->admin)->put(route('employee.update', $employee), [
            'nip'                 => $employee->nip,
            'nama'                => $employee->nama,
            'jabatan'             => $employee->jabatan,
            'gaji_pokok'          => $employee->gaji_pokok,
            'jumlah_anak'         => $employee->jumlah_anak,
            // password sengaja dikosongkan
        ]);

        $this->assertTrue(Hash::check('passwordlama', $user->fresh()->password));
    }

    /** @test */
    public function admin_bisa_reset_password_karyawan_lewat_edit(): void
    {
        $employee = Employee::factory()->create();
        $user = User::factory()->create(['employee_id' => $employee->id]);

        $this->actingAs($this->admin)->put(route('employee.update', $employee), [
            'nip'                   => $employee->nip,
            'nama'                  => $employee->nama,
            'jabatan'               => $employee->jabatan,
            'gaji_pokok'            => $employee->gaji_pokok,
            'jumlah_anak'           => $employee->jumlah_anak,
            'password'              => 'passwordbaru',
            'password_confirmation' => 'passwordbaru',
        ]);

        $this->assertTrue(Hash::check('passwordbaru', $user->fresh()->password));
    }

    // -----------------------------------------------------------------
    // Status pembayaran payroll
    // -----------------------------------------------------------------

    /** @test */
    public function payroll_baru_digenerate_defaultnya_belum_dibayar(): void
    {
        $employee = Employee::factory()->create();

        $this->actingAs($this->admin)->post(route('payroll.generate'), [
            'periode' => '2026-07',
        ]);

        $this->assertDatabaseHas('payrolls', [
            'employee_id' => $employee->id,
            'status'      => 'belum_dibayar',
        ]);
    }

    /** @test */
    public function admin_bisa_menandai_payroll_sudah_dibayar(): void
    {
        $payroll = Payroll::factory()->create(['status' => 'belum_dibayar']);

        $response = $this->actingAs($this->admin)
            ->patch(route('payroll.update-status', $payroll), ['status' => 'sudah_dibayar']);

        $response->assertRedirect();
        $this->assertDatabaseHas('payrolls', ['id' => $payroll->id, 'status' => 'sudah_dibayar']);
    }

    /** @test */
    public function karyawan_tidak_bisa_mengubah_status_payroll_sama_sekali(): void
    {
        $employee = Employee::factory()->create();
        $karyawan = User::factory()->create(['role' => 'karyawan', 'employee_id' => $employee->id]);
        $payroll = Payroll::factory()->create(['employee_id' => $employee->id, 'status' => 'belum_dibayar']);

        // Termasuk mencoba menandai status GAJINYA SENDIRI sebagai lunas.
        $response = $this->actingAs($karyawan)
            ->patch(route('payroll.update-status', $payroll), ['status' => 'sudah_dibayar']);

        $response->assertForbidden();
        $this->assertDatabaseHas('payrolls', ['id' => $payroll->id, 'status' => 'belum_dibayar']);
    }

    /** @test */
    public function generate_ulang_payroll_tidak_mereset_status_yang_sudah_dibayar(): void
    {
        $employee = Employee::factory()->create();

        $this->actingAs($this->admin)->post(route('payroll.generate'), ['periode' => '2026-07']);

        $payroll = Payroll::where('employee_id', $employee->id)->where('periode', '2026-07')->firstOrFail();
        $payroll->update(['status' => 'sudah_dibayar']);

        // HRD generate ulang periode yang sama (misal ada koreksi kehadiran)
        $this->actingAs($this->admin)->post(route('payroll.generate'), ['periode' => '2026-07']);

        $this->assertDatabaseHas('payrolls', [
            'id'     => $payroll->id,
            'status' => 'sudah_dibayar', // TIDAK ikut ke-reset ke belum_dibayar
        ]);
    }

    /** @test */
    public function status_selain_2_pilihan_yang_valid_ditolak(): void
    {
        $payroll = Payroll::factory()->create();

        $response = $this->actingAs($this->admin)
            ->patch(route('payroll.update-status', $payroll), ['status' => 'lunas_sebagian']);

        $response->assertSessionHasErrors('status');
    }
}
