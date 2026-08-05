@extends('layouts.app')

@section('title', 'Beranda')

@section('content')

@if (!empty($notifications))
    <div class="alert alert-info">
        <strong>Notifikasi:</strong>
        <ul class="mb-0 mt-2">
            @foreach ($notifications as $notif)
                <li>{{ $notif['message'] }} @if(isset($notif['admin_name']))(oleh {{ $notif['admin_name'] }})@endif</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="row mb-4">
    <div class="col-md-12">
        <h3>Halo, {{ auth()->user()->name }} 👋</h3>
        <p class="text-secondary">Selamat datang di Sistem Informasi Penggajian</p>
    </div>
</div>

@if($isAdmin)

    <div class="row g-3">
        <div class="col-md-3">
            <div class="card card-dashboard h-100">
                <div class="card-body">
                    <h6 class="text-secondary"><i class="bi bi-people"></i> Total Karyawan</h6>
                    <h2>{{ $totalKaryawan }}</h2>
                </div>
            </div>
        </div>
        <div class="col-md-5">
            <div class="card card-dashboard h-100">
                <div class="card-body">
                    <h6 class="text-secondary"><i class="bi bi-wallet2"></i> Total Payroll Bulan Lalu ({{ $periode }})</h6>
                    <h4>Rp {{ number_format($totalPayroll, 0, ',', '.') }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-dashboard h-100">
                <div class="card-body">
                    <h6 class="text-secondary">Aksi Cepat</h6>
                    <a href="{{ route('employee.jabatan.index') }}" class="btn btn-outline-primary btn-sm w-100 mb-2">
                        <i class="bi bi-briefcase"></i> Kelola Data Jabatan
                    </a>
                    <a href="{{ route('employee.index') }}" class="btn btn-outline-primary btn-sm w-100 mb-2">
                        <i class="bi bi-person-plus"></i> Kelola Data Karyawan
                    </a>
                    <a href="{{ route('payroll.index') }}" class="btn btn-outline-primary btn-sm w-100">
                        <i class="bi bi-cash-coin"></i> Kelola Penggajian
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mt-1">
        <div class="col-md-4">
            <div class="card card-dashboard h-100">
                <div class="card-header">Karyawan per Jabatan</div>
                <div class="card-body">
                    <canvas id="chartJabatan" height="220"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card card-dashboard h-100">
                <div class="card-header">Payroll Terbaru</div>
                <div class="card-body">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>NIP</th>
                                <th>Nama</th>
                                <th>Periode</th>
                                <th class="text-end">Gaji Bersih</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($payrollTerbaru as $p)
                                <tr>
                                    <td>{{ $p->employee->nip }}</td>
                                    <td>{{ $p->employee->nama }}</td>
                                    <td>{{ $p->periode }}</td>
                                    <td class="text-end">Rp {{ number_format($p->gaji_bersih, 0, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-secondary">Belum ada payroll yang digenerate.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
    <script>
        new Chart(document.getElementById('chartJabatan'), {
            type: 'doughnut',
            data: {
                labels: [@foreach($perJabatan as $row) '{{ ucfirst($row->jabatan) }}', @endforeach],
                datasets: [{
                    data: [@foreach($perJabatan as $row) {{ $row->jumlah }}, @endforeach],
                    backgroundColor: ['#2563eb', '#16a34a', '#f59e0b', '#dc2626'],
                }]
            },
            options: { plugins: { legend: { position: 'bottom' } } }
        });
    </script>

@else

    @unless($sudahAbsenHariIni)
        <div class="card card-dashboard mb-3">
            <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <i class="bi bi-calendar-check text-primary"></i>
                    Kamu belum isi kehadiran hari ini ({{ now()->translatedFormat('d F Y') }}).
                </div>
                <a href="{{ route('karyawan.kehadiran', ['tambah' => 1]) }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg"></i> Isi Kehadiran Sekarang
                </a>
            </div>
        </div>
    @endunless

    <div class="card card-dashboard">
        <div class="card-header">Slip Gaji Terbaru Saya</div>
        <div class="card-body">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Periode</th>
                        <th class="text-end">Gaji Bersih</th>
                        <th class="text-end">Slip</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($payrolls as $p)
                        <tr>
                            <td>{{ $p->periode }}</td>
                            <td class="text-end">Rp {{ number_format($p->gaji_bersih, 0, ',', '.') }}</td>
                            <td class="text-end">
                                <a href="{{ route('payroll.slip', $p) }}" class="btn btn-primary btn-sm">
                                    <i class="bi bi-receipt"></i> Lihat
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-secondary">Belum ada slip gaji.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <a href="{{ route('karyawan.slip-saya') }}" class="btn btn-outline-secondary btn-sm mt-3">
                Lihat Semua Slip Gaji Saya
            </a>
        </div>
    </div>

@endif

@endsection
