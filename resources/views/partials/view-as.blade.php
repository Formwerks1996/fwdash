{{-- Admins only: preview the dashboard as another role would see it. --}}
@if(auth()->user()?->hasRole(\App\Enums\Role::Admin->value))
    @php($viewingAs = auth()->user()->previewRole() ?? \App\Enums\Role::Admin)

    <form method="POST" action="{{ route('viewAs') }}" class="flex items-center gap-2">
        @csrf
        <span class="text-xs whitespace-nowrap text-muted">{{ __('View as') }}</span>
        <div class="seg">
            @foreach(\App\Enums\Role::cases() as $role)
                <button type="submit" name="role" value="{{ $role->value }}" @class(['is-active' => $viewingAs === $role]) @if($viewingAs === $role) aria-pressed="true" @endif>
                    {{ $role->label() }}
                </button>
            @endforeach
        </div>
    </form>
@endif
