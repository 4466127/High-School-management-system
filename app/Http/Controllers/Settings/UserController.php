<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ResetUserPasswordRequest;
use App\Http\Requests\Settings\StoreUserRequest;
use App\Http\Requests\Settings\UpdateUserRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Staff;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', User::class);

        $search = trim((string) $request->query('q'));
        $role = (string) $request->query('role');
        $status = (string) $request->query('status');

        $users = User::query()
            ->with('roles')
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('username', 'like', "%{$search}%")))
            ->when($role !== '', fn ($q) => $q->whereHas('roles', fn ($q) => $q->where('key', $role)))
            ->when($status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('settings.users.index', [
            'users' => $users,
            'roles' => Role::orderBy('id')->get(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', User::class);

        return view('settings.users.create', $this->formData(new User));
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'username' => $data['username'],
                'staff_id' => $data['staff_id'] ?? null,
                'password' => $data['password'],
                'is_active' => $data['is_active'] ?? false,
                'must_change_password' => true,
            ]);
            $user->roles()->sync($data['roles']);
            $user->permissions()->sync($data['permissions'] ?? []);

            $this->logger->log('user.created', $user, $user->name, new: $this->snapshot($user));

            return $user;
        });

        return redirect()->route('settings.users.index')
            ->with('success', __('users.created', ['name' => $user->name]));
    }

    public function edit(User $user): View
    {
        Gate::authorize('update', $user);

        return view('settings.users.edit', $this->formData($user->load('roles', 'permissions')));
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();
        $old = $this->snapshot($user);

        DB::transaction(function () use ($user, $data, $old) {
            $user->update(['name' => $data['name'], 'username' => $data['username'], 'staff_id' => $data['staff_id'] ?? null]);
            $user->roles()->sync($data['roles']);
            $user->permissions()->sync($data['permissions'] ?? []);
            $user->flushPermissionCache();

            $new = $this->snapshot($user);
            if ($new !== $old) {
                $this->logger->log('user.updated', $user, $user->name, $old, $new);
            }
        });

        return redirect()->route('settings.users.index')
            ->with('success', __('users.updated', ['name' => $user->name]));
    }

    public function toggleActive(User $user): RedirectResponse
    {
        Gate::authorize('toggleActive', $user);

        DB::transaction(function () use ($user) {
            $user->update(['is_active' => ! $user->is_active]);

            if (! $user->is_active) {
                // بیرون کردن فوری از همه‌ی دستگاه‌ها
                DB::table('sessions')->where('user_id', $user->id)->delete();
                $user->forceFill(['remember_token' => null])->save();
            }

            $this->logger->log($user->is_active ? 'user.activated' : 'user.deactivated', $user, $user->name);
        });

        return back()->with('success', __($user->is_active ? 'users.activated' : 'users.deactivated', ['name' => $user->name]));
    }

    public function resetPassword(ResetUserPasswordRequest $request, User $user): RedirectResponse
    {
        DB::transaction(function () use ($request, $user) {
            $user->forceFill([
                'password' => $request->validated('password'),
                'must_change_password' => true,
                'remember_token' => null,
            ])->save();

            if (! $user->is($request->user())) {
                DB::table('sessions')->where('user_id', $user->id)->delete();
            }

            $this->logger->log('user.password_reset', $user, $user->name);
        });

        return redirect()->route('settings.users.edit', $user)
            ->with('success', __('users.password_reset', ['name' => $user->name]));
    }

    private function formData(User $user): array
    {
        return [
            'user' => $user,
            'roles' => Role::orderBy('id')->get(),
            'permissionGroups' => Permission::orderBy('id')->get()->groupBy('group'),
            // کارمندانی که هنوز حساب ندارند + کارمند فعلی همین حساب
            'staffOptions' => Staff::query()
                ->where(fn ($q) => $q->whereDoesntHave('user')->orWhere('id', $user->staff_id))
                ->orderBy('first_name')
                ->get(['id', 'first_name', 'last_name', 'staff_type']),
        ];
    }

    /** مقدارهای قابل مقایسه برای ثبت رویداد (بدون رمز). */
    private function snapshot(User $user): array
    {
        $user->unsetRelation('roles')->unsetRelation('permissions');

        return [
            'name' => $user->name,
            'username' => $user->username,
            'staff_id' => $user->staff_id,
            'is_active' => $user->is_active,
            'roles' => $user->roles()->orderBy('key')->pluck('key')->all(),
            'permissions' => $user->permissions()->orderBy('key')->pluck('key')->all(),
        ];
    }
}
