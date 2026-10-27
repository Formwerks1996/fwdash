@php
    use App\Support\LineupTree;
    use Illuminate\Support\Str;

    $member = $tree->people[$name];
    $badge = $badges[$name] ?? null;
    $tag = $tags[$name] ?? null;
    $project = $tag ? ($projects[$tag] ?? null) : null;
    $under = $tree->countUnder($name);
    $initials = Str::of($member->displayName())->explode(' ')->filter()->take(2)->map(fn (string $part) => Str::upper(Str::substr($part, 0, 1)))->implode('');
@endphp
<div
    @class(['lu-card', 'lu-who', $extra ?? '', 'is-static' => ! $canEdit, 'lu-card-'.($badge['type'] ?? '') => $badge])
    data-id="{{ $name }}"
    @if($drop ?? true) data-drop="under:{{ $name }}" @endif
    @if($canEdit) role="button" tabindex="0" aria-label="{{ __('Move :name', ['name' => $member->displayName()]) }}" @endif
    wire:key="lu-card-{{ $name }}"
>
    <span class="lu-avatar" aria-hidden="true">{{ $initials }}</span>
    <b class="lu-card-name">{{ $member->displayName() }}</b>
    @if($under)
        <span class="lu-card-count" title="{{ trans_choice(':count person under them|:count people under them', $under, ['count' => $under]) }}">{{ $under }}</span>
    @endif

    @if($badge)
        <button
            type="button"
            class="lu-badge lu-badge-{{ $badge['type'] }}"
            @if($canEdit) data-act="badge" data-id="{{ $name }}" data-note="{{ $badge['note'] ?? '' }}" @else disabled @endif
            title="{{ LineupTree::Badges[$badge['type']]['hint'] }}{{ isset($badge['note']) ? ' — '.$badge['note'] : '' }}"
        >{{ LineupTree::Badges[$badge['type']]['label'] }}</button>
    @endif

    @if($tag)
        <button
            type="button"
            class="lu-ptag"
            @if($canEdit) data-act="tag" data-id="{{ $name }}" @else disabled @endif
            title="{{ $project ? $project['number'].' · '.$project['title'].($project['active'] ? '' : ' ('.__('closed').')') : __('Project no longer on the list') }}"
        >{{ $project['number'] ?? __('Unknown') }}</button>
    @endif

    @if($canEdit && ! ($badge && $tag))
        <span class="lu-card-tools">
            @unless($badge)
                <button type="button" class="lu-badge-add" data-act="badge" data-id="{{ $name }}" title="{{ __('Add a badge') }}" aria-label="{{ __('Add a badge') }}"><i class="ph ph-flag"></i></button>
            @endunless
            @unless($tag)
                <button type="button" class="lu-tag-add" data-act="tag" data-id="{{ $name }}" title="{{ __('Tag a project') }}" aria-label="{{ __('Tag a project') }}"><i class="ph ph-folder-simple-plus"></i></button>
            @endunless
        </span>
    @endif
</div>
