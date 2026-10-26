<?php

namespace App\Livewire;

use App\Models\StickyNote;
use Illuminate\Contracts\Routing\UrlRoutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Shared sticky notes, pinned to the page (route and its record) they were
 * dropped on. Everyone who signs in sees and can change them; a deleted note
 * can be restored.
 *
 * @property-read Collection<int, StickyNote> $notes
 * @property-read Collection<int, StickyNote> $allNotes
 */
class StickyNotes extends Component
{
    #[Locked]
    public string $target = '';

    #[Locked]
    public ?string $arg = null;

    #[Locked]
    public string $label = '';

    public bool $drawer = false;

    public ?string $lastDeleted = null;

    public function mount(?string $label = null): void
    {
        $route = request()->route();

        $this->target = (string) $route?->getName();
        $firstParameter = $route?->parameterNames()[0] ?? null;
        $value = $firstParameter === null ? null : $route->parameter($firstParameter);
        $this->arg = match (true) {
            $value instanceof UrlRoutable => (string) $value->getRouteKey(),
            is_string($value) => $value,
            default => null,
        };
        $this->label = mb_substr((string) ($label ?? $this->target), 0, 160);
    }

    /** @return Collection<int, StickyNote> */
    #[Computed]
    public function notes(): Collection
    {
        return StickyNote::where('place_target', $this->target)
            ->where('place_arg', $this->arg)
            ->orderBy('created_at')
            ->get();
    }

    /** @return Collection<int, StickyNote> */
    #[Computed]
    public function allNotes(): Collection
    {
        return StickyNote::latest('updated_at')->get();
    }

    public function create(string $body, float $x, int $y): void
    {
        StickyNote::create([
            'place_target' => mb_substr($this->target, 0, 40),
            'place_arg' => $this->arg === null ? null : mb_substr($this->arg, 0, 120),
            'place_label' => $this->label,
            'body' => $this->validBody($body),
            'author' => mb_substr((string) auth()->user()?->name, 0, 60),
            'user_id' => auth()->id(),
            'pos_x' => round(min(max($x, 0), 1), 4),
            'pos_y' => min(max($y, 0), 100000),
        ]);

        $this->forgetNotes();
    }

    public function edit(string $id, string $body): void
    {
        $note = $this->find($id);
        $body = $this->validBody($body);

        if ($note->body === $body) {
            return;
        }

        $note->update(['body' => $body, 'edited_at' => now()]);

        $this->forgetNotes();
    }

    public function move(string $id, float $x, int $y): void
    {
        $this->find($id)->update([
            'pos_x' => round(min(max($x, 0), 1), 4),
            'pos_y' => min(max($y, 0), 100000),
        ]);

        $this->forgetNotes();
    }

    public function delete(string $id): void
    {
        $note = $this->find($id);
        $note->update(['deleted_by' => mb_substr((string) auth()->user()?->name, 0, 60)]);
        $note->delete();

        $this->lastDeleted = $id;
        $this->forgetNotes();
    }

    public function restore(): void
    {
        $note = StickyNote::onlyTrashed()->find($this->lastDeleted);

        if (! $note) {
            throw ValidationException::withMessages(['note' => __('That note can no longer be restored.')]);
        }

        $note->restore();
        $note->update(['deleted_by' => null]);

        $this->lastDeleted = null;
        $this->forgetNotes();
    }

    public function render(): View
    {
        return view('livewire.sticky-notes');
    }

    protected function find(string $id): StickyNote
    {
        $note = StickyNote::find($id);

        if (! $note) {
            throw ValidationException::withMessages(['note' => __('That note no longer exists (someone may have deleted it).')]);
        }

        return $note;
    }

    protected function validBody(string $body): string
    {
        $body = trim($body);

        if ($body === '') {
            throw ValidationException::withMessages(['note' => __('The note is empty.')]);
        }

        if (mb_strlen($body) > StickyNote::MaxLength) {
            throw ValidationException::withMessages(['note' => __('That note is too long (4,000 characters at most).')]);
        }

        return $body;
    }

    protected function forgetNotes(): void
    {
        unset($this->notes, $this->allNotes);
    }
}
