<?php

namespace App\Livewire\BusinessDevelopment;

use App\Models\OutreachProspect;
use Illuminate\View\View;
use Livewire\Component;

class OutreachShow extends Component
{
    public OutreachProspect $prospect;

    /** @var array<int, string> */
    public array $keys = [];

    public function mount(string $prospectKey): void
    {
        $this->prospect = OutreachProspect::with('projects')->where('key', $prospectKey)->firstOrFail();

        $this->keys = session('outreach.order', []);
    }

    public static function safeUrl(?string $url): ?string
    {
        return $url && preg_match('#^https?://#i', $url) ? $url : null;
    }

    public function render(): View
    {
        $position = array_search($this->prospect->key, $this->keys, true);

        return view('livewire.business-development.outreach-show', [
            'position' => $position === false ? null : $position,
            'previous' => $position === false ? null : ($this->keys[$position - 1] ?? null),
            'next' => $position === false ? null : ($this->keys[$position + 1] ?? null),
            'typeColors' => OutreachIndex::typeColors(),
        ])->title($this->prospect->name);
    }
}
