<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Jabatan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Menguji validasi rentang gaji pokok per jabatan (skala upah internal),
 * lihat Employee::rentangGajiPokok() dan EmployeeController.
 */
class EmployeeSalaryRangeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Jabatan::insert([
            ['kode' => 'staff', 'nama' => 'Staff', 'gaji_min' => 4_000_000, 'gaji_max' => 6_000_000, 'multiplier' => 0.10, 'created_at' => now(), 'updated_at' => now()],
            ['kode' => 'supervisor', 'nama' => 'Supervisor', 'gaji_min' => 6_000_000, 'gaji_max' => 8_000_000, 'multiplier' => 0.20, 'created_at' => now(), 'updated_at' => now()],
            ['kode' => 'manager', 'nama' => 'Manager', 'gaji_min' => 8_000_000, 'gaji_max' => 10_000_000, 'multiplier' => 0.30, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->admin = User::factory()->admin()->create();
    }

    /** @test */
    public function gaji_pokok_ditentukan_oleh_master_jabatan_saat_tambah(): void
    {
        $response = $this->actingAs($this->admin)->post(route('employee.store'), [
            'nip'                   => 'EMP999',
            'nama'                  => 'Test Karyawan',
            'jabatan'               => 'staff',
            'gaji_pokok'            => 20_000_000,
            'jumlah_anak'           => 0,
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('employees', [
            'nip'       => 'EMP999',
            'jabatan_id' => Jabatan::where('kode', 'staff')->value('id'),
            'gaji_pokok' => Jabatan::where('kode', 'staff')->value('gaji_min'),
        ]);
    }

    /** @test */
    public function gaji_pokok_ditentukan_oleh_master_jabatan_saat_update(): void
    {
        $employee = Employee::factory()->create(['jabatan' => 'staff']);

        $response = $this->actingAs($this->admin)->put(route('employee.update', $employee), [
            'nip'                 => $employee->nip,
            'nama'                => $employee->nama,
            'jabatan'             => 'manager',
            'gaji_pokok'          => 5_000_000,
            'jumlah_anak'         => $employee->jumlah_anak,
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('employees', [
            'id'         => $employee->id,
            'jabatan_id' => Jabatan::where('kode', 'manager')->value('id'),
            'gaji_pokok' => Jabatan::where('kode', 'manager')->value('gaji_min'),
        ]);
    }
}
