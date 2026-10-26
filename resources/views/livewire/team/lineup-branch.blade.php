@php
    $children = $tree->childrenOf($name);
    $count = $tree->countUnder($name);
@endphp
<li data-drop="under:{{ $name }}" wire:key="lu-branch-{{ $name }}">
    <div class="lu-row">
        @include('livewire.team.lineup-who', ['name' => $name, 'extra' => $tree->isPm($name) ? 'lu-pmname' : ''])
        @if($children)
            <span class="lu-caps">{{ trans_choice(':count person|:count people', $count, ['count' => $count]) }}</span>
        @endif
    </div>
    @if($children)
        <ul class="lu-people">
            @foreach($children as $child)
                @include('livewire.team.lineup-branch', ['name' => $child])
            @endforeach
        </ul>
    @endif
</li>
