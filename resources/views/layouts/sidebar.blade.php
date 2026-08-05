<div class="sidebar">
    <div class="text-center py-4">
        <h4 class="text-white mb-0">
            <i class="bi bi-cash-stack"></i>
            Payroll
        </h4>
        <small class="text-secondary">Sistem Informasi Penggajian</small>
    </div>
    <hr class="text-secondary">

    <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">
        <i class="bi bi-house-door"></i>
        Beranda
    </a>

    @if(auth()->user()->isAdmin())
        <a href="{{ route('employee.jabatan.index') }}" class="{{ request()->routeIs('employee.jabatan.index') ? 'active' : '' }}">
            <i class="bi bi-diagram-3"></i>
            Data Jabatan
        </a>

        <a href="{{ route('employee.index') }}" class="{{ request()->routeIs('employee.index') ? 'active' : '' }}">
            <i class="bi bi-people"></i>
            Data Karyawan
        </a>

        <a href="{{ route('attendance.index') }}" class="{{ request()->routeIs('attendance.*') ? 'active' : '' }}">
            <i class="bi bi-calendar-check"></i>
            Pantauan Kehadiran
        </a>

        <a href="{{ route('payroll.index') }}" class="{{ request()->routeIs('payroll.index') || request()->routeIs('payroll.slip') ? 'active' : '' }}">
            <i class="bi bi-wallet2"></i>
            Data Penggajian
        </a>

        <a href="{{ route('payroll.laporan') }}" class="{{ request()->routeIs('payroll.laporan') ? 'active' : '' }}">
            <i class="bi bi-file-earmark-bar-graph"></i>
            Laporan
        </a>
    @endif

    @if(auth()->user()->isKaryawan())
        <a href="{{ route('karyawan.kehadiran') }}" class="{{ request()->routeIs('karyawan.kehadiran*') ? 'active' : '' }}">
            <i class="bi bi-calendar-check"></i>
            Kehadiran Saya
        </a>

        <a href="{{ route('karyawan.slip-saya') }}" class="{{ request()->routeIs('karyawan.slip-saya') || request()->routeIs('payroll.slip') ? 'active' : '' }}">
            <i class="bi bi-receipt"></i>
            Slip Gaji Saya
        </a>
    @endif

    <form action="{{ route('logout') }}" method="POST" class="px-3 mt-4">
        @csrf
        <button class="btn btn-danger w-100">
            <i class="bi bi-box-arrow-right"></i>
            Logout
        </button>
    </form>
</div>
