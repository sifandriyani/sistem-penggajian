@extends('layouts.app')

@section('title', 'Data Jabatan')

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

<div class="card card-dashboard">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-diagram-3"></i> Data Jabatan</span>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalTambahJabatan">
            <i class="bi bi-plus-lg"></i> Tambah Jabatan
        </button>
    </div>
    <div class="card-body">
        <p class="text-secondary small mb-3">Kelola data jabatan dengan keterangan, gaji pokok, dan tunjangan. Data ini akan muncul otomatis saat menambah atau mengedit karyawan.</p>
        <form method="GET" class="mb-3" action="{{ route('employee.jabatan.index') }}">
            <div class="input-group">
                <input type="text" class="form-control" placeholder="Cari jabatan..." name="search" value="{{ request('search') }}">
                <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
            </div>
        </form>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Nama</th>
                        <th>Keterangan</th>
                        <th class="text-end">Gaji Pokok</th>
                        <th class="text-end">Multiplier</th>
                        <th class="text-end">Transport / Hari</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($jabatans as $jabatan)
                        <tr>
                            <td><code>{{ $jabatan->kode }}</code></td>
                            <td>{{ $jabatan->nama }}</td>
                            <td>{{ $jabatan->keterangan ?: '—' }}</td>
                            <td class="text-end">Rp {{ number_format($jabatan->gaji_min, 0, ',', '.') }}</td>
                            <td class="text-end">{{ number_format($jabatan->multiplier, 2) }} ({{ round($jabatan->multiplier * 100) }}%)</td>
                            <td class="text-end">Rp {{ number_format($jabatan->transport_per_hari, 0, ',', '.') }}</td>
                            <td class="text-center text-nowrap">
                                <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalEditJabatan{{ $jabatan->id }}">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#modalHapusJabatan{{ $jabatan->id }}">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>

                        <div class="modal fade" id="modalEditJabatan{{ $jabatan->id }}" tabindex="-1">
                            <div class="modal-dialog">
                                <form method="POST" action="{{ route('employee.jabatan.update', $jabatan) }}">
                                    @csrf
                                    @method('PUT')
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Edit Jabatan — {{ $jabatan->nama }}</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="mb-3">
                                                <label class="form-label">Kode</label>
                                                <input type="text" name="kode" class="form-control" value="{{ old('kode', $jabatan->kode) }}" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Nama Jabatan</label>
                                                <input type="text" name="nama" class="form-control" value="{{ old('nama', $jabatan->nama) }}" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Keterangan</label>
                                                <textarea name="keterangan" class="form-control" rows="3">{{ old('keterangan', $jabatan->keterangan) }}</textarea>
                                            </div>
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <label class="form-label">Gaji Pokok</label>
                                                    <input type="number" step="0.01" min="0" name="gaji_min" class="form-control" value="{{ old('gaji_min', $jabatan->gaji_min) }}" required>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Multiplier Tunjangan</label>
                                                    <input type="number" step="0.01" min="0" max="1" name="multiplier" class="form-control" value="{{ old('multiplier', $jabatan->multiplier) }}" required>
                                                    <small class="text-secondary">Contoh: 0.20 = 20% dari gaji pokok</small>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Transport per Hari</label>
                                                    <input type="number" step="0.01" min="0" name="transport_per_hari" class="form-control" value="{{ old('transport_per_hari', $jabatan->transport_per_hari) }}" required>
                                                </div>
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

                        <div class="modal fade" id="modalHapusJabatan{{ $jabatan->id }}" tabindex="-1">
                            <div class="modal-dialog">
                                <form method="POST" action="{{ route('employee.jabatan.destroy', $jabatan) }}">
                                    @csrf
                                    @method('DELETE')
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Hapus Jabatan</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            Yakin ingin menghapus jabatan <strong>{{ $jabatan->nama }}</strong>?
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
                        <tr><td colspan="7" class="text-secondary text-center">Belum ada data jabatan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Modal Tambah Jabatan --}}
<div class="modal fade" id="modalTambahJabatan" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('employee.jabatan.store') }}">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Jabatan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Kode</label>
                        <input type="text" name="kode" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nama Jabatan</label>
                        <input type="text" name="nama" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Keterangan</label>
                        <textarea name="keterangan" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Gaji Pokok</label>
                            <input type="number" step="0.01" min="0" name="gaji_min" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Multiplier Tunjangan</label>
                            <input type="number" step="0.01" min="0" max="1" name="multiplier" class="form-control" value="0.10" required>
                            <small class="text-secondary">Contoh: 0.20 = 20% dari gaji pokok</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Transport per Hari</label>
                            <input type="number" step="0.01" min="0" name="transport_per_hari" class="form-control" value="0" required>
                        </div>
                    </div>
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
