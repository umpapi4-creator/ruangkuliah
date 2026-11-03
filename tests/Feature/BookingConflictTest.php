<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\PrioritySchedule;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingConflictTest extends TestCase
{
    use RefreshDatabase;

    public function test_room_cannot_be_booked_for_overlapping_time(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $room = Room::create(['code' => 'R-01', 'name' => 'Ruang 01', 'capacity' => 30, 'is_active' => true]);
        $date = now()->addDay()->toDateString();

        Booking::create([
            'room_id' => $room->id,
            'user_id' => $first->id,
            'course_name' => 'Mata Kuliah A',
            'class_group' => 'Kelas A',
            'start_at' => $date.' 09:00:00',
            'end_at' => $date.' 11:00:00',
            'status' => 'active',
        ]);

        $response = $this->actingAs($second)->post(route('bookings.store'), [
            'room_id' => $room->id,
            'course_name' => 'Mata Kuliah B',
            'class_group' => 'Kelas B',
            'date' => $date,
            'start_time' => '10:00',
            'end_time' => '12:00',
        ]);

        $response->assertSessionHasErrors('start_time');
        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_user_can_book_more_than_one_room_during_overlapping_time(): void
    {
        $user = User::factory()->create();
        $firstRoom = Room::create(['code' => 'TEST-MULTI-1', 'name' => 'Ruang Kelas', 'capacity' => 30, 'is_active' => true]);
        $secondRoom = Room::create(['code' => 'TEST-MULTI-2', 'name' => 'Laboratorium', 'capacity' => 30, 'is_active' => true]);
        $start = now()->addDay()->setTime(9, 0);

        Booking::create([
            'room_id' => $firstRoom->id,
            'user_id' => $user->id,
            'course_name' => 'Mata Kuliah A',
            'class_group' => 'Kelas A',
            'start_at' => $start,
            'end_at' => $start->copy()->addHours(2),
            'status' => Booking::STATUS_ACTIVE,
        ]);

        $this->actingAs($user)->post(route('bookings.store'), [
            'room_id' => $secondRoom->id,
            'course_name' => 'Praktikum A',
            'class_group' => 'Kelas A',
            'date' => $start->toDateString(),
            'start_time' => '09:30',
            'end_time' => '10:30',
        ])->assertRedirect(route('bookings.index'));

        $this->assertDatabaseCount('bookings', 2);
        $this->assertDatabaseHas('bookings', [
            'room_id' => $secondRoom->id,
            'user_id' => $user->id,
            'status' => Booking::STATUS_ACTIVE,
        ]);
    }

    public function test_cancelled_booking_releases_the_room(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $room = Room::create(['code' => 'R-02', 'name' => 'Ruang 02', 'capacity' => 30, 'is_active' => true]);
        $start = now()->addDay()->setTime(9, 0);
        $booking = Booking::create([
            'room_id' => $room->id,
            'user_id' => $first->id,
            'course_name' => 'Mata Kuliah A',
            'class_group' => 'Kelas A',
            'start_at' => $start,
            'end_at' => $start->copy()->addHours(2),
            'status' => 'active',
        ]);

        $this->actingAs($first)->patch(route('bookings.cancel', $booking))->assertSessionHas('success');

        $this->actingAs($second)->post(route('bookings.store'), [
            'room_id' => $room->id,
            'course_name' => 'Mata Kuliah B',
            'class_group' => 'Kelas B',
            'date' => $start->toDateString(),
            'start_time' => '09:30',
            'end_time' => '10:30',
        ])->assertRedirect(route('bookings.index'));

        $this->assertDatabaseCount('bookings', 2);
        $this->assertDatabaseHas('bookings', ['user_id' => $second->id, 'status' => 'active']);
    }

    public function test_owner_can_cancel_booking_while_class_is_running(): void
    {
        $owner = User::factory()->create();
        $room = Room::create(['code' => 'R-08', 'name' => 'Ruang 08', 'capacity' => 30, 'is_active' => true]);
        $booking = Booking::create([
            'room_id' => $room->id,
            'user_id' => $owner->id,
            'course_name' => 'Kelas Berjalan',
            'class_group' => 'Kelas A',
            'start_at' => now()->subMinutes(30),
            'end_at' => now()->addMinutes(30),
            'status' => Booking::STATUS_ACTIVE,
        ]);

        $this->actingAs($owner)
            ->patch(route('bookings.cancel', $booking))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => Booking::STATUS_CANCELLED,
            'cancelled_by' => $owner->id,
        ]);
    }

    public function test_another_user_cannot_cancel_running_booking(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $room = Room::create(['code' => 'R-09', 'name' => 'Ruang 09', 'capacity' => 30, 'is_active' => true]);
        $booking = Booking::create([
            'room_id' => $room->id,
            'user_id' => $owner->id,
            'course_name' => 'Kelas Berjalan',
            'class_group' => 'Kelas A',
            'start_at' => now()->subMinutes(30),
            'end_at' => now()->addMinutes(30),
            'status' => Booking::STATUS_ACTIVE,
        ]);

        $this->actingAs($otherUser)
            ->patch(route('bookings.cancel', $booking))
            ->assertForbidden();

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => Booking::STATUS_ACTIVE,
            'cancelled_by' => null,
        ]);
    }

    public function test_finished_room_usage_disappears_automatically_from_active_list(): void
    {
        $user = User::factory()->create();
        $room = Room::create(['code' => 'R-03', 'name' => 'Ruang 03', 'capacity' => 30, 'is_active' => true]);

        Booking::create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'course_name' => 'Kegiatan Sudah Selesai',
            'class_group' => 'Kelas A',
            'start_at' => now()->subHours(2),
            'end_at' => now()->subHour(),
            'status' => 'active',
        ]);

        Booking::create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'course_name' => 'Kegiatan Mendatang',
            'class_group' => 'Kelas A',
            'start_at' => now()->addDay(),
            'end_at' => now()->addDay()->addHour(),
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->get(route('bookings.index'))
            ->assertOk()
            ->assertDontSee('Kegiatan Sudah Selesai')
            ->assertSee('Kegiatan Mendatang');
    }

    public function test_finished_class_appears_in_schedule_history(): void
    {
        $user = User::factory()->create();
        $room = Room::create(['code' => 'R-07', 'name' => 'Ruang 07', 'capacity' => 30, 'is_active' => true]);

        Booking::create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'course_name' => 'Kelas Selesai Hari Ini',
            'class_group' => 'D4 MIK',
            'start_at' => now()->subHours(2),
            'end_at' => now()->subHour(),
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->get(route('schedule', ['date' => today()->toDateString(), 'room_id' => $room->id]))
            ->assertOk()
            ->assertSee('Riwayat Kelas')
            ->assertSee('Kelas Selesai Hari Ini')
            ->assertSee('Selesai');
    }

    public function test_priority_schedule_blocks_other_account_before_release_time(): void
    {
        $priorityUser = User::factory()->create(['name' => 'D4 MIK']);
        $otherUser = User::factory()->create(['name' => 'S1 Teknologi Informasi']);
        $room = Room::create(['code' => 'R-04', 'name' => 'Ruang 04', 'capacity' => 30, 'is_active' => true]);
        $date = now()->addDay()->toDateString();

        PrioritySchedule::create([
            'room_id' => $room->id,
            'user_id' => $priorityUser->id,
            'schedule_date' => $date,
            'start_time' => '08:00',
            'end_time' => '12:00',
            'is_active' => true,
        ]);

        $this->actingAs($otherUser)->post(route('bookings.store'), [
            'room_id' => $room->id,
            'course_name' => 'Mata Kuliah Lain',
            'class_group' => 'Kelas Lain',
            'date' => $date,
            'start_time' => '09:00',
            'end_time' => '10:00',
        ])->assertSessionHasErrors('start_time');

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_priority_account_can_book_its_own_reserved_slot(): void
    {
        $priorityUser = User::factory()->create(['name' => 'D4 MIK']);
        $room = Room::create(['code' => 'R-06', 'name' => 'Ruang 06', 'capacity' => 30, 'is_active' => true]);
        $date = now()->addDay()->toDateString();

        PrioritySchedule::create([
            'room_id' => $room->id,
            'user_id' => $priorityUser->id,
            'schedule_date' => $date,
            'start_time' => '08:00',
            'end_time' => '12:00',
            'is_active' => true,
        ]);

        $this->actingAs($priorityUser)->post(route('bookings.store'), [
            'room_id' => $room->id,
            'course_name' => 'Manajemen Informasi Kesehatan',
            'class_group' => 'D4 MIK',
            'date' => $date,
            'start_time' => '09:00',
            'end_time' => '10:00',
        ])->assertRedirect(route('bookings.index'));

        $this->assertDatabaseHas('bookings', ['user_id' => $priorityUser->id, 'status' => 'active']);
    }

    public function test_unclaimed_priority_opens_for_other_accounts_at_midnight(): void
    {
        $this->travelTo(Carbon::parse('2026-09-28 10:00:00'));

        $priorityUser = User::factory()->create(['name' => 'D4 MIK']);
        $otherUser = User::factory()->create(['name' => 'S1 Teknologi Informasi']);
        $room = Room::create(['code' => 'R-05', 'name' => 'Ruang 05', 'capacity' => 30, 'is_active' => true]);
        $date = now()->addDay()->toDateString();

        PrioritySchedule::create([
            'room_id' => $room->id,
            'user_id' => $priorityUser->id,
            'schedule_date' => $date,
            'start_time' => '08:00',
            'end_time' => '12:00',
            'is_active' => true,
        ]);

        $this->travelTo(Carbon::parse($date.' 00:01:00'));

        $this->actingAs($otherUser)->post(route('bookings.store'), [
            'room_id' => $room->id,
            'course_name' => 'Mata Kuliah Lain',
            'class_group' => 'Kelas Lain',
            'date' => $date,
            'start_time' => '09:00',
            'end_time' => '10:00',
        ])->assertRedirect(route('bookings.index'));

        $this->assertDatabaseHas('bookings', ['user_id' => $otherUser->id, 'status' => 'active']);
    }
}
