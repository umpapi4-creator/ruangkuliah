<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login · {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/css/app.css">
</head>
<body>
<div class="login-page">
    <section class="login-side">
        <div class="login-box">
            <div class="login-mobile-brand"><span class="site-brand-mark">J</span><strong>Sistem Penjadwalan Kuliah</strong></div>
            <h2>Selamat datang</h2>
            <p>Masuk menggunakan username Anda.</p>

            @if(session('success'))<div class="alert alert-success"><span>✓</span><div>{{ session('success') }}</div></div>@endif
            @if($errors->any())<div class="alert alert-danger"><span>!</span><div>{{ $errors->first() }}</div></div>@endif

            <form action="{{ route('login.store') }}" method="POST">
                @csrf
                <div class="form-group"><label class="form-label" for="identity">Username</label><input class="form-control" id="identity" name="identity" value="{{ old('identity') }}" placeholder="Masukkan username" autofocus required></div>
                <div class="form-group"><label class="form-label" for="password">Password</label><input class="form-control" type="password" id="password" name="password" placeholder="Masukkan password" required></div>
                <label class="check-row" style="margin-bottom:20px"><input type="checkbox" name="remember" value="1"> Ingat saya</label>
                <button class="btn btn-primary btn-block" type="submit">Masuk ke Sistem →</button>
            </form>

            <div class="demo-box login-accounts">
                <strong>Akun demo</strong>
                <div>Admin: <code>admin</code> / <code>123456</code></div>
                <div class="student-account-list">
                    Mahasiswa: <code>mik</code>, <code>d3bidan</code>, <code>s1bidan</code>, <code>d3keperawatan</code>, <code>s1keperawatan</code>, <code>ti</code>, <code>farmasi</code>, <code>s1manajemen</code>, <code>s1hukum</code> / <code>123456</code>
                </div>
            </div>
        </div>
    </section>
</div>
</body>
</html>
