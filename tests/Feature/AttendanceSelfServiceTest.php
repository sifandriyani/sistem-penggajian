<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Menguji fitur self-service kehadiran: karyawan input kehadiran &
 * lembur MILIKNYA SENDIRI, tidak bisa menyentuh data karyawan lain,
 * dan tidak bisa input tanggal yang belum terjadi.
 */
class AttendanceSelfServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_karyawan_bisa_menambah_kehadiran_miliknya_sendiri(): void
    {
        $employee = Employee::factory()->create();
        $user = User::factory()->create(['role' => 'karyawan', 'employee_id' => $employee->id]);

        $response = $this->actingAs($user)->post(route('karyawan.kehadiran.store'), [
            'tanggal'    => now()->format('Y-m-d'),
            'status'     => 'hadir',
            'jam_lembur' => 2,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('attendances', [
            'employee_id' => $employee->id,
            'status'      => 'hadir',
            'jam_lembur'  => 2,
        ]);
    }

    public function test_karyawan_tidak_bisa_input_atas_nama_karyawan_lain(): void
    {
        $employeeSaya = Employee::factory()->create();
        $employeeLain = Employee::factory()->create();
        $user = User::factory()->create(['role' => 'karyawan', 'employee_id' => $employeeSaya->id]);

        // Meskipun ada percobaan mengirim employee_id lain lewat request,
        // controller HARUS mengabaikannya dan selalu pakai employee_id
        // milik akun yang sedang login.
        $this->actingAs($user)->post(route('karyawan.kehadiran.store'), [
            'employee_id' => $employeeLain->id,
            'tanggal'     => now()->format('Y-m-d'),
            'status'      => 'hadir',
            'jam_lembur'  => 0,
        ]);

        $this->assertDatabaseHas('attendances', ['employee_id' => $employeeSaya->id]);
        $this->assertDatabaseMissing('attendances', ['employee_id' => $employeeLain->id]);
    }

    public function test_kehadiran_yang_sudah_disimpan_tidak_bisa_diedit_lewat_url_manapun(): void
    {
        $employee = Employee::factory()->create();
        $user = User::factory()->create(['role' => 'karyawan', 'employee_id' => $employee->id]);

        $attendance = Attendance::factory()->create([
            'employee_id' => $employee->id,
            'tanggal'     => now()->format('Y-m-d'),
        ]);

        // Route update/delete untuk kehadiran karyawan SENGAJA tidak
        // pernah didaftarkan (lihat routes/web.php) — jadi walau
        // karyawan tau ID record miliknya sendiri, tidak ada endpoint
        // PUT/DELETE yang bisa dipanggil sama sekali. Ini membuktikan
        // sifat append-only, bukan cuma soal kepemilikan data.
        $this->actingAs($user)->put("/kehadiran-saya/{$attendance->id}", [
            'tanggal' => now()->format('Y-m-d'), 'status' => 'izin', 'jam_lembur' => 0,
        ])->assertNotFound();

        $this->actingAs($user)->delete("/kehadiran-saya/{$attendance->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('attendances', ['id' => $attendance->id, 'status' => 'hadir']);
    }

    public function test_admin_juga_tidak_punya_endpoint_untuk_ubah_atau_hapus_kehadiran(): void
    {
        $admin = User::factory()->admin()->create();
        $attendance = Attendance::factory()->create();

        // Sama seperti karyawan: admin juga tidak punya rute PUT/DELETE
        // untuk kehadiran — index() admin murni read-only (lihat
        // routes/web.php, grup admin cuma daftar GET /kehadiran).
        $this->actingAs($admin)->put("/kehadiran-saya/{$attendance->id}", [])
            ->assertNotFound();

        $this->actingAs($admin)->delete("/kehadiran-saya/{$attendance->id}")
            ->assertNotFound();
    }

    public function test_karyawan_tidak_bisa_input_tanggal_masa_depan(): void
    {
        $employee = Employee::factory()->create();
        $user = User::factory()->create(['role' => 'karyawan', 'employee_id' => $employee->id]);

        $response = $this->actingAs($user)->post(route('karyawan.kehadiran.store'), [
            'tanggal'    => now()->addDays(3)->format('Y-m-d'),
            'status'     => 'hadir',
            'jam_lembur' => 0,
        ]);

        $response->assertSessionHasErrors('tanggal');
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_karyawan_tidak_bisa_input_tanggal_sebelumnya(): void
    {
        $employee = Employee::factory()->create();
        $user = User::factory()->create(['role' => 'karyawan', 'employee_id' => $employee->id]);

        $response = $this->actingAs($user)->post(route('karyawan.kehadiran.store'), [
            'tanggal'    => now()->subDays(1)->format('Y-m-d'),
            'status'     => 'hadir',
            'jam_lembur' => 0,
        ]);

        $response->assertSessionHasErrors('tanggal');
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_karyawan_tidak_bisa_input_kehadiran_dobel_di_tanggal_sama(): void
    {
        $employee = Employee::factory()->create();
        $user = User::factory()->create(['role' => 'karyawan', 'employee_id' => $employee->id]);

        Attendance::factory()->create([
            'employee_id' => $employee->id,
            'tanggal'     => now()->format('Y-m-d'),
        ]);

        $response = $this->actingAs($user)->post(route('karyawan.kehadiran.store'), [
            'tanggal'    => now()->format('Y-m-d'),
            'status'     => 'hadir',
            'jam_lembur' => 0,
        ]);

        $response->assertSessionHasErrors('tanggal');
        $this->assertDatabaseCount('attendances', 1);
    }

    public function test_karyawan_harus_menambahkan_keterangan_saat_izin(): void
    {
        $employee = Employee::factory()->create();
        $user = User::factory()->create(['role' => 'karyawan', 'employee_id' => $employee->id]);

        $response = $this->actingAs($user)->post(route('karyawan.kehadiran.store'), [
            'tanggal'    => now()->format('Y-m-d'),
            'status'     => 'izin',
            'jam_lembur' => 0,
        ]);

        $response->assertSessionHasErrors('keterangan');
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_karyawan_harus_mengunggah_bukti_saat_sakit(): void
    {
        $employee = Employee::factory()->create();
        $user = User::factory()->create(['role' => 'karyawan', 'employee_id' => $employee->id]);

        $response = $this->actingAs($user)->post(route('karyawan.kehadiran.store'), [
            'tanggal'    => now()->format('Y-m-d'),
            'status'     => 'sakit',
            'jam_lembur' => 0,
        ]);

        $response->assertSessionHasErrors('bukti');
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_karyawan_bisa_menambah_kehadiran_sakit_dengan_bukti(): void
    {
        $employee = Employee::factory()->create();
        $user = User::factory()->create(['role' => 'karyawan', 'employee_id' => $employee->id]);

        Storage::fake('public');

        $response = $this->actingAs($user)->post(route('karyawan.kehadiran.store'), [
            'tanggal'    => now()->format('Y-m-d'),
            'status'     => 'sakit',
            'jam_lembur' => 0,
            'bukti'      => UploadedFile::fake()->image('surat-sakit.jpg'),
        ]);

        $response->assertRedirect();

        $attendance = Attendance::where('employee_id', $employee->id)->where('status', 'sakit')->first();
        $this->assertNotNull($attendance);
        $this->assertNotNull($attendance->bukti);
        Storage::disk('public')->assertExists($attendance->bukti);
    }

    public function test_halaman_kehadiran_saya_hanya_menampilkan_data_milik_sendiri(): void
    {
        $employeeSaya = Employee::factory()->create();
        $employeeLain = Employee::factory()->create();
        $user = User::factory()->create(['role' => 'karyawan', 'employee_id' => $employeeSaya->id]);

        Attendance::factory()->create(['employee_id' => $employeeSaya->id, 'tanggal' => now()->format('Y-m-d')]);
        Attendance::factory()->create(['employee_id' => $employeeLain->id, 'tanggal' => now()->format('Y-m-d')]);

        $response = $this->actingAs($user)->get(route('karyawan.kehadiran'));

        $response->assertOk();
        $response->assertViewHas('attendances', fn ($attendances) => $attendances->every(
            fn ($a) => $a->employee_id === $employeeSaya->id
        ));
    }

    public function test_karyawan_yang_akunnya_belum_tertaut_data_karyawan_ditolak(): void
    {
        $user = User::factory()->create(['role' => 'karyawan', 'employee_id' => null]);

        $response = $this->actingAs($user)->get(route('karyawan.kehadiran'));

        $response->assertForbidden();
    }
}
