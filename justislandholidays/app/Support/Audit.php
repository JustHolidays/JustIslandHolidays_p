<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Writes to the append-only audit_log. Actor null means a system action (webhook, nightly job). */
final class Audit
{
    /**
     * @param  array<string, mixed>  $detail  usually ['before' => [...], 'after' => [...], 'reason' => ...]
     */
    public static function record(?User $actor, string $action, Model|string $entity, int|string|null $entityId = null, array $detail = []): AuditLog
    {
        if ($entity instanceof Model) {
            $entityId ??= $entity->getKey();
            $entity = str($entity->getTable())->singular()->toString();
        }

        return AuditLog::create([
            'actor_id' => $actor?->id,
            'action' => $action,
            'entity' => $entity,
            'entity_id' => $entityId === null ? null : (string) $entityId,
            'detail' => $detail === [] ? null : $detail,
        ]);
    }
}
