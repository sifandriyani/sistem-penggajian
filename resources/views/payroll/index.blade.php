@extends('layouts.app')

@section('title', 'Data Penggajian')

@section('content')

@if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

<div class="card card-dashboard mb-3">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <form action="{{ route('payroll.generate') }}" method="POST" class="d-flex gap-2 align-items-center">
                @csrf
                <label class="mb-0 d-flex align-items-center">
                    Periode
                    <input type="month" name="periode" value="{{ $periode }}" class="form-control ms-2" onchange="this.form.submit()" required>
                </label>
            </form>
            <div class="d-flex gap-2 align-items-center">
                <form method="GET" action="{{ route('payroll.index') }}" class="d-flex gap-2 align-items-center">
                    <input type="hidden" name="periode" value="{{ $periode }}">
                    <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="all" {{ $statusFilter === 'all' ? 'selected' : '' }}>Semua</option>
                        <option value="belum" {{ $statusFilter === 'belum' ? 'selected' : '' }}>Belum Dibayar</option>
                        <option value="dibayar" {{ $statusFilter === 'dibayar' ? 'selected' : '' }}>Sudah Dibayar</option>
                    </select>
                    <select name="payment_method" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="all" {{ $paymentMethodFilter === 'all' ? 'selected' : '' }}>Semua Metode</option>
                        <option value="bank" {{ $paymentMethodFilter === 'bank' ? 'selected' : '' }}>Bank</option>
                        <option value="cash" {{ $paymentMethodFilter === 'cash' ? 'selected' : '' }}>Cash</option>
                        <option value="other" {{ $paymentMethodFilter === 'other' ? 'selected' : '' }}>Lainnya</option>
                    </select>
                </form>
                @if ($statusFilter !== 'all' || $paymentMethodFilter !== 'all')
                    <a href="{{ route('payroll.index', ['periode' => $periode]) }}" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-x-circle"></i> Reset Filter
                    </a>
                @endif
                <a href="{{ route('payroll.laporan', ['periode' => $periode]) }}" class="btn btn-outline-secondary">
                    <i class="bi bi-file-earmark-bar-graph"></i> Laporan
                </a>
            </div>
        </div>
        <p class="text-secondary small mt-2">
            Pilih periode untuk langsung generate payroll. Tombol generate sudah dihapus karena periode otomatis diproses saat dipilih.
        </p>
    </div>
</div>

<div class="card card-dashboard mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-gear"></i> Konfigurasi Tarif Potongan Payroll</span>
        <small class="text-secondary">Simpan tarif agar generate payroll menggunakan angka terbaru.</small>
    </div>
    <div class="card-body">
        <form action="{{ route('payroll.update-deduction-rates') }}" method="POST">
            @csrf
            <div class="row g-3">
                <div class="col-md-2">
                    <label class="form-label">BPJS Kesehatan (%)</label>
                    <input type="number" step="0.01" min="0" name="bpjs_kesehatan" class="form-control" value="{{ data_get($deductionRates, 'bpjs_kesehatan.nilai', 0.01) * 100 }}" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">BPJS JHT (%)</label>
                    <input type="number" step="0.01" min="0" name="bpjs_jht" class="form-control" value="{{ data_get($deductionRates, 'bpjs_jht.nilai', 0.02) * 100 }}" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">BPJS JP (%)</label>
                    <input type="number" step="0.01" min="0" name="bpjs_jp" class="form-control" value="{{ data_get($deductionRates, 'bpjs_jp.nilai', 0.01) * 100 }}" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">PPh21 (Rp)</label>
                    <input type="number" step="0.01" min="0" name="pph21" class="form-control" value="{{ data_get($deductionRates, 'pph21.nilai', 0) }}" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Alpha / Denda (Rp)</label>
                    <input type="number" step="0.01" min="0" name="alpha" class="form-control" value="{{ data_get($deductionRates, 'alpha.nilai', 0) }}" required>
                </div>
            </div>
            <div class="mt-3">
                <button type="submit" class="btn btn-primary">Simpan Tarif Potongan</button>
            </div>
        </form>
    </div>
</div>

