<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string $key
 * @property ?string $city
 * @property ?string $note
 */
#[Fillable(['name', 'key', 'city', 'note'])]
class Developer extends Model {}
