@extends('layouts.app')

@section('title', 'Laporan Penggajian')

@section('content')

<div class="card card-dashboard mb-3">
    <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
        <form action="{{ route('payroll.laporan') }}" method="GET" class="d-flex gap-2 align-items-center">
            <label class="me-1">Periode</label>
            <input type="month" name="periode" value="{{ $periode }}" class="form-control" onchange="this.form.submit()">
        </form>
        <a href="{{ route('payroll.index', ['periode' => $periode]) }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Kembali ke Data Penggajian
        </a>
    </div>
</div>

<div class="card card-dashboard mb-3">
    <div class="card-header"><i class="bi bi-bar-chart"></i> Ringkasan Agregat Per Jabatan — {{ $periode }}</div>
    <div class="card-body">
        <p class="text-secondary small">
            Dihasilkan dari raw SQL (JOIN + GROUP BY) di <code>PayrollController::laporanPerJabatan()</code>.
        </p>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Jabatan</th>
                        <th class="text-end">Jumlah Karyawan</th>
                        <th class="text-end">Total Gaji Bersih</th>
                        <th class="text-end">Rata-rata Gaji Bersih</th>
                        <th class="text-end">Total Uang Lembur</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td><span class="badge bg-secondary">{{ ucfirst($row->jabatan) }}</span></td>
                            <td class="text-end">{{ $row->jumlah_karyawan }}</td>
                            <td class="text-end">Rp {{ number_format($row->total_gaji_bersih, 0, ',', '.') }}</td>
                            <td class="text-end">Rp {{ number_format($row->rata_rata_gaji_bersih, 0, ',', '.') }}</td>
                            <td class="text-end">Rp {{ number_format($row->total_uang_lembur, 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-secondary text-center">Belum ada payroll pada periode ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card card-dashboard">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-list-ul"></i> Detail Lengkap Per Karyawan — {{ $periode }}</span>
        <button onclick="window.print()" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-printer"></i> Cetak
        </button>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle">
                <thead>
                    <tr>
                        <th>NIP</th>
                        <th>Nama</th>
                        <th>Jabatan</th>
                        <th class="text-end">Gaji Pokok</th>
                        <th class="text-end">Tunjangan</th>
                        <th class="text-end">Lembur</th>
                        <th class="text-end">BPJS Kes</th>
                        <th class="text-end">BPJS JHT</th>
                        <th class="text-end">BPJS JP</th>
                        <th class="text-end">PPh21</th>
                        <th class="text-end">Alpha</th>
                        <th class="text-end">Potongan Lain</th>
                        <th class="text-end">Total Potongan</th>
                        <th class="text-end">Gaji Bersih</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($detail as $d)
                        <tr>
                            <td>{{ $d->nip }}</td>
                            <td>{{ $d->nama }}</td>
                            <td><span class="badge bg-secondary">{{ ucfirst($d->jabatan) }}</span></td>
                            <td class="text-end">Rp {{ number_format($d->gaji_pokok, 0, ',', '.') }}</td>
                            <td class="text-end">Rp {{ number_format($d->total_tunjangan, 0, ',', '.') }}</td>
                            <td class="text-end">Rp {{ number_format($d->uang_lembur, 0, ',', '.') }}</td>
                            <td class="text-end">Rp {{ number_format($d->potongan_bpjs_kesehatan, 0, ',', '.') }}</td>
                            <td class="text-end">Rp {{ number_format($d->potongan_bpjs_jht, 0, ',', '.') }}</td>
                            <td class="text-end">Rp {{ number_format($d->potongan_bpjs_jp, 0, ',', '.') }}</td>
                            <td class="text-end">Rp {{ number_format($d->potongan_pph21, 0, ',', '.') }}</td>
                            <td class="text-end">Rp {{ number_format($d->potongan_alpha, 0, ',', '.') }}</td>
                            <td class="text-end">Rp {{ number_format($d->potongan_lain, 0, ',', '.') }}</td>
                            <td class="text-end">Rp {{ number_format($d->total_potongan, 0, ',', '.') }}</td>
                            <td class="text-end"><strong>Rp {{ number_format($d->gaji_bersih, 0, ',', '.') }}</strong></td>
                            <td class="text-center">
                                @if ($d->status === 'sudah_dibayar')
                                    <span class="badge bg-success">Sudah</span>
                                @else
                                    <span class="badge bg-warning text-dark">Belum</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="14" class="text-secondary text-center">Belum ada payroll pada periode ini.</td></tr>
                    @endforelse
                </tbody>
                @if (count($detail))
                    <tfoot>
                        <tr class="table-light fw-bold">
                            <td colspan="3">Grand Total</td>
                            <td class="text-end">Rp {{ number_format($grandTotal['gaji_pokok'], 0, ',', '.') }}</td>
                            <td class="text-end">Rp {{ number_format($grandTotal['total_tunjangan'], 0, ',', '.') }}</td>
                            <td class="text-end">Rp {{ number_format($grandTotal['uang_lembur'], 0, ',', '.') }}</td>
                            <td class="text-end">Rp {{ number_format($grandTotal['potongan_bpjs_kesehatan'], 0, ',', '.') }}</td>
                            <td class="text-end">Rp {{ number_format($grandTotal['potongan_bpjs_jht'], 0, ',', '.') }}</td>
                            <td class="text-end">Rp {{ number_format($grandTotal['potongan_bpjs_jp'], 0, ',', '.') }}</td>
                            <td class="text-end">Rp {{ number_format($grandTotal['potongan_pph21'], 0, ',', '.') }}</td>
                            <td class="text-end">Rp {{ number_format($grandTotal['potongan_alpha'], 0, ',', '.') }}</td>
                            <td class="text-end">Rp {{ number_format($grandTotal['potongan_lain'], 0, ',', '.') }}</td>
                            <td class="text-end">Rp {{ number_format($grandTotal['total_potongan'], 0, ',', '.') }}</td>
                            <td class="text-end">Rp {{ number_format($grandTotal['gaji_bersih'], 0, ',', '.') }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>

@endsection
