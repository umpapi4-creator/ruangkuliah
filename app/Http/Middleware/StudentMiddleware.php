<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StudentMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->canBookRooms(), 403, 'Fitur penggunaan ruang hanya dapat digunakan mahasiswa atau dosen.');

        return $next($request);
    }
}
