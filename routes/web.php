<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PriorityScheduleController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WeeklyCalendarController;
use App\Http\Controllers\ImportScheduleController;
use App\Http\Controllers\RoomBlockController;
use App\Http\Controllers\RoomReportController;
use App\Http\Controllers\AdminAuditController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/jadwal', [BookingController::class, 'schedule'])->name('schedule');
    Route::get('/kalender-mingguan', [WeeklyCalendarController::class, 'index'])->name('calendar.weekly');
    Route::get('/ruang-kelas', [RoomController::class, 'publicIndex'])->name('rooms.index');
    Route::get('/ruang-terpakai', [BookingController::class, 'index'])->name('bookings.index');
    Route::patch('/ruang-terpakai/{booking}/batalkan', [BookingController::class, 'cancel'])->name('bookings.cancel');

    Route::middleware('student')->group(function () {
        Route::get('/gunakan-ruang', [BookingController::class, 'create'])->name('bookings.create');
        Route::post('/ruang-terpakai', [BookingController::class, 'store'])->name('bookings.store');
    });

    Route::prefix('dosen')->name('lecturer.')->middleware('lecturer')->group(function () {
        Route::get('jadwal', [ImportScheduleController::class, 'lecturerIndex'])->name('schedules.index');
        Route::get('jadwal/tambah', [ImportScheduleController::class, 'create'])->name('schedules.create');
        Route::post('jadwal', [ImportScheduleController::class, 'manualStore'])->name('schedules.store');
        Route::delete('jadwal/{schedule}', [ImportScheduleController::class, 'lecturerDestroy'])->name('schedules.destroy');
    });

    Route::prefix('admin')->name('admin.')->middleware('admin')->group(function () {
        Route::resource('rooms', RoomController::class)->except('show');
        Route::resource('users', UserController::class)->except(['show', 'destroy']);
        Route::resource('priorities', PriorityScheduleController::class)->except('show');
        Route::get('import-jadwal', [ImportScheduleController::class, 'index'])->name('imports.index');
        Route::get('import-jadwal/template', [ImportScheduleController::class, 'template'])->name('imports.template');
        Route::post('import-jadwal', [ImportScheduleController::class, 'store'])->name('imports.store');
        Route::get('jadwal-tetap/tambah', [ImportScheduleController::class, 'create'])->name('schedules.create');
        Route::post('jadwal-tetap', [ImportScheduleController::class, 'manualStore'])->name('schedules.store');
        Route::delete('jadwal-tetap/{schedule}', [ImportScheduleController::class, 'destroy'])->name('imports.destroy');
        Route::get('blokir-ruangan', [RoomBlockController::class, 'index'])->name('blocks.index');
        Route::post('blokir-ruangan', [RoomBlockController::class, 'store'])->name('blocks.store');
        Route::patch('blokir-ruangan/{block}', [RoomBlockController::class, 'update'])->name('blocks.update');
        Route::get('laporan-pemakaian', [RoomReportController::class, 'index'])->name('reports.index');
        Route::get('laporan-pemakaian/excel', [RoomReportController::class, 'excel'])->name('reports.excel');
        Route::get('laporan-pemakaian/pdf', [RoomReportController::class, 'pdf'])->name('reports.pdf');
        Route::get('log-aktivitas', [AdminAuditController::class, 'index'])->name('audits.index');
    });
});
