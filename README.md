# Laravel Audit Log

A lightweight Laravel package for tracking Eloquent model changes, recording application activities, grouping related events, and auditing queue execution.

`itmosoft/laravel-audit-log` helps you understand what changed, when it changed, and which related actions belong to the same activity.

## Features

- **Automatic model auditing** — record supported Eloquent model events using the `Auditable` trait.
- **Manual audit recording** — record business events that do not correspond to a model lifecycle event.
- **Activity grouping** — group related model changes and manual audit entries under a single activity.
- **Actor resolution** — associate audit records with the responsible user when an actor is available.
- **Sensitive attribute filtering** — avoid recording configured sensitive model attributes.
- **Queue auditing** — integrate audit tracking with supported Laravel queue lifecycle events.
- **Query scopes** — retrieve audit records by subject, actor, event, or date range.
- **Activity history** — retrieve a subject's audit history grouped into activities.
- **Laravel compatibility** — CI coverage for Laravel 12 and Laravel 13.

## Requirements

- PHP and Laravel versions supported by the package's `composer.json`.
- A configured Laravel database connection.
- A queue connection and running worker if you use asynchronous jobs.

## Installation

Install the package using Composer:

```bash
composer require itmosoft/laravel-audit-log
```

Run the Laravel database migrations:

```bash
php artisan migrate
```

The package includes an audit-log migration. If your installation requires publishing package configuration or migrations, use the publish tags exposed by the installed package's service provider.

### Configuration

The package uses the `audit-log` configuration namespace. The audit table name can be configured through `audit-log.table_name`.

For example, to use a custom table name in application configuration:

```php
// config/audit-log.php
return [
    // Preserve the other options provided by the package.
    'table_name' => 'audit_entries',
];
```

Keep the remaining settings from the package's published configuration when making this change. After changing the table name, ensure the database schema and configured table name are consistent.

## Automatic model auditing

Add the package's `Auditable` trait to an Eloquent model to enable automatic auditing.

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use ItmoSoft\AuditLog\Concerns\Auditable;

class Product extends Model
{
    use Auditable;

    protected $fillable = [
        'name',
        'price',
        'status',
    ];
}
```

### Create a model

```php
$product = Product::create([
    'name' => 'Keyboard',
    'price' => 2500,
    'status' => 'active',
]);
```

The package records the supported model creation event.

### Update a model

```php
$product->update([
    'price' => 3000,
]);
```

The audit history records the supported update event and its change data.

### Delete a model

```php
$product->delete();
```

The package can record supported deletion events as well.

> **Security note:** Audit logs may contain sensitive business information. Configure attribute filtering appropriately, and avoid recording passwords, access tokens, secrets, or other sensitive values.

## Manual audit recording

Some important application activities do not map directly to Eloquent model events. Use the `AuditLog` facade to record these events explicitly.

```php
use ItmoSoft\AuditLog\Facades\AuditLog;

AuditLog::record('order.payment_received', [
    'payment_reference' => 'PAY-1001',
    'amount' => 5000,
]);
```

You can associate a manual entry with an Eloquent model:

```php
use ItmoSoft\AuditLog\Facades\AuditLog;

AuditLog::record(
    'order.status_changed',
    [
        'from' => 'pending',
        'to' => 'paid',
    ],
    subject: $order,
);
```

This is useful for business events such as:

- Payment received
- Order approved
- Invoice generated
- Account suspended
- Inventory adjusted
- Permission granted

Use stable, descriptive event names so that audit records are easy to query and understand.

## Group related activity

A single business operation may cause several model updates and manual audit entries. Group them together to make the resulting history easier to understand.

```php
use ItmoSoft\AuditLog\Facades\AuditLog;

