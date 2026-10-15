<?php

namespace ItmoSoft\AuditLog\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \ItmoSoft\AuditLog\Models\AuditLog|null record(string $event, array $properties = [], ?string $description = null, ?string $logName = null)
 * @method static \ItmoSoft\AuditLog\Services\AuditLogger actingAs(mixed $actor)
 * @method static \ItmoSoft\AuditLog\Services\AuditLogger asSystem(string $name)
 *
 * @see \ItmoSoft\AuditLog\Services\AuditLogger
 */
class AuditLog extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \ItmoSoft\AuditLog\Services\AuditLogger::class;
    }
}
