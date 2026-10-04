<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class AuditLogger
{
    private const IGNORED_FIELDS = ['created_at', 'updated_at'];

    /**
     * Records one created/updated/deleted event of an Auditable model.
     * Updates only store the fields that changed; updates without a real
     * change are skipped.
     */
    public static function log(Model $model, string $event): void
    {
        $oldValues = null;
        $newValues = null;

        if ($event === 'created') {
            $newValues = Arr::except($model->getAttributes(), self::IGNORED_FIELDS);
        } elseif ($event === 'deleted') {
            $oldValues = Arr::except($model->getAttributes(), self::IGNORED_FIELDS);
        } else {
            $newValues = Arr::except($model->getChanges(), self::IGNORED_FIELDS);
            if (empty($newValues)) {
                return;
            }
            $oldValues = [];
            foreach (array_keys($newValues) as $field) {
                $oldValues[$field] = $model->getRawOriginal($field);
            }
        }

        self::write([
            'user_id' => auth()->id(),
            'auditable_type' => $model->auditType(),
            'auditable_id' => $model->getKey(),
            'label' => $model->auditLabel(),
            'event' => $event,
            'old_values' => $oldValues === null ? null : LogSanitizer::clean($oldValues),
            'new_values' => $newValues === null ? null : LogSanitizer::clean($newValues),
        ]);
    }

    /**
     * Records a login, failed login or logout. A failed login for an unknown
     * email has no user, so only the email is kept.
     */
    public static function logAuth(string $event, ?User $user, string $email): void
    {
        self::write([
            'user_id' => $user?->id,
            'auditable_type' => 'login',
            'auditable_id' => $user?->id,
            'label' => $user?->name ?? $email,
            'event' => $event,
            'old_values' => null,
            'new_values' => ['email' => $email],
        ]);
    }

    private static function write(array $attributes): void
    {
        $request = request();

        AuditLog::create($attributes + [
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'url' => $request?->fullUrl(),
        ]);
    }
}
