<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\CourseSchedule;
use App\Models\PrioritySchedule;
use App\Models\Room;
use App\Models\RoomBlock;
use Carbon\Carbon;

class ScheduleAvailability
{
    public static function blockFor(int $roomId, Carbon $start, Carbon $end): ?RoomBlock
    {
        return RoomBlock::active()->where('room_id', $roomId)->overlapping($start, $end)->first();
    }

    public static function recurringFor(int $roomId, Carbon $start, Carbon $end): ?CourseSchedule
    {
        // Booking standar tidak melewati hari; jadwal reguler juga selalu selesai pada hari yang sama.
        return CourseSchedule::where('room_id', $roomId)
            ->where('weekday', $start->dayOfWeekIso)
            ->whereDate('start_date', '<=', $start->toDateString())
            ->whereDate('end_date', '>=', $start->toDateString())
            ->where('start_time', '<', $end->format('H:i:s'))
            ->where('end_time', '>', $start->format('H:i:s'))
            ->first();
    }

    /** Return the first conflict for an imported weekly recurring slot. */
    public static function importConflict(array $item, Room $room): ?string
    {
        if (! $room->is_active) {
            return 'Ruangan tidak aktif.';
        }
        $from = Carbon::parse($item['start_date'])->startOfDay();
        $until = Carbon::parse($item['end_date'])->endOfDay();
        $weekday = (int) $item['weekday'];
        $startTime = $item['start_time'].':00';
        $endTime = $item['end_time'].':00';

        $others = CourseSchedule::where('room_id', $room->id)
            ->where('weekday', $weekday)
            ->whereDate('start_date', '<=', $item['end_date'])
            ->whereDate('end_date', '>=', $item['start_date'])
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime)
            ->get();
        foreach ($others as $other) {
            $intersectionStart = $from->copy()->max($other->start_date);
            $intersectionEnd = $until->copy()->min($other->end_date);
            if (self::firstWeekday($intersectionStart, $intersectionEnd, $weekday)) {
                return 'Bertabrakan dengan jadwal tetap '.$other->course_name.' ('.$other->prodi.').';
            }
        }

        $bookings = Booking::active()->where('room_id', $room->id)
            ->where('start_at', '<', $until)->where('end_at', '>', $from)
            ->whereTime('start_at', '<', $endTime)->whereTime('end_at', '>', $startTime)
            ->get();
        foreach ($bookings as $booking) {
            if ($booking->start_at->dayOfWeekIso === $weekday) {
                return 'Bertabrakan dengan booking pada '.$booking->start_at->format('d/m/Y H:i').'.';
            }
        }

        $priorities = PrioritySchedule::active()->where('room_id', $room->id)
            ->whereDate('schedule_date', '>=', $item['start_date'])
            ->whereDate('schedule_date', '<=', $item['end_date'])
            ->where('start_time', '<', $endTime)->where('end_time', '>', $startTime)->get();
        foreach ($priorities as $priority) {
            if ($priority->schedule_date->dayOfWeekIso === $weekday) {
                return 'Bertabrakan dengan prioritas pada '.$priority->schedule_date->format('d/m/Y').'.';
            }
        }

        $blocks = RoomBlock::active()->where('room_id', $room->id)
            ->where('start_at', '<', $until)->where('end_at', '>', $from)->get();
        foreach ($blocks as $block) {
            $date = self::firstWeekday($from->copy()->max($block->start_at->copy()->startOfDay()),
                $until->copy()->min($block->end_at->copy()->startOfDay()), $weekday);
            while ($date && $date->lte($until) && $date->lte($block->end_at)) {
                $start = Carbon::parse($date->toDateString().' '.$item['start_time']);
                $end = Carbon::parse($date->toDateString().' '.$item['end_time']);
                if ($block->start_at->lt($end) && $block->end_at->gt($start)) {
                    return 'Ruang diblokir ('.$block->reason.') pada '.$date->format('d/m/Y').'.';
                }
                $date->addWeek();
            }
        }
        return null;
    }

    public static function firstWeekday(Carbon $from, Carbon $until, int $weekday): ?Carbon
    {
        $date = $from->copy()->startOfDay();
        $date->addDays(($weekday - $date->dayOfWeekIso + 7) % 7);
        return $date->lte($until) ? $date : null;
    }
}
