<?php

namespace App\Models\Concerns;

use App\Support\AuditLogger;

/**
 * Writes an audit_logs row for every create, update and delete done through
 * the model. Query builder writes (e.g. `Product::where(...)->update()` or
 * `$transaction->product()->increment()`) skip model events and are not
 * audited, so always change audited records through a model instance.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn ($model) => AuditLogger::log($model, 'created'));
        static::updated(fn ($model) => AuditLogger::log($model, 'updated'));
        static::deleted(fn ($model) => AuditLogger::log($model, 'deleted'));
    }

    // Short type name stored in audit_logs.auditable_type, e.g. "product".
    abstract public function auditType(): string;

    // Human readable name of the record, kept in the log after it is deleted.
    public function auditLabel(): ?string
    {
        return $this->getAttribute('name');
    }
}
