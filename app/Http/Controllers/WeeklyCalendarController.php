<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\CourseSchedule;
use App\Models\PrioritySchedule;
use App\Models\Room;
use App\Models\RoomBlock;
use Carbon\Carbon;
use Illuminate\Http\Request;

class WeeklyCalendarController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'week' => ['nullable', 'date_format:Y-m-d'],
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
        ]);
        $monday = Carbon::parse($data['week'] ?? today()->toDateString())->startOfWeek(Carbon::MONDAY);
        $days = collect(range(0, 5))->map(fn ($n) => $monday->copy()->addDays($n));
        $end = $monday->copy()->addDays(6);
        $allRooms = Room::where('is_active', true)->orderForDisplay()->get();
        $rooms = ! empty($data['room_id']) ? $allRooms->where('id', (int) $data['room_id']) : $allRooms;
        $roomIds = $rooms->pluck('id');
        $cells = [];
        foreach ($rooms as $room) {
            foreach ($days as $day) $cells[$room->id][$day->toDateString()] = [];
        }
        $put = function (int $roomId, Carbon $day, array $entry) use (&$cells) {
            $key = $day->toDateString();
            if (isset($cells[$roomId][$key])) $cells[$roomId][$key][] = $entry;
        };

        $schedules = CourseSchedule::whereIn('room_id', $roomIds)->whereDate('start_date', '<', $end)
            ->whereDate('end_date', '>=', $monday)->get();
        foreach ($schedules as $schedule) {
            foreach ($days as $day) {
                if ($day->dayOfWeekIso !== $schedule->weekday || $day->lt($schedule->start_date) || $day->gt($schedule->end_date)) continue;
                $put($schedule->room_id, $day, [
                    'sort' => $schedule->start_time, 'type' => 'regular', 'time' => substr($schedule->start_time, 0, 5).'–'.substr($schedule->end_time, 0, 5),
                    'title' => $schedule->course_name, 'info' => $schedule->prodi.' · '.$schedule->lecturer_name,
                ]);
            }
        }
        $bookings = Booking::with('user')->active()->whereNull('cancelled_at')->where('end_at', '>', now())->whereIn('room_id', $roomIds)
            ->where('start_at', '>=', $monday)->where('start_at', '<', $end)->get();
        foreach ($bookings as $booking) {
            $put($booking->room_id, $booking->start_at, [
                'sort' => $booking->start_at->format('H:i'), 'type' => 'booking',
                'time' => $booking->start_at->format('H:i').'–'.$booking->end_at->format('H:i'),
                'title' => $booking->course_name,
                'info' => ($booking->user?->study_program ?: $booking->class_group).' · '.$booking->user->username,
            ]);
        }
        $priorities = PrioritySchedule::with('user')->active()->whereIn('room_id', $roomIds)
            ->whereDate('schedule_date', '>=', $monday)->whereDate('schedule_date', '<', $end)
            ->whereDate('schedule_date', '>', today())->get();
        foreach ($priorities as $priority) {
            $put($priority->room_id, $priority->schedule_date, [
                'sort' => $priority->start_time, 'type' => 'priority',
                'time' => substr($priority->start_time, 0, 5).'–'.substr($priority->end_time, 0, 5),
                'title' => 'Prioritas', 'info' => ($priority->user->study_program ?: 'Belum ada prodi').' · '.$priority->user->username,
            ]);
        }
        $blocks = RoomBlock::active()->whereIn('room_id', $roomIds)->where('start_at', '<', $end)
            ->where('end_at', '>', $monday)->get();
        foreach ($blocks as $block) {
            foreach ($days as $day) {
                $dayStart = $day->copy()->startOfDay();
                $dayEnd = $day->copy()->addDay();
                if ($block->start_at->gte($dayEnd) || $block->end_at->lte($dayStart)) continue;
                $a = $block->start_at->gt($dayStart) ? $block->start_at : $dayStart;
                $b = $block->end_at->lt($dayEnd) ? $block->end_at : $dayEnd;
                $put($block->room_id, $day, [
                    'sort' => $a->format('H:i'), 'type' => 'blocked',
                    'time' => $a->format('H:i').'–'.$b->format('H:i'),
                    'title' => 'DIBLOKIR: '.$block->reason, 'info' => $block->notes ?: 'Booking ditutup',
                ]);
            }
        }
        foreach ($cells as &$byDay) foreach ($byDay as &$entries) usort($entries, fn ($a, $b) => strcmp($a['sort'], $b['sort']));
        unset($byDay, $entries);
        return view('bookings.weekly', compact('days', 'monday', 'rooms', 'allRooms', 'cells'));
    }
}
