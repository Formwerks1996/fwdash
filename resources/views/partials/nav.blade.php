@php
    $sections = [
        __('Firm health') => [
            ['overview', 'ph-squares-four', __('Overview'), 'view financials'],
            ['invoicing', 'ph-receipt', __('Invoicing'), 'view financials'],
        ],
        __('Projects') => [
            ['projects.index', 'ph-folders', __('Projects'), 'view financials'],
        ],
        __('Office') => [
            ['software', 'ph-cube', __('Software'), null],
            ['hardware', 'ph-laptop', __('Hardware'), null],
        ],
        __('Admin') => [
            ['imports.create', 'ph-upload-simple', __('Upload workbook'), 'import data'],
            ['users', 'ph-users', __('Users'), 'manage users'],
        ],
    ];
@endphp

@foreach($sections as $label => $items)
    @php
        $visible = array_filter($items, fn (array $item) => $item[3] === null || auth()->user()?->can($item[3]));
    @endphp

    @if($visible)
        <p class="nav-label">{{ $label }}</p>

        @foreach($visible as [$route, $icon, $name])
            <a
                href="{{ route($route) }}"
                @class(['nav-item', 'is-active' => request()->routeIs(str_replace('.index', '.*', $route))])
                wire:navigate
            >
                <i class="ph {{ $icon }}"></i>
                {{ $name }}
            </a>
        @endforeach
    @endif
@endforeach
