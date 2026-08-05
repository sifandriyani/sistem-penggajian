@extends('layouts.app')

@section('title', 'Data Kehadiran (Pantauan)')

@section('content')

<div class="alert alert-secondary">
    <i class="bi bi-info-circle"></i>
    Halaman ini <strong>hanya untuk memantau</strong> — karyawan input kehadiran & lemburnya
    masing-masing lewat akun mereka sendiri. HRD tidak menambah/mengedit data di sini, supaya
    kehadiran benar-benar mencerminkan input asli karyawan. Data inilah yang dipakai saat
    <strong>Generate Payroll</strong> untuk menghitung Uang Lembur.
</div>

<div class="card card-dashboard mb-3">
    <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
        <form action="{{ route('attendance.index') }}" method="GET" class="d-flex gap-2 align-items-center">
            <label class="me-1">Periode</label>
            <input type="month" name="periode" value="{{ $periode }}" class="form-control" onchange="this.form.submit()">
        </form>
    </div>
</div>

<div class="card card-dashboard mb-3">
    <div class="card-header"><i class="bi bi-bar-chart"></i> Rekap Per Karyawan — {{ $periode }}</div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>NIP</th>
                        <th>Nama</th>
                        <th class="text-center">Hadir</th>
                        <th class="text-center">Izin</th>
                        <th class="text-center">Sakit</th>
                        <th class="text-end">Total Jam Lembur</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rekap as $r)
                        <tr>
                            <td>{{ $r['employee']->nip }}</td>
                            <td>{{ $r['employee']->nama }}</td>
                            <td class="text-center">{{ $r['hadir'] }}</td>
                            <td class="text-center">{{ $r['izin'] }}</td>
                            <td class="text-center">{{ $r['sakit'] }}</td>
                            <td class="text-end">{{ $r['jam_lembur'] }} jam</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-secondary text-center">Belum ada karyawan yang input kehadiran pada periode ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card card-dashboard">
    <div class="card-header"><i class="bi bi-calendar-check"></i> Log Harian — {{ $periode }}</div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>NIP</th>
                        <th>Nama</th>
                        <th class="text-center">Status</th>
                        <th>Keterangan</th>
                        <th class="text-end">Jam Lembur</th>
                        <th>Bukti</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($attendances as $a)
                        @php
                            $badge = match($a->status) {
                                'hadir' => 'bg-success',
                                'izin' => 'bg-info',
                                'sakit' => 'bg-warning text-dark',
                            };
                        @endphp
                        <tr>
                            <td>{{ $a->tanggal->format('d-m-Y') }}</td>
                            <td>{{ $a->employee->nip }}</td>
                            <td>{{ $a->employee->nama }}</td>
                            <td class="text-center"><span class="badge {{ $badge }}">{{ ucfirst($a->status) }}</span></td>
                            <td>{{ $a->keterangan ?? '-' }}</td>
                            <td class="text-end">{{ $a->jam_lembur }} jam</td>
                            <td>
                                @if ($a->bukti)
                                    <a href="{{ asset('storage/' . $a->bukti) }}" target="_blank" rel="noopener">Lihat</a>
                                @else
                                    -
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-secondary text-center">Belum ada data kehadiran pada periode ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@endsection
