@extends('layouts.app')

@section('title', 'Slip Gaji Saya')

@section('content')

<div class="card card-dashboard">
    <div class="card-header"><i class="bi bi-receipt"></i> Riwayat Slip Gaji Saya</div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Periode</th>
                        <th class="text-end">Gaji Bersih</th>
                        <th class="text-center">Status</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($payrolls as $p)
                        <tr>
                            <td>{{ $p->periode }}</td>
                            <td class="text-end">Rp {{ number_format($p->gaji_bersih, 0, ',', '.') }}</td>
                            <td class="text-center">
                                @if ($p->isSudahDibayar())
                                    <span class="badge bg-success">Sudah Dibayar</span>
                                @else
                                    <span class="badge bg-warning text-dark">Belum Dibayar</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('payroll.slip', $p) }}" class="btn btn-primary btn-sm">
                                    <i class="bi bi-eye"></i> Lihat Detail
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-secondary text-center">Belum ada slip gaji.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@endsection
