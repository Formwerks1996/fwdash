<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $alias_key
 * @property string $canon_key
 * @property ?string $alias_name
 */
#[Fillable(['alias_key', 'canon_key', 'alias_name', 'created_at'])]
class DeveloperAlias extends Model
{
    public const UPDATED_AT = null;

    protected $primaryKey = 'alias_key';

    protected $keyType = 'string';

    public $incrementing = false;
}
