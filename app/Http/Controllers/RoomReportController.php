<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\CourseSchedule;
use App\Models\Room;
use App\Models\RoomBlock;
use App\Services\AdminAudit;
use App\Services\ReportPdf;
use App\Services\ScheduleAvailability;
use App\Services\SimpleExcel;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RoomReportController extends Controller
{
    private function build(Request $request): array
    {
        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'until' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $from = Carbon::parse($data['from'] ?? today()->startOfMonth()->toDateString())->startOfDay();
        $until = Carbon::parse($data['until'] ?? today()->toDateString())->startOfDay();
        if ($from->gt($until) || $from->diffInDays($until) > 366) {
            throw ValidationException::withMessages(['until' => 'Rentang laporan maksimal 366 hari dan akhir tidak boleh mendahului awal.']);
        }
        $rooms = Room::orderForDisplay()->get();
        $summary = [];
        foreach ($rooms as $room) {
            $summary[$room->id] = ['room' => $room, 'booking' => 0, 'regular' => 0, 'total' => 0, 'blocks' => 0];
        }
        $prodis = [];
        $dayCounts = array_fill(1, 7, 0);
        $hourCounts = array_fill(0, 24, 0);
        // Count an occurrence exactly once, and each occupied clock-hour as one hour-slot.
        $record = function (int $roomId, string $prodi, Carbon $start, Carbon $end, string $source) use (&$summary, &$prodis, &$dayCounts, &$hourCounts) {
            $summary[$roomId][$source]++;
            $summary[$roomId]['total']++;
            $prodi = trim($prodi) ?: 'Tidak ditentukan';
            $prodis[$prodi] = ($prodis[$prodi] ?? 0) + 1;
            $dayCounts[$start->dayOfWeekIso]++;
            for ($h = 0; $h < 24; $h++) {
                $hStart = $start->copy()->startOfDay()->addHours($h);
                if ($start->lt($hStart->copy()->addHour()) && $end->gt($hStart)) $hourCounts[$h]++;
            }
        };
        $bookings = Booking::with('user')->active()->where('start_at', '>=', $from)
            ->where('start_at', '<', $until->copy()->addDay())->get();
        foreach ($bookings as $booking) {
            $record(
                $booking->room_id,
                $booking->user?->study_program ?: $booking->class_group,
                $booking->start_at,
                $booking->end_at,
                'booking'
            );
        }
        $recurring = CourseSchedule::whereDate('start_date', '<=', $until)->whereDate('end_date', '>=', $from)->get();
        foreach ($recurring as $schedule) {
            $date = ScheduleAvailability::firstWeekday($from->copy()->max($schedule->start_date),
                $until->copy()->min($schedule->end_date), $schedule->weekday);
            while ($date && $date->lte($until) && $date->lte($schedule->end_date)) {
                $start = Carbon::parse($date->toDateString().' '.$schedule->start_time);
                $end = Carbon::parse($date->toDateString().' '.$schedule->end_time);
                $record($schedule->room_id, $schedule->prodi, $start, $end, 'regular');
                $date->addWeek();
            }
        }
        $blocks = RoomBlock::where('start_at', '<', $until->copy()->addDay())
            ->where('end_at', '>', $from)->get();
        foreach ($blocks as $block) if (isset($summary[$block->room_id])) $summary[$block->room_id]['blocks']++;
        uasort($summary, fn ($a, $b) => ($b['total'] <=> $a['total']) ?: strcmp($a['room']->code, $b['room']->code));
        arsort($prodis);
        arsort($dayCounts);
        arsort($hourCounts);
        return compact('from', 'until', 'summary', 'prodis', 'dayCounts', 'hourCounts');
    }

    public function index(Request $request)
    {
        return view('admin.reports.index', $this->build($request));
    }

    public function excel(Request $request)
    {
        $r = $this->build($request);
        $rows = [['LAPORAN PEMAKAIAN RUANG'], ['Periode', $r['from']->format('d/m/Y').' - '.$r['until']->format('d/m/Y')],
            ['Catatan', 'Booking = tercatat; Jadwal tetap = sesi yang dijadwalkan (bukan bukti kehadiran).'], [],
            ['Ruangan', 'Kode', 'Booking', 'Jadwal tetap', 'Total terjadwal', 'Jumlah periode blokir']];
        foreach ($r['summary'] as $entry) $rows[] = [$entry['room']->name, $entry['room']->code, $entry['booking'], $entry['regular'], $entry['total'], $entry['blocks']];
        $rows[] = []; $rows[] = ['PROGRAM STUDI / KELAS', 'Total sesi terjadwal'];
        foreach ($r['prodis'] as $prodi => $total) $rows[] = [$prodi, $total];
        $rows[] = []; $rows[] = ['HARI', 'Total sesi terjadwal'];
        $names = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];
        foreach ($r['dayCounts'] as $day => $value) $rows[] = [$names[$day], $value];
        $rows[] = []; $rows[] = ['JAM', 'Jumlah sesi beririsan dengan jam tersebut'];
        foreach ($r['hourCounts'] as $hour => $value) $rows[] = [sprintf('%02d:00–%02d:00', $hour, $hour + 1), $value];
        AdminAudit::record('report.excel', null, ['from' => $r['from']->toDateString(), 'until' => $r['until']->toDateString()]);
        return SimpleExcel::download($rows, 'laporan_pemakaian_ruang.xlsx');
    }

    public function pdf(Request $request)
    {
        $r = $this->build($request);
        $lines = ['LAPORAN PEMAKAIAN RUANG KULIAH', 'Periode: '.$r['from']->format('d/m/Y').' - '.$r['until']->format('d/m/Y'),
            'Total = booking aktif + pertemuan jadwal tetap yang direncanakan.', 'Jadwal tetap bukan bukti pelaksanaan atau kehadiran.',
            str_repeat('-', 82), 'KODE       BOOKING    JADWAL TETAP    TOTAL    BLOKIR   RUANG'];
        foreach ($r['summary'] as $entry) {
            $lines[] = sprintf('%-10.10s %-10d %-15d %-8d %-8d %s', $entry['room']->code, $entry['booking'], $entry['regular'], $entry['total'], $entry['blocks'], $entry['room']->name);
        }
        $lines[] = ''; $lines[] = 'PROGRAM STUDI / KELAS (jumlah sesi)';
        foreach ($r['prodis'] as $prodi => $count) $lines[] = $prodi.' : '.$count;
        $lines[] = ''; $lines[] = 'HARI TERPADAT (berdasarkan jumlah sesi)';
        $dayNames = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];
        foreach ($r['dayCounts'] as $day => $count) $lines[] = $dayNames[$day].' : '.$count;
        $lines[] = ''; $lines[] = 'JAM TERSIBUK (jumlah sesi yang beririsan dengan tiap jam)';
        foreach ($r['hourCounts'] as $hour => $count) $lines[] = sprintf('%02d:00 - %02d:00 : %d', $hour, $hour + 1, $count);
        AdminAudit::record('report.pdf', null, ['from' => $r['from']->toDateString(), 'until' => $r['until']->toDateString()]);
        return ReportPdf::download($lines, 'laporan_pemakaian_ruang.pdf');
    }
}
