<?php

namespace ItmoSoft\AuditLog\Concerns;

use Illuminate\Database\Eloquent\Model;
use ItmoSoft\AuditLog\Facades\AuditLog;

trait Auditable
{
    /**
     * Register automatic model auditing callbacks.
     */
    public static function bootAuditable(): void
    {
        static::created(function (Model $model): void {
            $model->writeAuditEntry(
                event: 'created',
                oldValues: [],
                newValues: $model->getAttributes(),
            );
        });

        static::updated(function (Model $model): void {
            $changes = $model->getChanges();

            if ($changes === []) {
                return;
            }

            $oldValues = [];
            $newValues = [];

            foreach (array_keys($changes) as $key) {
                $oldValues[$key] = $model->getRawOriginal($key);
                $newValues[$key] = $changes[$key];
            }

            $model->writeAuditEntry(
                event: 'updated',
                oldValues: $oldValues,
                newValues: $newValues,
            );
        });

        static::deleted(function (Model $model): void {
            $model->writeAuditEntry(
                event: 'deleted',
                oldValues: $model->getAttributes(),
                newValues: [],
            );
        });
    }

    /**
     * Override this method on a model to disable auditing conditionally.
     */
    public function shouldAudit(): bool
    {
        return true;
    }

    /**
     * Record the model event.
     */
    protected function writeAuditEntry(
        string $event,
        array $oldValues,
        array $newValues,
    ): void {
        if (! $this->shouldAudit()) {
            return;
        }

        if (! config('audit-log.enabled', true)) {
            return;
        }

        $configuredEvents = config(
            'audit-log.model_events',
            ['created', 'updated', 'deleted'],
        );

        if (! in_array($event, $configuredEvents, true)) {
            return;
        }

        AuditLog::record(
            event: $event,
            subject: $this,
            oldValues: $oldValues,
            newValues: $newValues,
        );
    }
}
