@extends('layouts.app')

@section('title', 'Data Karyawan')

@section('content')

@if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

@if (session('kredensial_baru'))
    @php $kredensial = session('kredensial_baru'); @endphp
    <div class="modal fade show" id="modalKredensial" tabindex="-1" style="display:block; background:rgba(0,0,0,0.5);">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="bi bi-check-circle"></i> Akun Login Karyawan Baru</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" onclick="document.getElementById('modalKredensial').remove()"></button>
                </div>
                <div class="modal-body">
                    <p>Akun login untuk <strong>{{ $kredensial['nama'] }}</strong> berhasil dibuat dengan password yang sudah kamu tentukan. Sampaikan email login berikut ke karyawan bersangkutan:</p>
                    <table class="table table-bordered mb-0">
                        <tr><th style="width:120px">Email</th><td><code>{{ $kredensial['email'] }}</code></td></tr>
                    </table>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" onclick="document.getElementById('modalKredensial').remove()">Sudah Dicatat</button>
                </div>
            </div>
        </div>
    </div>
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
        <span><i class="bi bi-people"></i> Data Karyawan</span>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalTambah">
            <i class="bi bi-plus-lg"></i> Tambah Karyawan
        </button>
    </div>
    <div class="card-body">
        <form method="GET" class="mb-3" action="{{ route('employee.index') }}">
            <div class="input-group">
                <input type="text" class="form-control" placeholder="Cari nama karyawan..." name="search" value="{{ request('search') }}">
                <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
                @if(request('search'))
                    <a href="{{ route('employee.index') }}" class="btn btn-outline-secondary"><i class="bi bi-x"></i></a>
                @endif
            </div>
        </form>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>NIP</th>
                        <th>Nama</th>
                        <th>Jabatan</th>
                        <th class="text-end">Gaji Pokok</th>
                        <th class="text-center">Menikah</th>
                        <th class="text-center">Anak</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($employees as $e)
                        <tr>
                            <td>{{ $e->nip }}</td>
                            <td>{{ $e->nama }}</td>
                            <td><span class="badge bg-secondary">{{ ucfirst($e->jabatan) }}</span></td>
                            <td class="text-end">Rp {{ number_format($e->gaji_pokok, 0, ',', '.') }}</td>
                            <td class="text-center">{{ $e->status_menikah ? 'Ya' : 'Tidak' }}</td>
                            <td class="text-center">{{ $e->jumlah_anak }}</td>
                            <td class="text-center text-nowrap">
                                <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalEdit{{ $e->id }}">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#modalHapus{{ $e->id }}">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>

                        {{-- Modal Edit --}}
                        <div class="modal fade" id="modalEdit{{ $e->id }}" tabindex="-1">
                            <div class="modal-dialog">
                                <form method="POST" action="{{ route('employee.update', $e) }}">
                                    @csrf
                                    @method('PUT')
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Edit Karyawan — {{ $e->nama }}</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            @include('payroll.partials.employee-fields', ['e' => $e, 'jabatans' => $jabatans])
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                            <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>

                        {{-- Modal Hapus --}}
                        <div class="modal fade" id="modalHapus{{ $e->id }}" tabindex="-1">
                            <div class="modal-dialog">
                                <form method="POST" action="{{ route('employee.destroy', $e) }}">
                                    @csrf
                                    @method('DELETE')
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Hapus Karyawan</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            Yakin ingin menghapus <strong>{{ $e->nama }}</strong> ({{ $e->nip }})?
                                            Seluruh riwayat slip gajinya juga akan ikut terhapus.
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
                        <tr><td colspan="7" class="text-secondary text-center">Belum ada data karyawan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $employees->links() }}
    </div>
</div>

{{-- Modal Tambah Karyawan --}}
<div class="modal fade" id="modalTambah" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('employee.store') }}">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Karyawan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    @include('payroll.partials.employee-fields', ['e' => null, 'jabatans' => $jabatans])
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
