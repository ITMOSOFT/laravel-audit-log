<?php

namespace ItmoSoft\AuditLog\Services;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use ItmoSoft\AuditLog\Models\AuditLog as AuditLogModel;

class AuditLogger
{
    private mixed $explicitActor = null;

    private ?string $systemName = null;

    public function __construct(
        private readonly Application $app,
        private readonly ActorResolver $actorResolver,
        private readonly AuditGroupContext $groupContext,
    ) {
    }

    public function actingAs(mixed $actor): static
    {
        $clone = clone $this;
        $clone->explicitActor = $actor;
        $clone->systemName = null;

        return $clone;
    }

    public function asSystem(string $name): static
    {
        if (trim($name) === '') {
            throw new \InvalidArgumentException(
                'System actor name cannot be empty.'
            );
        }

        $clone = clone $this;
        $clone->explicitActor = null;
        $clone->systemName = $name;

        return $clone;
    }

    /**
     * Record related events under one activity group.
     */
    public function group(string $name, callable $callback): mixed
    {
        return $this->groupContext->run($name, $callback);
    }

    public function record(
        string $event,
        array $properties = [],
        ?string $description = null,
        ?string $logName = null,
        ?Model $subject = null,
        array $oldValues = [],
        array $newValues = [],
        ?string $batchUuid = null,
        ?string $batchName = null,
    ): ?AuditLogModel {
        if (! config('audit-log.enabled', true)) {
            return null;
        }

        if (trim($event) === '') {
            throw new \InvalidArgumentException(
                'Audit event name cannot be empty.'
            );
        }

        $actor = $this->actorResolver->resolve(
            $this->explicitActor,
            $this->systemName,
        );

        $filter = $this->app->make(AttributeFilter::class);
        $group = $this->groupContext->current();

        return (new AuditLogModel())
            ->setConnection(config('audit-log.connection') ?: null)
            ->newQuery()
            ->create([
                'log_name' => $logName
                    ?? config('audit-log.default_log_name', 'default'),
                'event' => $event,
                'description' => $description,
                'subject_type' => $subject?->getMorphClass(),
                'subject_id' => $subject?->getKey(),
                'actor_type' => $actor['type'] ?? null,
                'actor_id' => $actor['id'] ?? null,
                'actor_name' => $actor['name'] ?? null,
                'old_values' => $filter->filter($oldValues),
                'new_values' => $filter->filter($newValues),
                'properties' => $filter->filter($properties),
                'batch_uuid' => $batchUuid
                    ?? $group['uuid']
                    ?? (string) Str::uuid(),
                'batch_name' => $batchName ?? $group['name'] ?? null,
            ]);
    }
}