<div class="card card-dashboard">
    <div class="card-header"><i class="bi bi-wallet2"></i> Daftar Penggajian — Periode {{ $periode }}</div>
    <div class="card-body">
        <div class="row g-2 mb-3">
            <div class="col-md-4">
                <div class="border rounded p-3 h-100">
                    <div class="small text-secondary">Total Payroll</div>
                    <div class="fs-5 fw-bold">{{ count($payrolls) }}</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="border rounded p-3 h-100">
                    <div class="small text-secondary">Sudah Dibayar</div>
                    <div class="fs-5 fw-bold text-success">{{ $payrolls->where('status', 'sudah_dibayar')->count() }}</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="border rounded p-3 h-100">
                    <div class="small text-secondary">Belum Dibayar</div>
                    <div class="fs-5 fw-bold text-warning">{{ $payrolls->where('status', 'belum_dibayar')->count() }}</div>
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
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
                        <th class="text-end">Total Potongan</th>
                        <th class="text-end">Gaji Bersih</th>
                        <th class="text-center">Metode</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($payrolls as $p)
                        <tr>
                            <td>{{ $p->employee->nip }}</td>
                            <td>{{ $p->employee->nama }}</td>
                            <td><span class="badge bg-secondary">{{ ucfirst($p->employee->jabatan) }}</span></td>
                            <td class="text-end">Rp {{ number_format($p->gaji_pokok, 0, ',', '.') }}</td>
                            <td class="text-end">Rp {{ number_format($p->total_tunjangan, 0, ',', '.') }}</td>
                            <td class="text-end">Rp {{ number_format($p->uang_lembur, 0, ',', '.') }}</td>
                            <td class="text-end">Rp {{ number_format($p->potongan_bpjs_kesehatan, 0, ',', '.') }}</td>
                            <td class="text-end">Rp {{ number_format($p->potongan_bpjs_jht, 0, ',', '.') }}</td>
                            <td class="text-end">Rp {{ number_format($p->potongan_bpjs_jp, 0, ',', '.') }}</td>
                            <td class="text-end">Rp {{ number_format($p->potongan_pph21, 0, ',', '.') }}</td>
                            <td class="text-end">Rp {{ number_format($p->potongan_alpha, 0, ',', '.') }}</td>
                            <td class="text-end">Rp {{ number_format($p->total_potongan, 0, ',', '.') }}</td>
                            <td class="text-end"><strong>Rp {{ number_format($p->gaji_bersih, 0, ',', '.') }}</strong></td>
                            <td class="text-center">
                                @if ($p->payment_method === 'bank')
                                    <span class="badge bg-info text-dark">Transfer</span>
                                    @if ($p->payment_channel)
                                        <div class="small text-secondary">{{ $p->payment_channel }}</div>
                                    @endif
                                @elseif ($p->payment_method === 'cash')
                                    <span class="badge bg-secondary">Cash</span>
                                @elseif ($p->payment_method === 'other')
                                    <span class="badge bg-light text-dark">Lainnya</span>
                                @else
                                    <span class="small text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if ($p->isSudahDibayar())
                                    <div class="d-flex flex-column gap-1 align-items-center">
                                        <span class="badge bg-success"><i class="bi bi-check-circle"></i> Lunas</span>
                                        <form method="POST" action="{{ route('payroll.update-status', $p) }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="belum_dibayar">
                                            <button type="submit" class="btn btn-sm btn-outline-secondary" title="Klik untuk tandai belum dibayar">
                                                <i class="bi bi-arrow-counterclockwise"></i> Batal
                                            </button>
                                        </form>
                                    </div>
                                @else
                                    <div class="d-flex flex-column gap-1 align-items-start">
                                        <span class="badge bg-warning text-dark"><i class="bi bi-clock-history"></i> Belum</span>
                                        <button type="button"
                                            class="btn btn-sm btn-outline-warning btn-open-payment"
                                            data-bs-toggle="modal"
                                            data-bs-target="#modalPembayaran"
                                            data-payroll-id="{{ $p->id }}"
                                            data-route="{{ route('payroll.update-status', $p) }}"
                                            data-name="{{ $p->employee->nama }}"
                                            data-periode="{{ $p->periode }}"
                                            data-method="{{ $p->employee->default_payment_method ?? '' }}"
                                            data-channel="{{ $p->employee->default_payment_channel ?? '' }}"
                                            data-account="{{ $p->employee->default_payment_account ? '***'.substr($p->employee->default_payment_account, -4) : '' }}"
                                            data-full-account="{{ $p->employee->default_payment_account ?? '' }}"
                                            data-account-url="{{ auth()->user()->isAdmin() && $p->employee->default_payment_account ? route('employee.view-default-account', $p->employee) : '' }}"
                                            data-show-unmask="{{ auth()->user()->isAdmin() && $p->employee->default_payment_account ? '1' : '0' }}">
                                            <i class="bi bi-clock-history"></i> Bayar
                                        </button>
                                    </div>
                                @endif
                            </td>
                            <td class="text-center text-nowrap">
                                <a href="{{ route('payroll.slip', $p) }}" class="btn btn-outline-secondary btn-sm">
                                    <i class="bi bi-receipt"></i>
                                </a>
                                <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalEditPayroll{{ $p->id }}">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#modalHapusPayroll{{ $p->id }}">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>

                        {{-- Modal Edit Payroll: gaji bersih dihitung ulang live via JS,
                             tapi nilai FINAL tetap dihitung ulang di server (lihat
                             PayrollController::update) supaya tidak bisa dimanipulasi. --}}
                        <div class="modal fade" id="modalEditPayroll{{ $p->id }}" tabindex="-1">
                            <div class="modal-dialog">
                                <form method="POST" action="{{ route('payroll.update', $p) }}" class="form-slip" data-gaji-pokok="{{ $p->gaji_pokok }}">
                                    @csrf
                                    @method('PUT')
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Edit Slip Gaji — {{ $p->employee->nama }} ({{ $p->periode }})</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="alert alert-secondary py-2">
                                                Gaji Pokok (tetap): <strong>Rp {{ number_format($p->gaji_pokok, 0, ',', '.') }}</strong>
                                                — diubah lewat <em>Data Karyawan</em>, bukan di sini.
                                            </div>
                                            <div class="row g-3">
                                                <div class="col-md-4">
                                                    <label class="form-label">Tunjangan</label>
                                                    <input type="number" step="0.01" min="0" name="total_tunjangan" class="form-control field-slip" value="{{ $p->total_tunjangan }}" required>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">Uang Lembur</label>
                                                    <input type="number" step="0.01" min="0" name="uang_lembur" class="form-control field-slip" value="{{ $p->uang_lembur }}" required>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">Potongan BPJS Kes</label>
                                                    <input type="number" step="0.01" min="0" name="potongan_bpjs_kesehatan" class="form-control field-slip" value="{{ $p->potongan_bpjs_kesehatan }}" required>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">Potongan BPJS JHT</label>
                                                    <input type="number" step="0.01" min="0" name="potongan_bpjs_jht" class="form-control field-slip" value="{{ $p->potongan_bpjs_jht }}" required>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">Potongan BPJS JP</label>
                                                    <input type="number" step="0.01" min="0" name="potongan_bpjs_jp" class="form-control field-slip" value="{{ $p->potongan_bpjs_jp }}" required>
                                                </div>
                                            </div>
                                            <div class="row g-3 mt-3">
                                                <div class="col-md-4">
                                                    <label class="form-label">PPh21</label>
                                                    <input type="number" step="0.01" min="0" name="potongan_pph21" class="form-control field-slip" value="{{ $p->potongan_pph21 }}" required>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">Alpha</label>
                                                    <input type="number" step="0.01" min="0" name="potongan_alpha" class="form-control field-slip" value="{{ $p->potongan_alpha }}" required>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">Potongan Lain</label>
                                                    <input type="number" step="0.01" min="0" name="potongan_lain" class="form-control field-slip" value="{{ $p->potongan_lain }}" required>
                                                </div>
                                            </div>
                                            <hr>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <strong>Perkiraan Gaji Bersih</strong>
                                                <strong class="fs-5 text-primary preview-gaji-bersih">Rp {{ number_format($p->gaji_bersih, 0, ',', '.') }}</strong>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                            <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>

                        {{-- Modal Hapus Payroll --}}
                        <div class="modal fade" id="modalHapusPayroll{{ $p->id }}" tabindex="-1">
                            <div class="modal-dialog">
                                <form method="POST" action="{{ route('payroll.destroy', $p) }}">
                                    @csrf
                                    @method('DELETE')
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Hapus Slip Gaji</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            Yakin ingin menghapus slip gaji <strong>{{ $p->employee->nama }}</strong> periode {{ $p->periode }}?
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                            <button type="submit" class="btn btn-danger">Ya, Hapus</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @empty
                        <tr><td colspan="16" class="text-secondary text-center">Belum ada payroll untuk periode {{ $periode }}. Pilih periode di atas untuk langsung menghasilkan payroll.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

            <!-- Modal Pembayaran (single reusable) -->
            <div class="modal fade" id="modalPembayaran" tabindex="-1">
                <div class="modal-dialog">
                    <form method="POST" id="formModalPembayaran">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="status" value="sudah_dibayar">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="modalPembayaranTitle">Pembayaran</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-2">
                                    <label class="form-label">Metode Pembayaran</label>
                                    <select name="payment_method" class="form-control payment-method-select" id="paymentMethodSelect" data-target="#bankFieldsModal">
                                        <option value="bank">Bank / Transfer</option>
                                        <option value="cash">Cash</option>
                                        <option value="other">Lainnya</option>
                                    </select>
                                </div>
                                <div id="bankFieldsModal" class="bank-fields" style="display:none;">
                                    <div class="mb-2">
                                        <label class="form-label">Nama Bank</label>
                                        <input name="payment_channel" class="form-control" id="paymentChannelInput">
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label">No. Rekening</label>
                                        <div class="input-group">
                                            <input name="payment_account" class="form-control payment-account-input" id="paymentAccountInput">
                                            <button type="button" class="btn btn-outline-secondary btn-sm btn-unmask-default" id="btnUnmaskDefault" style="display:none;">Tampilkan</button>
                                        </div>
                                    </div>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label">Keterangan / No. Bukti (opsional)</label>
                                    <input name="payment_note" class="form-control" id="paymentNoteInput">
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                <button type="submit" class="btn btn-primary">Simpan & Tandai Dibayar</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
// Live preview gaji bersih di setiap modal edit slip gaji.
// Ini HANYA preview di sisi klien; nilai final selalu dihitung ulang
// di server (PayrollController::update) supaya tidak bisa dimanipulasi.
document.querySelectorAll('.form-slip').forEach(function (form) {
    const gajiPokok = parseFloat(form.dataset.gajiPokok) || 0;
    const preview = form.querySelector('.preview-gaji-bersih');

    function formatRupiah(n) {
        return 'Rp ' + Math.max(n, 0).toLocaleString('id-ID', { maximumFractionDigits: 0 });
    }

    function recalc() {
        const val = (name) => parseFloat(form.querySelector(`[name="${name}"]`).value) || 0;
        const totalPotongan = val('potongan_bpjs_kesehatan')
            + val('potongan_bpjs_jht')
            + val('potongan_bpjs_jp')
            + val('potongan_pph21')
            + val('potongan_alpha')
            + val('potongan_lain');

        const gajiBersih = gajiPokok + val('total_tunjangan') + val('uang_lembur')
            - totalPotongan;
        preview.textContent = formatRupiah(gajiBersih);
    }

    form.querySelectorAll('.field-slip').forEach(function (input) {
        input.addEventListener('input', recalc);
    });
});

