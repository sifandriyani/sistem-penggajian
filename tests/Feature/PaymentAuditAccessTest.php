<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Payroll;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PaymentAuditAccessTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function admin_dapat_melihat_nomor_rekening_dan_mencatat_audit_untuk_payroll_dan_default_karyawan(): void
    {
        $admin = User::factory()->admin()->create();

        $employee = Employee::factory()->create([
            'default_payment_method' => 'bank',
            'default_payment_channel' => 'BCA',
            'default_payment_account' => '1234567890',
        ]);

        $payroll = Payroll::factory()->create([
            'employee_id' => $employee->id,
            'payment_method' => 'bank',
            'payment_channel' => 'BCA',
            'payment_account' => '1234567890',
        ]);

        $this->actingAs($admin)
            ->post(route('payroll.view-account', $payroll), [])
            ->assertOk();

        $this->actingAs($admin)
            ->post(route('employee.view-default-account', $employee), [])
            ->assertOk();

        $this->assertDatabaseHas('payment_audits', [
            'payroll_id' => $payroll->id,
            'employee_id' => $employee->id,
            'action' => 'view_account',
            'performed_by' => $admin->id,
        ]);

        $this->assertDatabaseHas('payment_audits', [
            'employee_id' => $employee->id,
            'action' => 'view_default_account',
            'performed_by' => $admin->id,
        ]);

        $auditRows = DB::table('payment_audits')
            ->whereIn('action', ['view_account', 'view_default_account'])
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $auditRows);
        $this->assertNotNull($auditRows->first()->ip_address);
        $this->assertNotNull($auditRows->first()->user_agent);
    }

    /** @test */
    public function karyawan_tidak_boleh_melihat_nomor_rekening_dan_tidak_membuat_audit(): void
    {
        $karyawan = User::factory()->create(['role' => 'karyawan']);
        $employee = Employee::factory()->create([
            'default_payment_method' => 'bank',
            'default_payment_channel' => 'BCA',
            'default_payment_account' => '1234567890',
        ]);
        $payroll = Payroll::factory()->create([
            'employee_id' => $employee->id,
            'payment_method' => 'bank',
            'payment_channel' => 'BCA',
            'payment_account' => '1234567890',
        ]);

        $this->actingAs($karyawan)
            ->post(route('payroll.view-account', $payroll), [])
            ->assertForbidden();

        $this->actingAs($karyawan)
            ->post(route('employee.view-default-account', $employee), [])
            ->assertForbidden();

        $this->assertDatabaseCount('payment_audits', 0);
    }
}
