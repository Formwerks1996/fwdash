@php $children = $tree->childrenOf($name); @endphp
<div class="lu-branch" wire:key="lu-branch-{{ $name }}">
    @include('livewire.team.lineup-who', ['name' => $name])
    @if($children)
        <div class="lu-nest">
            @foreach($children as $child)
                @include('livewire.team.lineup-branch', ['name' => $child])
            @endforeach
        </div>
    @endif
</div>
