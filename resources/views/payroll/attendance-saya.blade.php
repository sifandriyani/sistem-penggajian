@extends('layouts.app')

@section('title', 'Kehadiran Saya')

@section('content')

@if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

@if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="alert alert-secondary">
    <i class="bi bi-info-circle"></i>
    Input kehadiran & jam lembur kamu di sini setiap hari kerja. Data ini yang dipakai HRD
    untuk menghitung <strong>uang lembur</strong> saat generate payroll bulanan.
    <strong>Pastikan data yang kamu isi sudah benar</strong> — begitu disimpan, data
    <strong>tidak bisa diubah atau dihapus lagi</strong> oleh siapapun, termasuk kamu sendiri.
</div>

<div class="card card-dashboard mb-3">
    <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
        <form action="{{ route('karyawan.kehadiran') }}" method="GET" class="d-flex gap-2 align-items-center">
            <label class="me-1">Periode</label>
            <input type="month" name="periode" value="{{ $periode }}" class="form-control" onchange="this.form.submit()">
        </form>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTambahKehadiranSaya">
            <i class="bi bi-plus-lg"></i> Tambah Kehadiran
        </button>
    </div>
</div>

<div class="card card-dashboard">
    <div class="card-header"><i class="bi bi-calendar-check"></i> Kehadiran Saya — Periode {{ $periode }}</div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Tanggal</th>
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
                        <tr><td colspan="5" class="text-secondary text-center">Belum ada data kehadiran pada periode ini. Klik "Tambah Kehadiran" di atas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Modal Tambah --}}
<div class="modal fade" id="modalTambahKehadiranSaya" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('karyawan.kehadiran.store') }}" enctype="multipart/form-data">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Kehadiran</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    @include('payroll.partials.attendance-fields-saya', ['a' => null])
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </div>
        </form>
    </div>
</div>

@endsection

@if (request()->boolean('tambah'))
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            new bootstrap.Modal(document.getElementById('modalTambahKehadiranSaya')).show();
        });
    </script>
@endif
