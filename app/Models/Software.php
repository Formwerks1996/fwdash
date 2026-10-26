<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property ?string $division
 * @property ?string $price
 * @property string $currency
 * @property string $cycle
 * @property ?int $seats
 * @property ?int $seats_used
 * @property ?string $description
 * @property bool $active
 */
#[Fillable(['name', 'division', 'price', 'currency', 'cycle', 'seats', 'seats_used', 'description', 'note', 'active'])]
class Software extends Model
{
    public const Currencies = ['CAD', 'USD', 'EUR', 'GBP'];

    public const Cycles = ['Monthly', 'Annual'];

    protected $table = 'software';

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'seats' => 'integer',
            'seats_used' => 'integer',
        ];
    }

    public function annual(): ?float
    {
        if ($this->price === null) {
            return null;
        }

        return (float) $this->price * ($this->cycle === 'Annual' ? 1 : 12);
    }

    public function idleSeats(): ?int
    {
        if ($this->seats === null || $this->seats_used === null) {
            return null;
        }

        return max(0, $this->seats - $this->seats_used);
    }

    /** Only a single-product row can be priced per seat; a middot marks a bundle such as Adobe. */
    public function idleCost(): ?float
    {
        $idle = $this->idleSeats();

        if (! $idle || ! $this->seats || str_contains((string) $this->description, '·') || $this->annual() === null) {
            return null;
        }

        return $this->annual() / $this->seats * $idle;
    }
}
