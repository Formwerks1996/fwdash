<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\User;

/*
 * Every write lands in the change log with who made it and the record before
 * and after, so history can always be read back.
 */
class AuditLogger
{
    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function log(
        string $action,
        string $table,
        string|int|null $key,
        ?array $before = null,
        ?array $after = null,
        ?User $user = null,
    ): AuditLog {
        $user ??= auth()->user();

        return AuditLog::create([
            'user_id' => $user?->id,
            'actor' => $user->name ?? 'system',
            'action' => $action,
            'table_name' => $table,
            'record_key' => $key === null ? null : (string) $key,
            'before' => $before,
            'after' => $after,
            'created_at' => now(),
        ]);
    }
}
