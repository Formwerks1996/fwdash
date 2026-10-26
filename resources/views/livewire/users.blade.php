<div class="max-w-4xl space-y-8">
    <div>
        <h1 class="page-title">{{ __('Users') }}</h1>
        <p class="page-subtitle">{{ __('Everyone who has signed in with Microsoft. New people start as Staff.') }}</p>
    </div>

    <div class="grid gap-px border border-[#e7e9ea] bg-[#e7e9ea] text-xs sm:grid-cols-3">
        <div class="bg-white p-4"><b class="font-medium text-navy">{{ __('Admin') }}</b><p class="mt-1 text-muted">{{ __('Everything, including workbook uploads and user roles.') }}</p></div>
        <div class="bg-white p-4"><b class="font-medium text-navy">{{ __('Manager') }}</b><p class="mt-1 text-muted">{{ __('Overview, Invoicing and Projects, and can edit billing, software and hardware.') }}</p></div>
        <div class="bg-white p-4"><b class="font-medium text-navy">{{ __('Staff') }}</b><p class="mt-1 text-muted">{{ __('Software and Hardware, read only.') }}</p></div>
    </div>

    @error('role') <p class="error-note">{{ $message }}</p> @enderror

    <div class="card overflow-x-auto p-0">
        <table class="data-table">
            <thead>
                <tr>
                    <th>{{ __('Name') }}</th>
                    <th>{{ __('Email') }}</th>
                    <th>{{ __('Last sign-in') }}</th>
                    <th>{{ __('Role') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($this->users as $user)
                    <tr wire:key="user-{{ $user->id }}">
                        <td class="font-medium">{{ $user->name }}</td>
                        <td>{{ $user->email }}</td>
                        <td class="text-muted">{{ $user->last_login_at?->timezone(config('app.display_timezone'))->format('M j, Y g:i a') ?? __('never') }}</td>
                        <td>
                            <select class="select" wire:change="setRole({{ $user->id }}, $event.target.value)">
                                @foreach($roles as $role)
                                    <option value="{{ $role->value }}" @selected($user->primaryRole() === $role)>{{ $role->label() }}</option>
                                @endforeach
                            </select>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
