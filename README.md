# Sistem Penjadwalan Kuliah

Aplikasi penggunaan ruang kelas berbasis **Laravel 10**, **PHP 8.1**, dan **MySQL**. Sistem memiliki dua role: Administrator dan Mahasiswa.

## Fitur

### Administrator

- Dashboard ringkasan ruang, mahasiswa, dan penggunaan ruang.
- Tambah, edit, aktif/nonaktif, dan hapus ruang kelas.
- Mengatur prioritas ruang dan jam untuk akun kelas/program studi tertentu.
- Kelola akun mahasiswa.
- Melihat seluruh jadwal penggunaan ruang.
- Membatalkan penggunaan bila diperlukan.

### Mahasiswa

- Melihat daftar ruang, kapasitas, dan fasilitas.
- Melihat jadwal penggunaan semua ruang berdasarkan tanggal.
- Melihat riwayat kelas yang telah selesai atau dibatalkan berdasarkan tanggal dan ruang.
- Menggunakan ruang untuk mata kuliah/kegiatan.
- Melihat jadwal sendiri dan membatalkan penggunaan yang belum dimulai.

### Pengamanan bentrok

- Ruang yang telah terpakai tidak dapat digunakan lagi pada waktu yang beririsan.
- Mahasiswa tidak dapat memiliki dua jadwal penggunaan yang waktunya beririsan.
- Booking baru hanya dapat dibuat untuk hari ini atau maksimal satu hari ke depan (H+1).
- Prioritas prodi berlaku sampai pukul 00.00 pada tanggal jadwal; setelah itu slot yang belum dibooking terbuka untuk akun lain.
- Baris ruang dikunci selama proses penyimpanan sehingga permintaan serentak tetap diproses dengan aman.
- Penggunaan yang dibatalkan langsung membuat jadwal ruang tersedia kembali.
- Jadwal otomatis hilang dari daftar ruang terpakai setelah jam selesai.

## Persyaratan

- PHP 8.1 atau lebih baru (disarankan PHP 8.1.x)
- Composer 2
- MySQL/MariaDB
- Laragon 6 dapat digunakan

## Instalasi di Laragon

1. Ekstrak folder `Sistem_Penjadwalan_Kuliah` ke `C:\laragon\www`.
2. Jalankan Laragon, lalu aktifkan Apache dan MySQL.
3. Buat database baru bernama `penjadwalan_kuliah` melalui HeidiSQL/phpMyAdmin.
4. Buka Terminal Laragon pada folder proyek, lalu jalankan:

```bash
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

5. Buka `http://127.0.0.1:8000`.

Jika menggunakan domain otomatis Laragon, klik **Menu > www > Sistem_Penjadwalan_Kuliah** setelah `composer install`, pembuatan `.env`, dan migrasi selesai.

### Jika Composer menampilkan security advisories

File `composer.json` sudah dikonfigurasi untuk mengabaikan lima advisory Laravel 10 yang diketahui agar tetap kompatibel dengan PHP 8.1. Pemeriksaan advisory lain tetap aktif. Jalankan kembali:

```bash
composer install
```

## Pengaturan database

Konfigurasi bawaan `.env.example`:

```env
DB_DATABASE=penjadwalan_kuliah
DB_USERNAME=root
DB_PASSWORD=
```

Ubah bagian tersebut pada file `.env` bila username atau password MySQL berbeda.

## Akun login

| Role | Login | Password |
| --- | --- | --- |
| Administrator | `admin` | `123456` |
| Mahasiswa | `mahasiswa` | `123456` |
| S1 Teknologi Informasi | `ti` | `123456` |
| S1 Kebidanan | `s1kebidanan` | `123456` |
| S1 Keperawatan | `s1keperawatan` | `123456` |
| S1 Farmasi | `s1farmasi` | `123456` |
| D3 Kebidanan | `d3kebidanan` | `123456` |
| D3 Keperawatan | `d3keperawatan` | `123456` |
| D4 Manajemen Informasi Kesehatan | `d4mik` | `123456` |

Segera ubah akun dan password demo sebelum aplikasi dipakai secara nyata.

## Menjalankan pengujian

```bash
php artisan test
```

## Catatan

