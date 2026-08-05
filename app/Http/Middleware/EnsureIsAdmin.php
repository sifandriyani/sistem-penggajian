<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * EnsureIsAdmin
 *
 * Melindungi route yang hanya boleh diakses role 'admin' (HRD), seperti
 * kelola data karyawan, generate payroll, dan laporan agregat.
 * Karyawan biasa yang mencoba mengakses akan mendapat 403.
 */
class EnsureIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || ! $request->user()->isAdmin()) {
            abort(403, 'Halaman ini hanya untuk Admin (HRD).');
        }

        return $next($request);
    }
}
