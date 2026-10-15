<?php

namespace ItmoSoft\AuditLog\Contracts;

interface ActorResolver
{
    /**
     * Return a normalized actor identity, or null when no actor is known.
     *
     * @return array{type: ?string, id: int|string|null, name: ?string}|null
     */
    public function resolve(mixed $explicitActor = null, ?string $systemName = null): ?array;
}
