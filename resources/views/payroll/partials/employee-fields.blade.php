@php
    $uid = optional($e)->id ?? 'baru';
    $jabatanData = [];
    foreach ($jabatans as $jabatan) {
        $jabatanData[$jabatan->kode] = [
            'gaji_pokok' => (int) $jabatan->gaji_min,
            'nama'       => $jabatan->nama,
        ];
    }
    $selectedJabatan = old('jabatan', optional($e)->jabatan ?? ($jabatans->first()->kode ?? 'staff'));
    $initialGaji = old('gaji_pokok', optional($jabatans->firstWhere('kode', $selectedJabatan))->gaji_min ?? 0);
@endphp

<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label">NIP</label>
        <input
            type="text"
            name="nip"
            class="form-control"
            value="{{ old('nip', optional($e)->nip ?? '') }}"
            minlength="6"
            required>
        <small class="text-secondary">Minimal 6 karakter.</small>
    </div>

    <div class="col-md-6">
        <label class="form-label">Nama</label>
        <input
            type="text"
            name="nama"
            class="form-control"
            value="{{ old('nama', optional($e)->nama ?? '') }}"
            minlength="6"
            required>
        <small class="text-secondary">Minimal 6 karakter.</small>
    </div>

    <div class="col-md-6">
        <label class="form-label">Jabatan</label>
        <select name="jabatan" id="jabatan{{ $uid }}" class="form-select" required>
            @foreach ($jabatans as $jabatan)
                <option value="{{ $jabatan->kode }}" @selected(old('jabatan', optional($e)->jabatan ?? 'staff') == $jabatan->kode)>
                    {{ $jabatan->nama }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="col-md-6">
        <label class="form-label">Gaji Pokok</label>
        <input
            type="number"
            step="0.01"
            min="0"
            name="gaji_pokok"
            id="gajiPokok{{ $uid }}"
            class="form-control"
            value="{{ $initialGaji }}"
            readonly
            required>

        <small class="text-secondary" id="hintGaji{{ $uid }}"></small>
    </div>

    <div class="col-md-3">
        <label class="form-label">Jumlah Anak</label>
        <input
            type="number"
            min="0"
            max="10"
            name="jumlah_anak"
            class="form-control"
            value="{{ old('jumlah_anak', optional($e)->jumlah_anak ?? 0) }}">
    </div>

    <div class="col-md-3 d-flex align-items-end">
        <div class="form-check">
            <input type="hidden" name="status_menikah" value="0">

            <input
                type="checkbox"
                name="status_menikah"
                value="1"
                class="form-check-input"
                id="menikah{{ $uid }}"
                @checked(old('status_menikah', optional($e)->status_menikah ?? false))>

            <label class="form-check-label" for="menikah{{ $uid }}">
                Sudah Menikah
            </label>
        </div>
    </div>

    <div class="col-md-6">
        <label class="form-label">
            Password {{ $e ? 'Baru (opsional)' : 'Login' }}
        </label>

        <input
            type="password"
            name="password"
            class="form-control"
            minlength="6"
            placeholder="{{ $e ? 'Kosongkan jika tidak ingin mengubah' : 'Minimal 6 karakter' }}"
            {{ $e ? '' : 'required' }}>
    </div>

    <div class="col-md-6">
        <label class="form-label">Ulangi Password</label>

        <input
            type="password"
            name="password_confirmation"
            class="form-control"
            minlength="6"
            {{ $e ? '' : 'required' }}>
    </div>
</div>

<hr>

<div class="row g-3">
    <div class="col-md-4">
        <label class="form-label">Default Metode Pembayaran</label>
        <select name="default_payment_method" class="form-select">
            <option value="">(Tidak diset)</option>
            <option value="bank" {{ old('default_payment_method', optional($e)->default_payment_method ?? '') == 'bank' ? 'selected' : '' }}>Bank / Transfer</option>
            <option value="cash" {{ old('default_payment_method', optional($e)->default_payment_method ?? '') == 'cash' ? 'selected' : '' }}>Cash</option>
            <option value="other" {{ old('default_payment_method', optional($e)->default_payment_method ?? '') == 'other' ? 'selected' : '' }}>Lainnya</option>
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label">Default Nama Bank</label>
        <input name="default_payment_channel" class="form-control" value="{{ old('default_payment_channel', optional($e)->default_payment_channel ?? '') }}">
    </div>
    <div class="col-md-4">
        <label class="form-label">Default No. Rekening</label>
        <input name="default_payment_account" class="form-control" value="{{ old('default_payment_account', optional($e)->default_payment_account ? ('***'.substr(optional($e)->default_payment_account, -4)) : '') }}">
        <small class="text-secondary">Disimpan terenkripsi. Administrator dapat menampilkan nomor lengkap saat mengelola pembayaran.</small>
    </div>
</div>

<p class="text-secondary small mt-2 mb-0">
    Tunjangan jabatan, tunjangan anak, lembur, dan potongan BPJS dihitung otomatis oleh sistem saat payroll digenerate — tidak diisi manual di sini.

    @if ($e)
        Password baru cuma dipakai kalau field di atas diisi — kosongkan kalau tidak mau mengubah password karyawan ini.
    @else
        Password ini yang akan dipakai karyawan buat login — tentukan sendiri password yang gampang diingat/disampaikan.
    @endif
</p>

<script>
(function () {
    var jabatanData = @json($jabatanData);
    var selectJabatan = document.getElementById('jabatan{{ $uid }}');
    var gajiPokokField = document.getElementById('gajiPokok{{ $uid }}');
    var hint = document.getElementById('hintGaji{{ $uid }}');

    function formatRupiah(n) {
        return 'Rp' + Number(n).toLocaleString('id-ID');
    }

    function tampilkanHint() {
        var data = jabatanData[selectJabatan.value] || {};

        gajiPokokField.value = data.gaji_pokok || 0;
        hint.textContent =
            'Gaji pokok otomatis: ' +
            formatRupiah(data.gaji_pokok || 0) +
            ' sesuai master jabatan.';
    }

    selectJabatan.addEventListener('change', tampilkanHint);
    tampilkanHint();
})();
</script>