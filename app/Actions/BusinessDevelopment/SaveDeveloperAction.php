<?php

namespace App\Actions\BusinessDevelopment;

use App\Models\Developer;
use App\Support\AuditLogger;
use App\Support\DeveloperKey;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveDeveloperAction
{
    public function __construct(protected AuditLogger $audit) {}

    /**
     * Adds a developer, or updates the one with the same key. Only the given
     * extras (city, note) are written, so a billing-only developer gets a row
     * the first time someone types a city for it.
     *
     * @param  array{city?: ?string, note?: ?string}  $extras
     */
    public function execute(string $name, array $extras = []): Developer
    {
        $name = trim($name);

        if ($name === '') {
            throw ValidationException::withMessages(['name' => __('A developer needs a name.')]);
        }

        $key = DeveloperKey::make($name);

        if ($key === '') {
            throw ValidationException::withMessages(['name' => __('That name is only punctuation.')]);
        }

        $extras = array_map(fn (?string $value) => filled($value) ? trim($value) : null, $extras);
        $after = ['name' => $name, ...$extras];

        return DB::transaction(function () use ($key, $after) {
            $developer = Developer::firstWhere('key', $key);

            if (! $developer) {
                $developer = Developer::create([...$after, 'key' => $key]);

                $this->audit->log('insert', 'developers', $key, after: $after);

                return $developer;
            }

            $before = $developer->only(['name', 'city', 'note']);
            $developer->update($after);

            $this->audit->log('update', 'developers', $key, before: $before, after: $after);

            return $developer;
        });
    }
}