- Zona waktu aplikasi disetel ke `Asia/Jakarta`.
- Folder `vendor` tidak disertakan agar ZIP tetap kecil; folder ini dibuat otomatis oleh `composer install`.
- Jangan menghapus ruang yang sudah memiliki riwayat penggunaan. Nonaktifkan ruang tersebut melalui menu **Kelola Kelas**.

## Tambahan fitur September 2026 (tanpa menghapus fitur lama)

Setelah memperbarui ZIP pada instalasi yang **sudah memiliki database**, jalankan:

```bash
composer install
php artisan migrate
php artisan optimize:clear
```

**Jangan gunakan `migrate:fresh` pada database yang sudah berisi data**, karena menghapus seluruh tabel. Untuk instalasi baru, ikuti instruksi instalasi normal di atas (`migrate --seed`).

Menu baru:

- **Import Excel Jadwal (Admin)**: unduh template `.xlsx`, isi 9 kolom `prodi`, `mata kuliah`, `dosen`, `ruangan`, `hari`, `jam mulai`, `jam selesai`, `tanggal mulai`, `tanggal berakhir`, lalu unggah. Mendukung `.xlsx` dan `.csv`; format `.xls` lama harus disimpan ulang sebagai `.xlsx`. Maksimal 5 MB, 500 baris, rentang 366 hari per baris. Ruangan harus didaftarkan dahulu di **Kelola Kelas**; kolom ruangan diisi kode (mis. `R-01`) atau nama ruang yang persis. Hari Senin–Sabtu. Tanggal bisa `YYYY-MM-DD`, `DD/MM/YYYY`, atau tanggal Excel. Baris salah/bentrok otomatis ditolak dengan alasan per baris; baris valid tetap dimasukkan. Jadwal tetap adalah **berulang setiap minggu** selama tanggal mulai–akhir, bukan booking pribadi. Hapus dari daftar jadwal tetap untuk melepas slot.
- **Kalender Mingguan (semua akun)**: Senin–Sabtu, filter minggu/ruangan, penanda booking, jadwal kuliah tetap, prioritas, blokir, dan slot tanpa kegiatan.
- **Blokir Ruangan (Admin)**: alasan, tanggal dan jam mulai/akhir; semua akun dicegah membuat booking baru pada jam tersebut. Blokir tidak menghapus booking/jadwal lama secara otomatis; periksa jadwal terdampak dan batalkan jika diperlukan. Blokir dapat dibuka kembali melalui daftar.
- **Laporan Pemakaian (Admin)**: filter periode maksimal 366 hari, jumlah booking tercatat dan pertemuan jadwal tetap menurut ruangan, prodi/kelas, hari dan jam terpadat; unduh `.xlsx` dan `.pdf`. *Jadwal tetap dihitung sebagai rencana pertemuan, bukan bukti bahwa kelas benar-benar berlangsung.*
- **Log Aktivitas (Admin)**: audit siapa membuat/memperbarui/menghapus prioritas, membatalkan booking, mengubah pengguna/ruang, impor, blokir serta ekspor laporan, berikut waktu, target, dan perubahan non-rahasia. Password tidak dimasukkan log.

Pemeriksaan bentrok jadwal hasil impor terhadap jadwal tetap lain, booking aktif, prioritas, dan blokir berjalan saat impor; proses booking serta membuat prioritas juga menolak konflik dengan jadwal tetap dan blokir. Aturan lama mahasiswa untuk booking hari ini atau besok (H+1) **tetap berlaku**; impor oleh admin tidak dibatasi H+1.

**Catatan PHP Laragon:** aktifkan ekstensi `zip`, `mbstring`, dan `simplexml` di `php.ini` (jika belum aktif), lalu restart Apache. Fitur Excel/PDF baru tidak membutuhkan package Composer tambahan.

Satu contoh file CSV yang dapat dibuka di Excel disertakan di `contoh/CONTOH_IMPORT_JADWAL.csv`; template `.xlsx` juga dapat diunduh langsung dari halaman **Import Excel Jadwal**. Contoh menggunakan kode ruang `R-101` yang tersedia pada seed awal. Sesuaikan kode ruangan dan tanggal dengan data kampus sebenarnya sebelum mengimpor.
