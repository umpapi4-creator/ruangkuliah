<?php

namespace Tests\Feature;

use App\Models\CourseSchedule;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserRoleManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_lecturer_and_lecturer_can_book_a_room(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $room = Room::create([
            'code' => 'R-DOSEN',
            'name' => 'Ruang Dosen',
            'capacity' => 30,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.users.create'))
            ->assertOk()
            ->assertDontSee('name="name"', false)
            ->assertSee('name="username"', false)
            ->assertSee('name="study_program"', false)
            ->assertSee('D4 Manajemen Informasi Kesehatan')
            ->assertSee('S1 Teknologi Informasi')
            ->assertSee('name="password"', false)
            ->assertDontSee('Username dan password acak');

        $response = $this->actingAs($admin)->post(route('admin.users.store'), [
            'username' => 'dosen bebas 01',
            'nim' => 'NIDN-001',
            'email' => 'dosen01@example.test',
            'role' => User::ROLE_LECTURER,
            'study_program' => 'S1 Teknologi Informasi',
            'password' => '4',
            'is_active' => 1,
        ]);
        $response->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('success');

        $lecturer = User::where('username', 'dosen bebas 01')->firstOrFail();
        $this->assertSame('dosen bebas 01', $lecturer->name);
        $this->assertSame('S1 Teknologi Informasi', $lecturer->study_program);
        $this->assertTrue(Hash::check('4', $lecturer->password));
        $this->assertTrue($lecturer->isLecturer());
        $this->assertSame('Dosen', $lecturer->role_label);

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('dosen bebas 01')
            ->assertSee('S1 Teknologi Informasi')
            ->assertSee('Dosen');

        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->post(route('login.store'), [
            'identity' => 'dosen bebas 01',
            'password' => '4',
        ])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($lecturer);

        $this
            ->get(route('bookings.create'))
            ->assertOk()
            ->assertSee('value="S1 Teknologi Informasi"', false);

        $this->post(route('bookings.store'), [
            'room_id' => $room->id,
            'course_name' => 'Kuliah Dosen',
            'class_group' => 'Kelas A',
            'date' => today()->addDay()->toDateString(),
            'start_time' => '08:00',
            'end_time' => '10:00',
        ])->assertRedirect(route('bookings.index'));

        $this->assertDatabaseHas('bookings', [
            'user_id' => $lecturer->id,
            'course_name' => 'Kuliah Dosen',
        ]);
    }

    public function test_admin_can_change_managed_user_role_but_cannot_assign_admin_role(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $user = User::factory()->create([
            'role' => User::ROLE_STUDENT,
            'username' => 'mahasiswa_role',
            'nim' => 'NIM-ROLE-001',
            'email' => 'mahasiswa.role@example.test',
            'study_program' => 'S1 Farmasi',
        ]);

        $this->actingAs($admin)->put(route('admin.users.update', $user), [
            'name' => $user->name,
            'username' => $user->username,
            'nim' => $user->nim,
            'email' => $user->email,
            'role' => User::ROLE_LECTURER,
            'study_program' => $user->study_program,
            'is_active' => 1,
        ])->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'role' => User::ROLE_LECTURER,
        ]);

        $this->actingAs($admin)->put(route('admin.users.update', $user), [
            'name' => $user->name,
            'username' => $user->username,
            'nim' => $user->nim,
            'email' => $user->email,
            'role' => User::ROLE_ADMIN,
            'study_program' => $user->study_program,
            'is_active' => 1,
        ])->assertSessionHasErrors('role');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'role' => User::ROLE_LECTURER,
        ]);
    }

    public function test_lecturer_can_manage_only_their_own_course_schedules(): void
    {
        $lecturer = User::factory()->create([
            'role' => User::ROLE_LECTURER,
            'name' => 'Dosen Pemilik Jadwal',
        ]);
        $otherLecturer = User::factory()->create(['role' => User::ROLE_LECTURER]);
        $student = User::factory()->create([
            'role' => User::ROLE_STUDENT,
            'name' => 'S1 Teknologi Informasi',
            'study_program' => 'S1 Teknologi Informasi',
        ]);
        $room = Room::create([
            'code' => 'R-JADWAL-DOSEN',
            'name' => 'Ruang Jadwal Dosen',
            'capacity' => 30,
            'is_active' => true,
        ]);
        $monday = today()->addDay();
        while ($monday->dayOfWeekIso !== 1) {
            $monday->addDay();
        }

        $this->actingAs($lecturer)
            ->get(route('lecturer.schedules.index'))
            ->assertOk()
            ->assertSee('Jadwal Kuliah Saya');
        $this->actingAs($lecturer)
            ->get(route('lecturer.schedules.create'))
            ->assertOk()
            ->assertSee($lecturer->name)
            ->assertSee($student->name);
        $this->actingAs($student)
            ->get(route('lecturer.schedules.index'))
            ->assertForbidden();
        $this->actingAs($lecturer)
            ->get(route('admin.users.index'))
            ->assertForbidden();

        $this->actingAs($lecturer)->post(route('lecturer.schedules.store'), [
            'user_id' => $student->id,
            'room_id' => $room->id,
            'course_name' => 'Algoritma Dosen',
            'lecturer_name' => 'Nama Tidak Boleh Dipakai',
            'weekday' => 1,
            'start_time' => '08:00',
            'end_time' => '10:00',
            'start_date' => $monday->toDateString(),
            'end_date' => $monday->copy()->addWeeks(8)->toDateString(),
        ])->assertRedirect(route('lecturer.schedules.index'))
            ->assertSessionHas('success');

        $schedule = CourseSchedule::where('course_name', 'Algoritma Dosen')->firstOrFail();
        $this->assertSame($lecturer->id, $schedule->created_by);
        $this->assertSame($lecturer->name, $schedule->lecturer_name);

        $this->actingAs($otherLecturer)
            ->delete(route('lecturer.schedules.destroy', $schedule))
            ->assertForbidden();
        $this->assertDatabaseHas('course_schedules', ['id' => $schedule->id]);

        $this->actingAs($lecturer)
            ->delete(route('lecturer.schedules.destroy', $schedule))
            ->assertSessionHas('success');
        $this->assertDatabaseMissing('course_schedules', ['id' => $schedule->id]);
    }
}
