<?php

namespace Tests\Unit;

use App\Models\DeductionRate;
use App\Models\Employee;
use App\Services\PayrollCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Unit test murni: menguji setiap method perhitungan di
 * PayrollCalculationService secara terisolasi (tanpa HTTP, tanpa
 * database untuk sebagian besar test, kecuali yang butuh model Employee).
 */
class PayrollCalculationServiceTest extends TestCase
{
    use RefreshDatabase;

    private PayrollCalculationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PayrollCalculationService();
    }

    public function test_rentang_gaji_pokok_meningkat_seiring_jenjang_jabatan(): void
    {
        $staff = Employee::rentangGajiPokok('staff');
        $supervisor = Employee::rentangGajiPokok('supervisor');
        $manager = Employee::rentangGajiPokok('manager');

        $this->assertLessThanOrEqual($supervisor['min'], $staff['max']);
        $this->assertLessThanOrEqual($manager['min'], $supervisor['max']);
    }

    /** @test */
    public function test_tunjangan_manager_dihitung_dengan_multiplier_30_persen(): void
    {
        $employee = Employee::factory()->create([
            'jabatan'             => 'manager',
            'gaji_pokok'          => 10_000_000,
            'status_menikah'      => false,
            'jumlah_anak'         => 0,
        ]);

        // 10jt * 0.30 + 0 (belum menikah) = 3.000.000
        $this->assertEquals(3_000_000.0, $this->service->calculateTunjangan($employee));
    }

    /** @test */
    public function test_tunjangan_supervisor_dihitung_dengan_multiplier_20_persen(): void
    {
        $employee = Employee::factory()->create([
            'jabatan'             => 'supervisor',
            'gaji_pokok'          => 7_000_000,
            'status_menikah'      => false,
            'jumlah_anak'         => 0,
        ]);

        // 7jt * 0.20 + 0 (belum menikah) = 1.400.000
        $this->assertEquals(1_400_000.0, $this->service->calculateTunjangan($employee));
    }

    /** @test */
    public function test_transport_dihitung_dari_master_jabatan_per_hari_hadir(): void
    {
        $employee = Employee::factory()->create([
            'jabatan' => 'staff',
            'gaji_pokok' => 6_000_000,
            'status_menikah' => false,
            'jumlah_anak' => 0,
        ]);
        $employee->jabatanMaster->update(['transport_per_hari' => 25_000]);

        // 6jt * 0.10 + (25rb * 20 hari) = 1.100.000
        $this->assertEquals(1_100_000.0, $this->service->calculateTunjangan($employee, 20));
    }

    /** @test */
    public function test_tunjangan_anak_maksimal_3_walau_anak_lebih_dari_3(): void
    {
        $employee = Employee::factory()->create([
            'jabatan'             => 'staff',
            'gaji_pokok'          => 6_000_000,
            'status_menikah'      => true,
            'jumlah_anak'         => 5, // harus dianggap 3
        ]);

        // 6jt * 0.10 + (3 * 150rb) = 1.050.000
        $this->assertEquals(1_050_000.0, $this->service->calculateTunjangan($employee));
    }

    /** @test */
    public function test_upah_lembur_dihitung_berdasar_173_jam_kerja_per_bulan(): void
    {
        // gaji_pokok 8.650.000 / 173 jam = 50.000/jam -> lembur 4 jam * 1.5 = 300.000
        $hasil = $this->service->calculateOvertimePay(8_650_000, 4);

        $this->assertEquals(300_000.0, $hasil);
    }

    /** @test */
    public function test_tidak_ada_lembur_menghasilkan_nol(): void
    {
        $this->assertEquals(0.0, $this->service->calculateOvertimePay(8_650_000, 0));
    }

    /** @test */
    public function test_potongan_bpjs_total_4_persen_dari_gaji_pokok(): void
    {
        $hasil = $this->service->calculateBpjsDeduction(10_000_000);

        $this->assertEquals(400_000.0, $hasil);
    }

    /** @test */
    public function test_calculate_bpjs_kesehatan_uses_database_rate_when_present(): void
    {
        DeductionRate::create([
            'kode'       => 'bpjs_kesehatan',
            'nama'       => 'BPJS Kesehatan',
            'jenis'      => 'percentage',
            'nilai'      => 0.02,
            'keterangan' => 'Dua persen untuk BPJS Kesehatan',
        ]);

        $hasil = $this->service->calculateBpjsKesehatan(10_000_000);

        $this->assertEquals(200_000.0, $hasil);
    }

    /** @test */
    public function test_calculate_alpha_uses_fixed_amount_from_database_rate(): void
    {
        DeductionRate::create([
            'kode'       => 'alpha',
            'nama'       => 'Potongan Alpha',
            'jenis'      => 'fixed',
            'nilai'      => 25_000,
            'keterangan' => 'Potongan per hari alpha',
        ]);

        $attendances = new Collection([
            (object) ['status' => 'izin'],
            (object) ['status' => 'sakit'],
        ]);

        $this->assertEquals(50_000.0, $this->service->calculateAlpha($attendances));
    }

    /** @test */
    public function test_kalkulasi_end_to_end_menghasilkan_gaji_bersih_yang_konsisten(): void
    {
        $employee = Employee::factory()->create([
            'jabatan'             => 'staff',
            'gaji_pokok'          => 6_000_000,
            'status_menikah'      => false,
            'jumlah_anak'         => 0,
        ]);

        $attendances = new Collection(); // tidak ada lembur

        $hasil = $this->service->calculate($employee, $attendances);

        $penghasilanBruto = $hasil['gaji_pokok'] + $hasil['total_tunjangan'] + $hasil['uang_lembur'];
        $totalPotongan    = $hasil['potongan_bpjs'];

        $this->assertEqualsWithDelta(
            $penghasilanBruto - $totalPotongan,
            $hasil['gaji_bersih'],
            0.01,
            'Gaji bersih harus sama dengan bruto dikurangi seluruh potongan.'
        );
    }

    /** @test */
    public function test_gaji_bersih_tidak_boleh_negatif_walau_gaji_pokok_nol(): void
    {
        $employee = Employee::factory()->create([
            'jabatan'             => 'staff',
            'gaji_pokok'          => 0,
            'status_menikah'      => false,
            'jumlah_anak'         => 0,
        ]);

        $hasil = $this->service->calculate($employee, new Collection());

        $this->assertGreaterThanOrEqual(0.0, $hasil['gaji_bersih']);
    }
}
