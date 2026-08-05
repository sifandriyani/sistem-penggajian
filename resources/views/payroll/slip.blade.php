@extends('layouts.app')

@section('title', 'Slip Gaji')

@section('content')

<div class="card card-dashboard" style="max-width: 560px;">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-receipt"></i> Slip Gaji</span>
        <button onclick="window.print()" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-printer"></i> Cetak
        </button>
    </div>
    <div class="card-body">
        <p class="mb-3">
            <strong>{{ $payroll->employee->nama }}</strong> ({{ $payroll->employee->nip }})
            — Periode <strong>{{ $payroll->periode }}</strong>
            @if ($payroll->isSudahDibayar())
                <span class="badge bg-success"><i class="bi bi-check-circle"></i> Sudah Dibayar</span>
            @else
                <span class="badge bg-warning text-dark"><i class="bi bi-clock-history"></i> Belum Dibayar</span>
            @endif

            {{-- Informasi pembayaran (jika ada) --}}
            @if ($payroll->paid_at)
                <div class="mt-2 small text-secondary">
                    <div><strong>Pembayaran:</strong> {{ ucfirst($payroll->payment_method ?? '—') }} @if($payroll->payment_method === 'bank') — {{ $payroll->payment_channel ?? '-' }} @endif</div>
                    <div>
                        No. Rekening:
                        @if($payroll->payment_account)
                            @if(auth()->user()->isAdmin())
                                <span id="paymentAccountMasked">***{{ substr($payroll->payment_account, -4) }}</span>
                                <span id="paymentAccountFull" style="display:none;">{{ $payroll->payment_account }}</span>
                                <button type="button" id="btnToggleAccount" class="btn btn-link btn-sm">Tampilkan</button>
                            @else
                                <span>***{{ substr($payroll->payment_account, -4) }}</span>
                            @endif
                        @else
                            -
                        @endif
                    </div>
                    <div>No. Bukti / Keterangan: {{ $payroll->payment_note ?? '-' }}</div>
                    <div>Tanggal dibayar: {{ $payroll->paid_at->translatedFormat('d F Y H:i') }}</div>
                    <div>Dicatat oleh: {{ optional($payroll->paidBy)->name ?? '-' }}</div>
                </div>
            @endif

            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    var btn = document.getElementById('btnToggleAccount');
                    if (!btn) return;
                    var masked = document.getElementById('paymentAccountMasked');
                    var full = document.getElementById('paymentAccountFull');
                    var shown = false;
                    var csrf = '{{ csrf_token() }}';

                    btn.addEventListener('click', function () {
                        shown = !shown;
                        if (shown) {
                            // show full
                            masked.style.display = 'none';
                            full.style.display = '';
                            btn.textContent = 'Sembunyikan';

                            // record audit of viewing full account via AJAX
                            fetch("{{ route('payroll.view-account', $payroll) }}", {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': csrf,
                                    'Accept': 'application/json'
                                },
                                body: JSON.stringify({})
                            }).catch(function (err) {
                                console.warn('Audit view failed', err);
                            });
                        } else {
                            // hide
                            masked.style.display = '';
                            full.style.display = 'none';
                            btn.textContent = 'Tampilkan';
                        }
                    });
                });
            </script>
        </p>

        <table class="table table-sm">
            <tbody>
                <tr><td>Gaji Pokok</td><td class="text-end">Rp {{ number_format($payroll->gaji_pokok, 0, ',', '.') }}</td></tr>
                <tr><td>Tunjangan</td><td class="text-end">Rp {{ number_format($payroll->total_tunjangan, 0, ',', '.') }}</td></tr>
                <tr><td>Uang Lembur</td><td class="text-end">Rp {{ number_format($payroll->uang_lembur, 0, ',', '.') }}</td></tr>
                <tr><td>Potongan BPJS Kesehatan</td><td class="text-end text-danger">- Rp {{ number_format($payroll->potongan_bpjs_kesehatan, 0, ',', '.') }}</td></tr>
                <tr><td>Potongan BPJS JHT</td><td class="text-end text-danger">- Rp {{ number_format($payroll->potongan_bpjs_jht, 0, ',', '.') }}</td></tr>
                <tr><td>Potongan BPJS JP</td><td class="text-end text-danger">- Rp {{ number_format($payroll->potongan_bpjs_jp, 0, ',', '.') }}</td></tr>
                <tr><td>Potongan PPh21</td><td class="text-end text-danger">- Rp {{ number_format($payroll->potongan_pph21, 0, ',', '.') }}</td></tr>
                <tr><td>Potongan Alpha</td><td class="text-end text-danger">- Rp {{ number_format($payroll->potongan_alpha, 0, ',', '.') }}</td></tr>
                <tr><td>Total Potongan</td><td class="text-end text-danger">- Rp {{ number_format($payroll->total_potongan, 0, ',', '.') }}</td></tr>
                <tr class="table-light fw-bold fs-5"><td>Gaji Bersih</td><td class="text-end">Rp {{ number_format($payroll->gaji_bersih, 0, ',', '.') }}</td></tr>
            </tbody>
        </table>

        <a href="{{ auth()->user()->isAdmin() ? route('payroll.index', ['periode' => $payroll->periode]) : route('karyawan.slip-saya') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Kembali
        </a>
    </div>
</div>

@endsection
