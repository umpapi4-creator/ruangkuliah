<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AdminAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index()
    {
        $users = User::whereIn('role', [User::ROLE_STUDENT, User::ROLE_LECTURER])
            ->withCount('bookings')
            ->orderBy('role')
            ->orderBy('study_program')
            ->orderBy('username')
            ->paginate(10);
        return view('admin.users.index', compact('users'));
    }

    public function create()
    {
        return view('admin.users.form', [
            'user' => new User(),
            'studyPrograms' => User::STUDY_PROGRAMS,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['name'] = $data['username'];
        $data['password'] = Hash::make($data['password']);
        $newUser = User::create($data);
        AdminAudit::record('user.created', $newUser, $newUser->only(['name', 'username', 'nim', 'email', 'role', 'study_program', 'is_active']));

        return redirect()->route('admin.users.index')->with('success', 'Akun pengguna berhasil ditambahkan dan dapat langsung digunakan untuk login.');
    }

    public function edit(User $user)
    {
        abort_unless($user->isManagedUser(), 404);
        return view('admin.users.form', [
            'user' => $user,
            'studyPrograms' => User::STUDY_PROGRAMS,
        ]);
    }

    public function update(Request $request, User $user)
    {
        abort_unless($user->isManagedUser(), 404);
        $data = $this->validated($request, $user);
        $data['name'] = $data['username'];
        if (empty($data['password'])) {
            unset($data['password']);
        } else {
            $data['password'] = Hash::make($data['password']);
        }
        $before = $user->only(['name', 'username', 'nim', 'email', 'role', 'study_program', 'is_active']);
        $passwordChanged = array_key_exists('password', $data);
        $user->update($data);
        AdminAudit::record('user.updated', $user, [
            'before' => $before, 'after' => $user->only(array_keys($before)), 'password_changed' => $passwordChanged,
        ]);

        return redirect()->route('admin.users.index')->with('success', 'Akun pengguna berhasil diperbarui.');
    }

    private function validated(Request $request, ?User $user = null): array
    {
        $rules = [
            'username' => ['required', 'string', 'max:50', Rule::unique('users')->ignore($user?->id)],
            'nim' => ['required', 'string', 'max:50', Rule::unique('users')->ignore($user?->id)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user?->id)],
            'role' => ['required', Rule::in([User::ROLE_STUDENT, User::ROLE_LECTURER])],
            'study_program' => ['required', Rule::in(User::STUDY_PROGRAMS)],
            'password' => [$user ? 'nullable' : 'required', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ];

        $data = $request->validate($rules);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
