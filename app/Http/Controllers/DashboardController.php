<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Room;
use App\Models\CourseSchedule;
use App\Models\User;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $todayBookings = Booking::with(['room', 'user'])
            ->active()
            ->whereDate('start_at', today())
            ->orderBy('start_at')
            ->get();

        $todayRegular = CourseSchedule::with('room')->where('weekday', now()->dayOfWeekIso)
            ->whereDate('start_date', '<=', today())->whereDate('end_date', '>=', today())
            ->orderBy('start_time')->get();

        $bookingItems = $todayBookings->map(function (Booking $booking) {
            $theme = $this->prodiTheme(
                implode(' ', array_filter([$booking->user?->study_program, $booking->user?->username, $booking->class_group])),
                $booking->user?->study_program ?: $booking->class_group
            );

            return [
                'course_name' => $booking->course_name,
                'prodi' => $theme['label'],
                'theme' => $theme['class'],
                'room' => $booking->room,
                'lecturer_name' => $booking->lecturer_name,
                'start' => $booking->start_at,
                'end' => $booking->end_at,
                'source' => 'Booking Ruang',
            ];
        });
        $regularItems = $todayRegular->map(function (CourseSchedule $schedule) {
            $start = Carbon::parse(today()->toDateString().' '.$schedule->start_time);
            $end = Carbon::parse(today()->toDateString().' '.$schedule->end_time);
            $theme = $this->prodiTheme($schedule->prodi, $schedule->prodi);

            return [
                'course_name' => $schedule->course_name,
                'prodi' => $theme['label'],
                'theme' => $theme['class'],
                'room' => $schedule->room,
                'lecturer_name' => $schedule->lecturer_name,
                'start' => $start,
                'end' => $end,
                'source' => 'Jadwal Tetap',
            ];
        });
        $todayScheduleItems = $bookingItems->concat($regularItems)
            ->sortBy('start')
            ->values();
        $activeScheduleItems = $todayScheduleItems
            ->filter(fn (array $item) => $item['start']->lte(now()) && $item['end']->gt(now()))
            ->values();

        if ($user->isAdmin()) {
            $stats = [
                'rooms' => Room::where('is_active', true)->count(),
                'users' => User::whereIn('role', [User::ROLE_STUDENT, User::ROLE_LECTURER])->where('is_active', true)->count(),
                'today' => $todayScheduleItems->count(),
                'upcoming' => Booking::active()->where('start_at', '>', now())->count(),
            ];
            $upcoming = Booking::with(['room', 'user'])->active()->where('start_at', '>', now())->orderBy('start_at')->limit(6)->get();
        } else {
            $stats = [
                'rooms' => Room::where('is_active', true)->count(),
                'mine' => Booking::active()->where('user_id', $user->id)->where('start_at', '>', now())->count(),
                'today' => $todayScheduleItems->count(),
                'history' => Booking::where('user_id', $user->id)->count(),
            ];
            $upcoming = Booking::with('room')->active()->where('user_id', $user->id)->where('start_at', '>', now())->orderBy('start_at')->limit(6)->get();
        }

        return view('dashboard', compact('stats', 'todayBookings', 'upcoming', 'todayScheduleItems', 'activeScheduleItems'));
    }

    private function prodiTheme(string $identity, string $fallback): array
    {
        $identity = mb_strtolower($identity);

        $theme = match (true) {
            str_contains($identity, 'mik'), str_contains($identity, 'informasi kesehatan') => ['class' => 'mikes', 'label' => 'D4 MIKES'],
            str_contains($identity, 'keperawatan') && str_contains($identity, 'd3') => ['class' => 'perawat-d3', 'label' => 'D3 Keperawatan'],
            str_contains($identity, 'keperawatan') => ['class' => 'perawat-s1', 'label' => 'S1 Keperawatan'],
            str_contains($identity, 'bidan') && str_contains($identity, 'd3') => ['class' => 'bidan-d3', 'label' => 'D3 Kebidanan'],
            str_contains($identity, 'bidan') => ['class' => 'bidan-s1', 'label' => 'S1 Kebidanan'],
            str_contains($identity, 'teknologi informasi'), preg_match('/(^|\s)ti($|\s)/', $identity) === 1 => ['class' => 'ti', 'label' => 'S1 TI'],
            str_contains($identity, 'farmasi') => ['class' => 'farmasi', 'label' => 'S1 Farmasi'],
            str_contains($identity, 'manajemen') => ['class' => 'manajemen', 'label' => 'S1 Manajemen'],
            str_contains($identity, 'hukum') => ['class' => 'hukum', 'label' => 'S1 Hukum'],
            default => null,
        };

        if ($theme !== null) {
            return $theme;
        }

        $fallbackThemes = ['acak-ungu', 'acak-jingga', 'acak-toska', 'acak-nila', 'acak-zaitun'];
        $themeIndex = (int) sprintf('%u', crc32($identity ?: $fallback)) % count($fallbackThemes);

        return ['class' => $fallbackThemes[$themeIndex], 'label' => $fallback ?: 'Umum'];
    }
}
