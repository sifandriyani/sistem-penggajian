<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label">Tanggal</label>
        <input type="date" name="tanggal" class="form-control" value="{{ old('tanggal', now()->format('Y-m-d')) }}" min="{{ now()->format('Y-m-d') }}" max="{{ now()->format('Y-m-d') }}" required>
    </div>
    <div class="col-md-6">
        <label class="form-label">Status</label>
        <select id="attendance-status" name="status" class="form-select" required>
            @foreach (['hadir' => 'Hadir', 'izin' => 'Izin', 'sakit' => 'Sakit'] as $value => $label)
                <option value="{{ $value }}" @selected(old('status', 'hadir') == $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-12 attendance-field attendance-field--izin {{ old('status', 'hadir') === 'izin' ? '' : 'd-none' }}">
        <label class="form-label">Keterangan Izin</label>
        <textarea name="keterangan" class="form-control" rows="3">{{ old('keterangan') }}</textarea>
        <div class="form-text">Jelaskan alasan izin singkatnya, misalnya urusan keluarga atau keperluan penting.</div>
    </div>
    <div class="col-md-12 attendance-field attendance-field--sakit {{ old('status', 'hadir') === 'sakit' ? '' : 'd-none' }}">
        <label class="form-label">Bukti Sakit</label>
        <input type="file" name="bukti" class="form-control">
        <div class="form-text">Unggah foto atau file bukti sakit, misalnya surat dokter atau catatan kesehatan.</div>
    </div>
    <div class="col-md-12">
        <label class="form-label">Jam Lembur (hari ini)</label>
        <input type="number" step="0.5" min="0" max="24" name="jam_lembur" class="form-control" value="{{ old('jam_lembur', 0) }}" required>
    </div>
</div>
<p class="text-secondary small mt-2 mb-0">
    Isi jam lembur 0 jika kamu tidak lembur pada hari tersebut. Data ini yang dipakai HRD
    saat generate payroll untuk menghitung uang lembur kamu bulan ini.
    <strong>Cek dulu sebelum simpan</strong> — data tidak bisa diubah lagi setelah disimpan.
</p>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const status = document.getElementById('attendance-status');
        const izinField = document.querySelector('.attendance-field--izin');
        const sakitField = document.querySelector('.attendance-field--sakit');

        function toggleAttendanceFields() {
            const value = status.value;
            izinField.classList.toggle('d-none', value !== 'izin');
            sakitField.classList.toggle('d-none', value !== 'sakit');
        }

        status.addEventListener('change', toggleAttendanceFields);
        toggleAttendanceFields();
    });
</script>
