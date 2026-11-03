<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\CourseSchedule;
use App\Models\Room;
use App\Models\RoomBlock;
use App\Models\User;
use App\Services\SimpleExcel;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use ZipArchive;
use Tests\TestCase;

class ExtraScheduleFeaturesTest extends TestCase
{
    use RefreshDatabase;

    private function room(): Room
    {
        return Room::create(['code' => 'R-01', 'name' => 'Ruang 01', 'capacity' => 30, 'is_active' => true]);
    }

    private function nextWeekday(int $weekday): Carbon
    {
        $day = today()->addDay();
        while ($day->dayOfWeekIso !== $weekday) $day->addDay();
        return $day;
    }

    public function test_every_user_is_prevented_from_booking_during_room_block(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create();
        $room = $this->room();
        $date = now()->addDay()->toDateString();
        $this->actingAs($admin)->post(route('admin.blocks.store'), [
            'room_id' => $room->id, 'reason' => 'Renovasi',
            'start_at' => $date.'T08:00', 'end_at' => $date.'T12:00',
        ])->assertSessionHas('success');
        $this->assertDatabaseCount('room_blocks', 1);
        $this->actingAs($student)->post(route('bookings.store'), [
            'room_id' => $room->id, 'course_name' => 'A', 'class_group' => 'D4 MIK',
            'date' => $date, 'start_time' => '09:00', 'end_time' => '10:00',
        ])->assertSessionHasErrors('start_time');
        $this->assertDatabaseCount('bookings', 0);
        $this->assertDatabaseHas('admin_activity_logs', ['action' => 'room.blocked', 'admin_id' => $admin->id]);
    }

    public function test_recurring_course_schedule_prevents_booking_and_priority(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create();
        $room = $this->room();
        $date = now()->addDay();
        CourseSchedule::create([
            'room_id' => $room->id, 'prodi' => 'D4 MIK', 'course_name' => 'Coding',
            'lecturer_name' => 'Dosen A', 'weekday' => $date->dayOfWeekIso,
            'start_time' => '08:00', 'end_time' => '12:00',
            'start_date' => $date->copy()->subWeek()->toDateString(),
            'end_date' => $date->copy()->addWeek()->toDateString(), 'created_by' => $admin->id,
        ]);
        $this->actingAs($student)->post(route('bookings.store'), [
            'room_id' => $room->id, 'course_name' => 'B', 'class_group' => 'Kelas B',
            'date' => $date->toDateString(), 'start_time' => '09:00', 'end_time' => '10:00',
        ])->assertSessionHasErrors('start_time');
        $this->actingAs($admin)->post(route('admin.priorities.store'), [
            'room_id' => $room->id, 'user_id' => $student->id,
            'schedule_date' => $date->toDateString(), 'start_time' => '09:00',
            'end_time' => '10:00', 'is_active' => 1,
        ])->assertSessionHasErrors('start_time');
    }

