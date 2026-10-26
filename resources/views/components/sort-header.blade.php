@props(['column', 'sort', 'direction', 'numeric' => false])

<th @class(['is-num' => $numeric])>
    <button type="button" wire:click="sortBy('{{ $column }}')">
        {{ $slot }}
        @if($sort === $column)
            <span aria-hidden="true">{{ $direction === 'asc' ? '↑' : '↓' }}</span>
        @endif
    </button>
</th>
