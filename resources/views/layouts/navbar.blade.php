<nav class="navbar navbar-expand-lg navbar-custom">
    <div class="container-fluid">
        <h4 class="mb-0">@yield('title', 'Beranda')</h4>
        <div>
            <span class="text-secondary">
                {{ now()->translatedFormat('d F Y') }}
            </span>
            |
            <strong>{{ auth()->user()->name }}</strong>
            <span class="badge {{ auth()->user()->isAdmin() ? 'bg-primary' : 'bg-secondary' }} ms-1">
                {{ auth()->user()->isAdmin() ? 'Admin/HRD' : 'Karyawan' }}
            </span>
        </div>
    </div>
</nav>