// Toggle bank fields in reusable payment modal
document.querySelectorAll('.payment-method-select').forEach(function (sel) {
    function toggle() {
        var target = document.querySelector(sel.dataset.target);
        if (!target) return;
        if (sel.value === 'bank') target.style.display = '';
        else target.style.display = 'none';
    }
    sel.addEventListener('change', toggle);
    // initial
    toggle();
});

// Reusable payment modal population
var paymentModalEl = document.getElementById('modalPembayaran');
var paymentForm = document.getElementById('formModalPembayaran');
var paymentTitle = document.getElementById('modalPembayaranTitle');
var paymentMethodSelect = document.getElementById('paymentMethodSelect');
var paymentChannelInput = document.getElementById('paymentChannelInput');
var paymentAccountInput = document.getElementById('paymentAccountInput');
var paymentNoteInput = document.getElementById('paymentNoteInput');
var btnUnmaskDefault = document.getElementById('btnUnmaskDefault');
var paymentModalAccountUrl = null;
var paymentModalFullAccount = '';

if (paymentModalEl && paymentForm) {
    paymentModalEl.addEventListener('show.bs.modal', function () {
        var trigger = document.activeElement;
        if (!trigger || !trigger.classList.contains('btn-open-payment')) return;

        paymentForm.action = trigger.dataset.route;
        paymentTitle.textContent = 'Pembayaran — ' + trigger.dataset.name + ' (' + trigger.dataset.periode + ')';
        paymentMethodSelect.value = trigger.dataset.method || 'bank';

        paymentChannelInput.value = trigger.dataset.channel || '';
        paymentAccountInput.value = trigger.dataset.account || '';
        paymentAccountInput.dataset.full = trigger.dataset.fullAccount || '';
        paymentModalFullAccount = trigger.dataset.fullAccount || '';
        paymentModalAccountUrl = trigger.dataset.accountUrl || '';
        paymentNoteInput.value = '';

        if (btnUnmaskDefault) {
            btnUnmaskDefault.style.display = trigger.dataset.showUnmask === '1' ? '' : 'none';
            btnUnmaskDefault.dataset.url = paymentModalAccountUrl;
            btnUnmaskDefault.textContent = 'Tampilkan';
        }

        // toggle bank fields based on selected method
        if (paymentMethodSelect.value === 'bank') {
            document.getElementById('bankFieldsModal').style.display = '';
        } else {
            document.getElementById('bankFieldsModal').style.display = 'none';
        }
    });
}

if (btnUnmaskDefault) {
    btnUnmaskDefault.addEventListener('click', function () {
        var full = paymentModalFullAccount;
        if (!full) return;

        if (paymentAccountInput.value.startsWith('***')) {
            paymentAccountInput.value = full;
            btnUnmaskDefault.textContent = 'Sembunyikan';

            if (paymentModalAccountUrl) {
                fetch(paymentModalAccountUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({})
                }).catch(function (err) {
                    console.warn('Audit view default failed', err);
                });
            }
        } else {
            paymentAccountInput.value = '***' + full.slice(-4);
            btnUnmaskDefault.textContent = 'Tampilkan';
        }
    });
}

</script>

@endsection
