<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\PayrollController;
use Illuminate\Support\Facades\Route;

// ---------------------------------------------------------------------
// Guest (belum login)
// ---------------------------------------------------------------------
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
});

Route::get('/', fn () => redirect()->route('dashboard'));

// ---------------------------------------------------------------------
// Butuh login (admin ATAU karyawan)
// ---------------------------------------------------------------------
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', [AuthController::class, 'dashboard'])->name('dashboard');

    // Self-service karyawan: hanya bisa lihat slip miliknya sendiri
    Route::get('/slip-saya', [PayrollController::class, 'slipSaya'])->name('karyawan.slip-saya');
    Route::get('/slip/{payroll}', [PayrollController::class, 'slip'])->name('payroll.slip'); // otorisasi dicek di controller

    // Self-service karyawan: input kehadiran & lembur milik sendiri
    // (APPEND-ONLY — sengaja tidak ada route update/delete, lihat
    // docblock AttendanceController
    Route::get('/kehadiran-saya', [AttendanceController::class, 'mine'])->name('karyawan.kehadiran');
    Route::post('/kehadiran-saya', [AttendanceController::class, 'storeMine'])->name('karyawan.kehadiran.store');
});

// ---------------------------------------------------------------------
// Khusus admin (HRD) — kelola karyawan, generate payroll, laporan
// ---------------------------------------------------------------------
Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/karyawan', [EmployeeController::class, 'index'])->name('employee.index');
    Route::post('/karyawan', [EmployeeController::class, 'store'])->name('employee.store');
    Route::put('/karyawan/{employee}', [EmployeeController::class, 'update'])->name('employee.update');
    Route::delete('/karyawan/{employee}', [EmployeeController::class, 'destroy'])->name('employee.destroy');
    
    Route::get('/jabatan', [EmployeeController::class, 'indexJabatan'])->name('employee.jabatan.index');
    Route::post('/jabatan', [EmployeeController::class, 'storeJabatan'])->name('employee.jabatan.store');
    Route::put('/jabatan/{jabatan}', [EmployeeController::class, 'updateJabatan'])->name('employee.jabatan.update');
    Route::delete('/jabatan/{jabatan}', [EmployeeController::class, 'destroyJabatan'])->name('employee.jabatan.destroy');
     
    Route::get('/kehadiran', [AttendanceController::class, 'index'])->name('attendance.index');

    Route::get('/payroll', [PayrollController::class, 'index'])->name('payroll.index');
    Route::post('/payroll/generate', [PayrollController::class, 'generate'])->name('payroll.generate');
    Route::post('/payroll/deduction-rates', [PayrollController::class, 'updateDeductionRates'])->name('payroll.update-deduction-rates');
    Route::put('/payroll/{payroll}', [PayrollController::class, 'update'])->name('payroll.update');
    Route::patch('/payroll/{payroll}/status', [PayrollController::class, 'updateStatus'])->name('payroll.update-status');
    Route::post('/payroll/bulk-mark-paid', [PayrollController::class, 'bulkMarkPaid'])->name('payroll.bulk-mark-paid');
    Route::post('/payroll/mark-all-unpaid', [PayrollController::class, 'markAllUnpaid'])->name('payroll.mark-all-unpaid');
    Route::post('/payroll/reset-all-paid', [PayrollController::class, 'resetAllPaid'])->name('payroll.reset-all-paid');
    Route::post('/payroll/{payroll}/view-account', [PayrollController::class, 'viewPaymentAccount'])->name('payroll.view-account');
    Route::post('/employee/{employee}/view-default-account', [EmployeeController::class, 'viewDefaultPaymentAccount'])->name('employee.view-default-account');
    Route::delete('/payroll/{payroll}', [PayrollController::class, 'destroy'])->name('payroll.destroy');
    Route::get('/payroll/laporan-per-jabatan', [PayrollController::class, 'laporanPerJabatan'])->name('payroll.laporan');
});
