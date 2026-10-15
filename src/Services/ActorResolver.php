<?php

namespace ItmoSoft\AuditLog\Services;

use Illuminate\Contracts\Auth\Factory as AuthFactory;
use ItmoSoft\AuditLog\Contracts\ActorResolver as ActorResolverContract;

class ActorResolver implements ActorResolverContract
{
    public function __construct(private readonly mixed $app)
    {
    }

    public function resolve(mixed $explicitActor = null, ?string $systemName = null): ?array
    {
        if ($systemName !== null) {
            return ['type' => null, 'id' => null, 'name' => $systemName];
        }

        $actor = $explicitActor;

        if ($actor === null && config('audit-log.auto_discover_actor', true) && $this->app->bound('auth')) {
            $actor = $this->app->make(AuthFactory::class)->guard()->user();
        }

        if ($actor === null) {
            return null;
        }

        if (is_object($actor) && method_exists($actor, 'getMorphClass') && method_exists($actor, 'getKey')) {
            return [
                'type' => $actor->getMorphClass(),
                'id' => $actor->getKey(),
                'name' => null,
            ];
        }

        if (is_array($actor) && isset($actor['type'])) {
            return [
                'type' => (string) $actor['type'],
                'id' => $actor['id'] ?? null,
                'name' => isset($actor['name']) ? (string) $actor['name'] : null,
            ];
        }

        throw new \InvalidArgumentException(
            'Audit actor must be an Eloquent model or an array containing a type key. Use asSystem() for system actions.'
        );
    }
}
