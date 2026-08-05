<div class="row g-3">
    <div class="col-md-12">
        <label class="form-label">Karyawan</label>
        <select name="employee_id" class="form-select" required>
            @foreach ($employees as $emp)
                <option value="{{ $emp->id }}" @selected(old('employee_id', $a->employee_id ?? '') == $emp->id)>
                    {{ $emp->nama }} ({{ $emp->nip }})
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label">Tanggal</label>
        <input type="date" name="tanggal" class="form-control" value="{{ old('tanggal', isset($a) && $a ? $a->tanggal->format('Y-m-d') : '') }}" required>
    </div>
    <div class="col-md-6">
        <label class="form-label">Status</label>
        <select name="status" class="form-select" required>
            @foreach (['hadir' => 'Hadir', 'izin' => 'Izin', 'sakit' => 'Sakit'] as $value => $label)
                <option value="{{ $value }}" @selected(old('status', $a->status ?? 'hadir') == $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label">Jam Lembur (hari ini)</label>
        <input type="number" step="0.5" min="0" max="24" name="jam_lembur" class="form-control" value="{{ old('jam_lembur', $a->jam_lembur ?? 0) }}" required>
    </div>
</div>
<p class="text-secondary small mt-2 mb-0">
    Isi jam lembur 0 jika karyawan tidak lembur pada hari tersebut.
</p>
