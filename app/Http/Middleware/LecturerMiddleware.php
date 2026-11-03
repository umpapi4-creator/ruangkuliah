<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LecturerMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->isLecturer(), 403, 'Halaman ini hanya dapat diakses dosen.');

        return $next($request);
    }
}