AuditLog::group('Order processed', function () use ($order) {
    $order->update([
        'status' => 'processing',
    ]);

    AuditLog::record(
        'order.processing_started',
        ['source' => 'admin'],
        subject: $order,
    );
});
```

The package associates supported audit entries created inside the group with a shared batch UUID and group name.

### Nested groups

Nested groups can reuse the outer group, keeping a multi-step operation under one activity.

```php
AuditLog::group('Order fulfilled', function () use ($order) {
    AuditLog::record(
        'order.picking_started',
        subject: $order,
    );

    AuditLog::group('Packing order', function () use ($order) {
        AuditLog::record(
            'order.packed',
            subject: $order,
        );
    });
});
```

Grouping is particularly useful when one business operation triggers several model events and manual records.

## Retrieve audit records

Use the audit-log model to query stored entries.

```php
use ItmoSoft\AuditLog\Models\AuditLog as AuditLogEntry;
```

### Filter by subject

```php
$entries = AuditLogEntry::forSubject($order)->get();
```

### Filter by actor

```php
$entries = AuditLogEntry::byActor($user)->get();
```

### Filter by event

```php
$entries = AuditLogEntry::forEvent('updated')->get();
```

### Filter by date range

```php
$entries = AuditLogEntry::between(
    now()->subDays(7),
    now(),
)->get();
```

These scopes can also be combined when you need to narrow down a query.

```php
$entries = AuditLogEntry::query()
    ->forSubject($order)
    ->forEvent('updated')
    ->between(now()->subMonth(), now())
    ->get();
```

## Retrieve grouped activity history

For a model's user-facing activity timeline, retrieve its audit history grouped into activities.

```php
use ItmoSoft\AuditLog\Models\AuditLog as AuditLogEntry;

$activities = AuditLogEntry::activityGroupsForSubject($order);
```

Each activity contains information such as:

- `batch_uuid` — the activity's batch identifier, or `null` for an ungrouped entry.
- `name` — the activity group name, when available.
- `created_at` — the activity timestamp.
- `events` — the audit entries belonging to that activity.

Example of iterating over activities:

```php
foreach ($activities as $activity) {
    echo $activity['name'] ?? 'Individual activity';

    foreach ($activity['events'] as $event) {
        echo $event->event;
    }
}
```

Activities are returned newest first. Entries without a batch UUID remain separate activities instead of being merged into one group.

This API is useful for building order histories, administrative timelines, and audit-history pages.

## Queue auditing

The package includes queue-listener integration for supported queue lifecycle events. It also supports auditing model changes made while queued jobs execute.

For example, consider a queued job that updates an order:

```php
<?php

namespace App\Jobs;

use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessOrder implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $orderId,
    ) {}

    public function handle(): void
    {
        $order = Order::findOrFail($this->orderId);

        $order->update([
            'status' => 'processing',
        ]);
    }
}
```

Dispatch the job:

```php
ProcessOrder::dispatch($order->id);
```

Run a Laravel queue worker:

```bash
php artisan queue:work
```

If `Order` uses the `Auditable` trait, its supported model changes can be recorded when the job updates it. The package's queue integration handles the queue events supported by its listener implementation.

### Production considerations

- Configure a working Laravel queue connection.
- Keep queue workers running under an appropriate process manager.
- Monitor failed jobs using Laravel's queue tooling.
- Test audit behavior for the queue connection and job lifecycle used by your application.
- Avoid assuming that every queue event or every change made through bulk database queries is automatically audited.

## Testing

Run the package's test suite:

```bash
composer test
```

Validate the Composer configuration:

```bash
composer validate --strict
```

Check dependencies for known security advisories:

```bash
composer audit
```

The package's feature tests cover automatic model auditing, grouped activities, queue auditing, migrations, and audit query scopes.

## Best practices

1. **Exclude sensitive data.** Never treat audit logs as a safe place for credentials or secrets.
2. **Use descriptive event names.** Prefer `order.payment_received` over vague labels such as `action`.
3. **Group related operations.** Use activity grouping when a single business operation produces multiple