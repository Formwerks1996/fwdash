@php
    use App\Support\LineupTree;

    $member = $tree->people[$name];
    $badge = $badges[$name] ?? null;
    $tag = $tags[$name] ?? null;
    $project = $tag ? ($projects[$tag] ?? null) : null;
@endphp
<span class="lu-name">
    <button type="button" @class(['lu-who', $extra ?? '', 'is-static' => ! $canEdit || $member->isPartner()]) data-id="{{ $name }}">{{ $member->displayName() }}</button>
    @unless($member->isPartner())
        @if($badge)
            <button
                type="button"
                class="lu-badge lu-badge-{{ $badge['type'] }}"
                @if($canEdit) data-act="badge" data-id="{{ $name }}" @else disabled @endif
                title="{{ LineupTree::Badges[$badge['type']]['hint'] }}{{ isset($badge['note']) ? ' — '.$badge['note'] : '' }}"
            >{{ LineupTree::Badges[$badge['type']]['label'] }}@isset($badge['note'])<small>{{ $badge['note'] }}</small>@endisset</button>
        @elseif($canEdit)
            <button type="button" class="lu-badge-add" data-act="badge" data-id="{{ $name }}" title="{{ __('Add a badge') }}"><i class="ph ph-flag"></i></button>
        @endif

        @if($tag)
            <button
                type="button"
                class="lu-ptag"
                @if($canEdit) data-act="tag" data-id="{{ $name }}" @else disabled @endif
                title="{{ $project ? $project['number'].' · '.$project['title'].($project['active'] ? '' : ' ('.__('closed').')') : __('Project no longer on the list') }}"
            >@if($project)<b>{{ $project['number'] }}</b> {{ $project['title'] }}@else{{ __('Unknown project') }}@endif</button>
        @elseif($canEdit)
            <button type="button" class="lu-tag-add" data-act="tag" data-id="{{ $name }}" title="{{ __('Tag a project') }}">+ {{ __('project') }}</button>
        @endif
    @endunless
</span>
