{{-- فرم مشترک ساخت و ویرایش کاربر. $user, $roles, $permissionGroups --}}
@php
    $selectedRoles = array_map('intval', old('roles', $user->exists ? $user->roles->pluck('id')->all() : []));
    $selectedPermissions = array_map('intval', old('permissions', $user->exists ? $user->permissions->pluck('id')->all() : []));
@endphp

<div class="row g-3">
    <div class="col-md-6">
        <label for="name" class="form-label">{{ __('users.name') }} <span class="text-danger">*</span></label>
        <input type="text" id="name" name="name" value="{{ old('name', $user->name) }}" required maxlength="255"
               class="form-control @error('name') is-invalid @enderror">
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label for="username" class="form-label">{{ __('users.username') }} <span class="text-danger">*</span></label>
        <input type="text" id="username" name="username" value="{{ old('username', $user->username) }}" required maxlength="50"
               dir="ltr" autocomplete="off" autocapitalize="none" spellcheck="false"
               class="form-control @error('username') is-invalid @enderror">
        <div class="form-text">{{ __('users.username_hint') }}</div>
        @error('username')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label for="staff_id" class="form-label">{{ __('users.staff') }}</label>
        <select id="staff_id" name="staff_id" class="form-select @error('staff_id') is-invalid @enderror">
            <option value="">{{ __('users.no_staff') }}</option>
            @foreach ($staffOptions as $member)
                <option value="{{ $member->id }}" @selected((string) old('staff_id', $user->staff_id) === (string) $member->id)>
                    {{ $member->full_name }} — {{ $member->staff_type->label() }}
                </option>
            @endforeach
        </select>
        <div class="form-text">{{ __('users.staff_hint') }}</div>
        @error('staff_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    @unless ($user->exists)
        <div class="col-md-6">
            <label for="password" class="form-label">{{ __('users.password') }} <span class="text-danger">*</span></label>
            <input type="password" id="password" name="password" required dir="ltr" autocomplete="new-password"
                   class="form-control @error('password') is-invalid @enderror">
            <div class="form-text">{{ __('users.initial_password_hint') }}</div>
            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label for="password_confirmation" class="form-label">{{ __('users.password_confirmation') }} <span class="text-danger">*</span></label>
            <input type="password" id="password_confirmation" name="password_confirmation" required dir="ltr" autocomplete="new-password" class="form-control">
        </div>
        <div class="col-12">
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" @checked(old('is_active', true))>
                <label class="form-check-label" for="is_active">{{ __('users.is_active') }}</label>
            </div>
        </div>
    @endunless

    <div class="col-12">
        <fieldset>
            <legend class="form-label fs-6">{{ __('users.roles') }} <span class="text-danger">*</span></legend>
            <div @class(['d-flex flex-wrap gap-3 role-choices', 'is-invalid' => $errors->has('roles') || $errors->has('roles.*')])>
                @foreach ($roles as $role)
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="roles[]" value="{{ $role->id }}" id="role_{{ $role->id }}"
                               @checked(in_array($role->id, $selectedRoles, true))>
                        <label class="form-check-label" for="role_{{ $role->id }}">{{ $role->label() }}</label>
                    </div>
                @endforeach
            </div>
            @error('roles')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            @error('roles.*')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
        </fieldset>
    </div>

    <div class="col-12">
        <details @if ($selectedPermissions !== []) open @endif>
            <summary class="form-label fs-6">{{ __('users.extra_permissions') }}</summary>
            <p class="form-text">{{ __('users.extra_permissions_hint') }}</p>
            <div class="perm-grid">
                @foreach ($permissionGroups as $group => $permissions)
                    <fieldset class="perm-group border rounded p-2">
                        <legend class="px-1">{{ __('app.permission_groups.'.$group) }}</legend>
                        @foreach ($permissions as $permission)
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="permissions[]" value="{{ $permission->id }}" id="perm_{{ $permission->id }}"
                                       @checked(in_array($permission->id, $selectedPermissions, true))>
                                <label class="form-check-label" for="perm_{{ $permission->id }}">{{ $permission->label() }}</label>
                            </div>
                        @endforeach
                    </fieldset>
                @endforeach
            </div>
        </details>
    </div>
</div>
