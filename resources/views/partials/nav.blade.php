@foreach(\App\Support\Navigation::for(auth()->user()) as $label => $pages)
    <p class="nav-label">{{ __($label) }}</p>

    @foreach($pages as $page)
        <a
            href="{{ route($page['route']) }}"
            @class(['nav-item', 'is-active' => request()->routeIs($page['route'], str_replace('.index', '.*', $page['route']))])
            wire:navigate
        >
            <i class="ph {{ $page['icon'] }}"></i>
            {{ __($page['label']) }}
        </a>
    @endforeach
@endforeach
