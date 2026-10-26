<?php

namespace App\Actions\BusinessDevelopment;

use App\Enums\PursuitStage;
use App\Models\Pursuit;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SavePursuitAction
{
    public function __construct(protected AuditLogger $audit) {}

    /** @param  array<string, mixed>  $attributes  any subset of developer, project, fee, stage, owner, note */
    public function execute(array $attributes, ?Pursuit $pursuit = null): Pursuit
    {
        $attributes = $this->validated($attributes);

        return DB::transaction(function () use ($attributes, $pursuit) {
            if (! $pursuit) {
                $attributes = ['developer' => '', 'stage' => PursuitStage::Contacted->value, ...$attributes];
                $pursuit = Pursuit::create($attributes);

                $this->audit->log('insert', 'pursuits', $pursuit->id, after: $attributes);

                return $pursuit;
            }

            if ($attributes === []) {
                return $pursuit;
            }

            $before = $pursuit->only(['developer', 'project', 'fee', 'stage', 'owner', 'note']);
            $pursuit->update($attributes);
            $pursuit->touch();

            $this->audit->log('update', 'pursuits', $pursuit->id, before: $before, after: $attributes);

            return $pursuit;
        });
    }

    public function delete(Pursuit $pursuit): void
    {
        DB::transaction(function () use ($pursuit) {
            $before = $pursuit->only(['developer', 'project', 'fee', 'stage', 'owner', 'note']);
            $pursuit->delete();

            $this->audit->log('delete', 'pursuits', $pursuit->id, before: $before);
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function validated(array $attributes): array
    {
        $attributes = collect($attributes)
            ->only(['developer', 'project', 'fee', 'stage', 'owner', 'note'])
            ->map(fn (mixed $value) => is_string($value) ? trim($value) : $value)
            ->map(fn (mixed $value, string $field) => $value === '' && $field !== 'developer' ? null : $value)
            ->all();

        if (array_key_exists('fee', $attributes) && is_string($attributes['fee'])) {
            $attributes['fee'] = str_replace(['$', ',', ' '], '', $attributes['fee']);
        }

        $validator = validator($attributes, [
            'developer' => ['sometimes', 'string', 'max:255'],
            'project' => ['nullable', 'string', 'max:255'],
            'fee' => ['nullable', 'numeric', 'min:0'],
            'stage' => ['sometimes', Rule::enum(PursuitStage::class)],
            'owner' => ['nullable', Rule::in(config('bizdev.owners'))],
            'note' => ['nullable', 'string', 'max:2000'],
        ], [
            'fee.numeric' => __('":value" is not a number.', ['value' => $attributes['fee'] ?? '']),
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $attributes;
    }
}
