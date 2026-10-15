<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Central audit logging for sensitive actions.
 */
class AuditService
{
    public static function log(
        string $action,
        ?User $actor,
        ?string $entityType = null,
        string|int|null $entityId = null,
        ?array $before = null,
        ?array $after = null,
    ): AuditLog {
        return AuditLog::create([
            'actor_user_id' => $actor?->id,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId !== null ? (string) $entityId : null,
            'before' => $before,
            'after' => $after,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }

    public static function logModel(string $action, ?User $actor, Model $model, ?array $before = null): AuditLog
    {
        return static::log(
            $action,
            $actor,
            $model->getMorphClass() === Model::class ? get_class($model) : $model->getTable(),
            (string) $model->getKey(),
            $before,
            $model->fresh()?->toArray()
        );
    }
}
