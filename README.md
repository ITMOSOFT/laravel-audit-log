# ITMOSOFT Laravel Audit Log

Auditing for Eloquent models, business events, and Laravel queue lifecycle events.

> Initial development scaffold. Do not treat this as a production-ready v1.0.0 release yet.

## Requirements

- PHP 8.3+
- Laravel 12 or 13 (compatibility must be verified in CI)

## Installation

```bash
composer require itmosoft/laravel-audit-log
php artisan vendor:publish --tag=audit-log-config
php artisan migrate
```

## Manual business events

```php
use ItmoSoft\AuditLog\Facades\AuditLog;

AuditLog::record('invoice.approved', [
    'invoice_id' => 125,
    'amount' => 50000,
]);

AuditLog::asSystem('nightly-import')->record('products.imported', [
    'count' => 250,
]);
```

Explicit model actors:

```php
AuditLog::actingAs($admin)->record('permissions.changed', [
    'role' => 'manager',
]);
```

## Scope

The first development milestone establishes package metadata, service provider, configuration, migration, audit model, actor resolution, attribute filtering, and a manual recorder. Automatic Eloquent listeners, queue event integration, retention command, and a complete test matrix are subsequent implementation steps.

## Security notes

- Never store complete job payloads or raw exception messages without reviewing for secrets and personal information.
- Audit records should be treated as sensitive data and access-controlled by the host application.
- Bulk Eloquent query updates/deletes do not dispatch per-model lifecycle events.