    public function test_import_accepts_valid_csv_rows_and_reports_conflicting_rows(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $room = $this->room();
        $monday = $this->nextWeekday(1);
        $end = $monday->copy()->addWeeks(4);
        $csv = 'prodi,mata kuliah,dosen,ruangan,hari,jam mulai,jam selesai,tanggal mulai,tanggal berakhir'."\n".
            'D4 MIK,Coding,Dosen A,'.$room->code.',Senin,08:00,10:00,'.$monday->toDateString().','.$end->toDateString()."\n".
            'D4 MIK,Coding Kedua,Dosen B,'.$room->code.',Senin,09:00,11:00,'.$monday->toDateString().','.$end->toDateString()."\n";
        $upload = UploadedFile::fake()->createWithContent('jadwal.csv', $csv);
        $response = $this->actingAs($admin)->post(route('admin.imports.store'), ['file' => $upload]);
        $response->assertRedirect(route('admin.imports.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseCount('course_schedules', 1);
        $this->assertDatabaseHas('admin_activity_logs', ['action' => 'schedule.imported']);
    }

    public function test_excel_template_contains_dropdown_lists_from_active_data(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->create(['role' => 'student', 'name' => 'S1 Teknologi Informasi', 'is_active' => true]);
        User::factory()->create(['role' => 'student', 'name' => 'Prodi Nonaktif', 'is_active' => false]);
        $room = $this->room();
        Room::create(['code' => 'R-OFF', 'name' => 'Ruang Nonaktif', 'capacity' => 20, 'is_active' => false]);

        $response = $this->actingAs($admin)->get(route('admin.imports.template'));
        $response->assertOk()->assertDownload('template_import_jadwal.xlsx');

        $templatePath = $response->baseResponse->getFile()->getPathname();
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($templatePath) === true);
        $workbook = $zip->getFromName('xl/workbook.xml');
        $mainSheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $references = $zip->getFromName('xl/worksheets/sheet2.xml');
        $zip->close();

        $this->assertStringContainsString('name="DaftarProdi"', $workbook);
        $this->assertStringContainsString('name="DaftarRuangan"', $workbook);
        $this->assertStringContainsString('state="hidden"', $workbook);
        $this->assertStringContainsString('sqref="A2:A501"', $mainSheet);
        $this->assertStringContainsString('sqref="F2:F501"', $mainSheet);
        $this->assertStringContainsString('S1 Teknologi Informasi', $references);
        $this->assertStringContainsString($room->code, $references);
        $this->assertStringNotContainsString('Prodi Nonaktif', $references);
        $this->assertStringNotContainsString('R-OFF', $references);

        $rows = SimpleExcel::read($templatePath, 'xlsx');
        $this->assertCount(501, $rows);
        $this->assertSame(
            ['prodi', 'mata kuliah', 'dosen', 'ruangan', 'hari', 'jam mulai', 'jam selesai', 'tanggal mulai', 'tanggal berakhir'],
            $rows[0]
        );
    }

    public function test_xlsx_import_reads_selected_time_columns_correctly(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $room = $this->room();
        $monday = $this->nextWeekday(1);
        $end = $monday->copy()->addWeeks(4);
        $path = SimpleExcel::create([
            ['prodi', 'mata kuliah', 'dosen', 'ruangan', 'hari', 'jam mulai', 'jam selesai', 'tanggal mulai', 'tanggal berakhir'],
            ['S1 Teknologi Informasi', 'Pemrograman Web', 'Dosen A', $room->code, 'Senin', '08:00', '10:00', $monday->toDateString(), $end->toDateString()],
        ], 'jadwal.xlsx');
        $upload = new UploadedFile(
            $path,
            'jadwal.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $response = $this->actingAs($admin)->post(route('admin.imports.store'), ['file' => $upload]);

        $response->assertRedirect(route('admin.imports.index'))
            ->assertSessionHas('success');
        $this->assertSame([], session('import_errors', []), implode(PHP_EOL, session('import_errors', [])));
        $this->assertDatabaseHas('course_schedules', [
            'room_id' => $room->id,
            'course_name' => 'Pemrograman Web',
            'start_time' => '08:00',
            'end_time' => '10:00',
        ]);
    }

    public function test_admin_can_add_a_recurring_schedule_directly_and_conflicts_are_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $prodi = User::factory()->create([
            'role' => 'student',
            'name' => 'S1 Teknologi Informasi',
            'study_program' => 'S1 Teknologi Informasi',
            'is_active' => true,
        ]);
        $room = $this->room();
        $monday = $this->nextWeekday(1);
        $payload = [
            'user_id' => $prodi->id,
            'room_id' => $room->id,
            'course_name' => 'Basis Data',
            'lecturer_name' => 'Dosen Basis Data',
            'weekday' => 1,
            'start_time' => '08:00',
            'end_time' => '10:00',
            'start_date' => $monday->toDateString(),
            'end_date' => $monday->copy()->addWeeks(8)->toDateString(),
        ];

        $this->actingAs($admin)
            ->get(route('admin.schedules.create'))
            ->assertOk()
            ->assertSee('Tambah Jadwal Kuliah Langsung')
            ->assertSee($prodi->name)
            ->assertSee($room->code);

        $this->actingAs($admin)
            ->post(route('admin.schedules.store'), $payload)
            ->assertRedirect(route('admin.imports.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('course_schedules', [
            'room_id' => $room->id,
            'prodi' => $prodi->study_program,
            'course_name' => 'Basis Data',
        ]);
        $this->assertDatabaseHas('admin_activity_logs', [
            'admin_id' => $admin->id,
            'action' => 'schedule.created',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.schedules.store'), array_merge($payload, ['course_name' => 'Jadwal Bentrok']))
            ->assertSessionHasErrors('start_time');
        $this->assertDatabaseCount('course_schedules', 1);
    }

    public function test_weekly_calendar_and_reports_are_available_to_correct_roles(): void
    {
        $this->travelTo(Carbon::parse('2026-10-26 09:00:00'));
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['study_program' => 'S1 Farmasi']);
        $room = $this->room();
        Booking::create([
            'room_id' => $room->id,
            'user_id' => $student->id,
            'course_name' => 'Kimia Farmasi',
            'class_group' => 'Kelas Bebas',
            'start_at' => now()->copy()->addHour(),
            'end_at' => now()->copy()->addHours(2),
            'status' => Booking::STATUS_ACTIVE,
        ]);

        $this->actingAs($student)->get(route('calendar.weekly'))
            ->assertOk()
            ->assertSee('Senin')
            ->assertSee($room->code)
            ->assertSee('S1 Farmasi');
        $this->actingAs($student)->get(route('admin.reports.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.reports.index'))
            ->assertOk()
            ->assertSee('Pemakaian Tiap Ruangan')
            ->assertSee('S1 Farmasi')
            ->assertDontSee('Kelas Bebas');
        $this->actingAs($admin)->get(route('admin.audits.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.reports.pdf'))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_dashboard_separates_active_and_all_schedules_with_countdown_and_prodi_identity(): void
    {
        $this->travelTo(Carbon::parse('2026-10-26 09:00:00'));

        $student = User::factory()->create([
            'name' => 'angkatan-satu',
            'username' => 'angkatan-satu',
            'study_program' => 'D4 Manajemen Informasi Kesehatan',
        ]);
        $bookingRoom = $this->room();
        $regularRoom = Room::create([
            'code' => 'R-02',
            'name' => 'Ruang 02',
            'capacity' => 40,
            'is_active' => true,
        ]);

        Booking::create([
            'room_id' => $bookingRoom->id,
            'user_id' => $student->id,
            'course_name' => 'Statistik Kesehatan',
            'class_group' => 'D4 MIKES',
            'lecturer_name' => 'Dosen MIKES',
            'purpose' => 'Perkuliahan',
            'start_at' => now()->copy()->addHour(),
            'end_at' => now()->copy()->addHours(2),
            'status' => Booking::STATUS_ACTIVE,
        ]);
        CourseSchedule::create([
            'room_id' => $regularRoom->id,
            'prodi' => 'D4 Manajemen Informasi Kesehatan',
            'course_name' => 'Rekam Medis Elektronik',
            'lecturer_name' => 'Dosen Rekam Medis',
            'weekday' => now()->dayOfWeekIso,
            'start_time' => '08:00',
            'end_time' => '10:00',
            'start_date' => today()->copy()->subWeek()->toDateString(),
            'end_date' => today()->copy()->addWeek()->toDateString(),
            'created_by' => $student->id,
        ]);

        $response = $this->actingAs($student)->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('Jadwal Kuliah Sedang Aktif')
            ->assertSee('1 Kelas Aktif')
            ->assertSee('Keseluruhan Jadwal Hari Ini')
            ->assertSee('2 Jadwal')
            ->assertSee('Statistik Kesehatan')
            ->assertSee('Rekam Medis Elektronik')
            ->assertSee('Booking Ruang')
            ->assertSee('Jadwal Tetap')
            ->assertSee('Selesai pukul 10.00')
            ->assertSee('Menghitung waktu selesai...')
            ->assertSee('prodi-mikes', false)
            ->assertDontSee('Jadwal Kuliah Tetap Hari Ini');

        $activeSection = explode(
            'Keseluruhan Jadwal Hari Ini',
            explode('Jadwal Kuliah Sedang Aktif', $response->getContent(), 2)[1],
            2
        )[0];
        $this->assertStringContainsString('Rekam Medis Elektronik', $activeSection);
        $this->assertStringNotContainsString('Statistik Kesehatan', $activeSection);
    }

    public function test_dashboard_uses_reference_colors_and_a_stable_fallback_for_other_prodis(): void
    {
        $this->travelTo(Carbon::parse('2026-10-26 09:00:00'));

        $viewer = User::factory()->create();
        $room = $this->room();
        $themes = [
            'S1 Farmasi' => 'prodi-farmasi',
            'S1 Keperawatan' => 'prodi-perawat-s1',
            'D4 Manajemen Informasi Kesehatan' => 'prodi-mikes',
            'S1 Kebidanan dan Profesi Bidan' => 'prodi-bidan-s1',
            'S1 Teknologi Informasi' => 'prodi-ti',
            'D3 Keperawatan' => 'prodi-perawat-d3',
            'D3 Kebidanan' => 'prodi-bidan-d3',
        ];

        foreach (array_keys($themes) as $index => $prodi) {
            CourseSchedule::create([
                'room_id' => $room->id,
                'prodi' => $prodi,
                'course_name' => 'Mata Kuliah '.($index + 1),
                'lecturer_name' => 'Dosen '.($index + 1),
                'weekday' => now()->dayOfWeekIso,
                'start_time' => '12:00',
                'end_time' => '13:00',
                'start_date' => today()->copy()->subWeek()->toDateString(),
                'end_date' => today()->copy()->addWeek()->toDateString(),
                'created_by' => $viewer->id,
            ]);
        }
        CourseSchedule::create([
            'room_id' => $room->id,
            'prodi' => 'S1 Psikologi',
            'course_name' => 'Mata Kuliah Lain',
            'lecturer_name' => 'Dosen Lain',
            'weekday' => now()->dayOfWeekIso,
            'start_time' => '14:00',
            'end_time' => '15:00',
            'start_date' => today()->copy()->subWeek()->toDateString(),
            'end_date' => today()->copy()->addWeek()->toDateString(),
            'created_by' => $viewer->id,
        ]);

        $response = $this->actingAs($viewer)->get(route('dashboard'))->assertOk();

        foreach ($themes as $theme) {
            $response->assertSee($theme, false);
        }
        $this->assertMatchesRegularExpression(
            '/prodi-acak-(ungu|jingga|toska|nila|zaitun)/',
            $response->getContent()
        );
    }

    public function test_manual_schedule_room_options_use_natural_number_order(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->create([
            'role' => 'student',
            'name' => 'S1 Farmasi',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.schedules.create'))
            ->assertOk()
            ->assertSeeInOrder([
                'R-1 — Ruang Kelas R-1',
                'R-2 — Ruang Kelas R-2',
                'R-4 — Ruang Kelas R-4',
                'R-9 — Ruang Kelas R-9',
                'R-10 — Ruang Kelas R-10',
                'R-11 — Ruang Kelas R-11',
            ]);
    }
}
