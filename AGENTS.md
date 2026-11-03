# AGENTS.md

## Project Overview

Sistem Penjadwalan Kuliah adalah aplikasi Laravel untuk pengelolaan ruang kelas, booking penggunaan ruang, jadwal kuliah tetap, dan administrasi pengguna.

## Technology Stack

- PHP `^8.1`.
- Laravel `^10.10` (lockfile saat ini: `v10.50.3`).
- Runtime/dependency manager: Composer 2.
- Database: MySQL/MariaDB melalui konfigurasi `DB_CONNECTION` (default `mysql`).
- Frontend: Blade templates dan CSS statis `public/css/app.css`; tidak ditemukan `package.json`, Vite, webpack, atau pipeline build frontend.
- Testing: PHPUnit `10.5.65` melalui Laravel test runner.

## Architecture

Web route -> middleware (`auth`, `admin`, `student`) -> controller -> service/model -> MySQL -> Blade view.

Autentikasi menggunakan session guard Eloquent terhadap `App\Models\User`. Otorisasi role memakai middleware `admin` dan `student`; role yang ditemukan adalah `admin` dan `student`. Domain utama mencakup `User`, `Room`, `Booking`, `PrioritySchedule`, `CourseSchedule`, `RoomBlock`, dan `AdminActivityLog`. Service penting untuk ketersediaan jadwal, impor sederhana, laporan PDF, dan audit admin berada di `app/Services/`.

## Important Project Rules

1. Jangan upgrade framework atau runtime/bahasa pemrograman tanpa diminta.
2. Jangan mengubah fitur yang tidak berkaitan dengan request dan jangan menghapus fitur lama yang masih digunakan.
3. Jangan mengubah `.env` tanpa instruksi.
4. Jangan edit `vendor/` atau `node_modules/`.
5. Jangan menjalankan command destructive tanpa izin, termasuk migrate, operasi database, reset database, dan penghapusan data massal.
6. Sebelum mengubah fitur, periksa seluruh file terkait dan telusuri route/controller/model/database/view/JS/CSS bila relevan.
7. Gunakan struktur, pola coding, dan komponen/service/helper existing; hindari duplikasi table, column, route, controller, atau modul.
8. Lakukan perubahan seminimal mungkin dan pertahankan kompatibilitas dengan versi project sekarang.
9. Jika diminta “jangan ubah yang lain”, bagian yang tidak terkait harus tetap sama.

## Database Rules

- Periksa migration lama, model, relationship, foreign key, nama table, dan nama column sebelum membuat perubahan.
- Jangan membuat duplicate table/column.
- Jangan menghapus data user tanpa izin.
- Gunakan perubahan database yang aman dan minimal.

## UI Rules

- Pertahankan style/layout dan responsive layout yang sudah ada.
- Gunakan komponen existing bila tersedia.
- Jangan mengganti keseluruhan UI untuk perubahan kecil.

## Debugging Rules

- Cari root cause sebelum memperbaiki error; jangan hanya menyembunyikan error.
- Periksa dampak perubahan terhadap fitur lain.
- Gunakan perubahan paling kecil yang menyelesaikan masalah.

## Verification

Command relevan yang aman untuk verifikasi:

```bash
php artisan route:list
php artisan optimize
php artisan test
```

Untuk perubahan database, periksa migration terlebih dahulu dan minta izin sebelum menjalankan migrasi terhadap database berisi data. Instalasi normal menurut `README.md` menggunakan `composer install`, konfigurasi `.env`, `php artisan key:generate`, `php artisan migrate --seed`, lalu `php artisan serve`.
