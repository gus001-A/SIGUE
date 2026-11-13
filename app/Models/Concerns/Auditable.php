<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;

/**
 * Trazabilidad de operaciones sensibles: guarda en audit_logs los valores
 * anteriores y nuevos de cada alta, cambio o baja.
 */
trait Auditable
{
    protected static array $auditIgnore = ['password', 'remember_token', 'created_at', 'updated_at'];

    public static function bootAuditable(): void
    {
        static::created(fn ($m) => $m->writeAudit('created', [], $m->auditClean($m->getAttributes())));

        static::updated(function ($m) {
            $changes = $m->auditClean($m->getChanges());
            if (! $changes) {
                return;
            }
            $old = array_intersect_key($m->getOriginal(), $changes);
            $m->writeAudit('updated', $old, $changes);
        });

        static::deleted(fn ($m) => $m->writeAudit('deleted', $m->auditClean($m->getOriginal()), []));
    }

    protected function auditClean(array $values): array
    {
        return array_diff_key($values, array_flip(static::$auditIgnore));
    }

    protected function writeAudit(string $action, array $old, array $new): void
    {
        try {
            AuditLog::record($action, $this, $this->auditLabel(), $old, $new);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function auditLabel(): string
    {
        return class_basename($this) . ' #' . $this->getKey();
    }
}
