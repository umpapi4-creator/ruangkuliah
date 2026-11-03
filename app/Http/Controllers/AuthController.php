<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'identity' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $key = Str::transliterate(Str::lower($credentials['identity']).'|'.$request->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages(['identity' => "Terlalu banyak percobaan. Coba lagi dalam {$seconds} detik."]);
        }

        $remember = $request->boolean('remember');
        $identity = trim($credentials['identity']);
        $user = User::query()
            ->where('username', $identity)
            ->orWhere('email', $identity)
            ->orWhere('nim', $identity)
            ->first();

        if (! $user || ! Auth::attempt(['id' => $user->id, 'password' => $credentials['password'], 'is_active' => true], $remember)) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['identity' => 'Username, email, NIM, atau password tidak sesuai.']);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'))->with('success', 'Selamat datang, '.Auth::user()->name.'!');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Anda berhasil keluar.');
    }
}
