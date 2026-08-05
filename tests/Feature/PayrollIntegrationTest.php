<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Integration test: menguji alur PENUH aplikasi mulai dari HTTP request,
 * Controller, Service, hingga data tersimpan benar di database dan bisa
 * ditampilkan kembali lewat halaman lain. Ini berbeda dari unit test
 * karena melibatkan lebih dari satu komponen sekaligus (routing,
 * validasi, transaksi DB, relasi Eloquent, view, DAN otorisasi role).
 *
 * Semua endpoint generate/laporan/index sekarang dilindungi middleware
 * 'auth' + 'admin', jadi setiap test di sini login sebagai admin
 * terlebih dahulu (lihat setUp()).
 */
class PayrollIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin', 'employee_id' => null]);
        $this->actingAs($this->admin);
    }

    /** @test */
    public function test_generate_payroll_membuat_data_payroll_untuk_semua_karyawan(): void
    {
        $employees = Employee::factory()->count(3)->create([
            'gaji_pokok' => 6_000_000,
        ]);

        foreach ($employees as $employee) {
            Attendance::factory()->create([
                'employee_id' => $employee->id,
                'tanggal'     => '2026-07-05',
                'status'      => 'hadir',
                'jam_lembur'  => 2,
            ]);
        }

        $response = $this->post(route('payroll.generate'), ['periode' => '2026-07']);

        $response->assertRedirect(route('payroll.index', ['periode' => '2026-07']));
        $response->assertSessionHas('status');

        $this->assertDatabaseCount('payrolls', 3);

        foreach ($employees as $employee) {
            $this->assertDatabaseHas('payrolls', [
                'employee_id' => $employee->id,
                'periode'     => '2026-07',
            ]);
        }
    }

    /** @test */
    public function test_generate_payroll_dua_kali_pada_periode_sama_tidak_membuat_duplikat(): void
    {
        $employee = Employee::factory()->create();

        $this->post(route('payroll.generate'), ['periode' => '2026-07']);
        $this->post(route('payroll.generate'), ['periode' => '2026-07']);

        $this->assertDatabaseCount('payrolls', 1);
    }

    /** @test */
    public function test_absensi_dengan_jam_lembur_menambah_gaji_bersih_dibanding_tanpa_lembur(): void
    {
        $commonAttributes = [
            'jabatan'               => 'staff',
            'gaji_pokok'            => 6_000_000,
            'status_menikah'        => false,
            'jumlah_anak'           => 0,
        ];

        $karyawanBiasa  = Employee::factory()->create($commonAttributes);
        $karyawanLembur = Employee::factory()->create($commonAttributes);

        Attendance::factory()->create([
            'employee_id' => $karyawanLembur->id,
            'tanggal'     => '2026-07-10',
            'status'      => 'hadir',
            'jam_lembur'  => 5,
        ]);

        $this->post(route('payroll.generate'), ['periode' => '2026-07']);

        $gajiBiasa  = Payroll::where('employee_id', $karyawanBiasa->id)->first();
        $gajiLembur = Payroll::where('employee_id', $karyawanLembur->id)->first();

        $this->assertGreaterThan($gajiBiasa->gaji_bersih, $gajiLembur->gaji_bersih);
    }

    /** @test */
    public function test_halaman_index_payroll_menampilkan_data_yang_baru_digenerate(): void
    {
        $employee = Employee::factory()->create(['nama' => 'Integration Test User']);
        $this->post(route('payroll.generate'), ['periode' => '2026-07']);

        $response = $this->get(route('payroll.index', ['periode' => '2026-07']));

        $response->assertOk();
        $response->assertSee('Integration Test User');
    }

    /** @test */
    public function test_laporan_per_jabatan_mengembalikan_agregat_yang_benar(): void
    {
        Employee::factory()->count(2)->create(['jabatan' => 'staff', 'gaji_pokok' => 6_000_000]);
        $this->post(route('payroll.generate'), ['periode' => '2026-07']);

        $response = $this->get(route('payroll.laporan', ['periode' => '2026-07']));

        $response->assertOk();
        $response->assertViewHas('rows', function ($rows) {
            return collect($rows)->firstWhere('jabatan', 'staff')?->jumlah_karyawan == 2;
        });
    }

    /** @test */
    public function test_periode_dengan_format_salah_ditolak_validasi(): void
    {
        $response = $this->post(route('payroll.generate'), ['periode' => 'bulan-depan']);

        $response->assertSessionHasErrors('periode');
        $this->assertDatabaseCount('payrolls', 0);
    }
}
