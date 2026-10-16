# ITMOSOFT Laravel Audit Log

Audit logging for Laravel applications, including manual business events, automatic Eloquent model auditing, grouped activities, and queue lifecycle events.

## Requirements

- PHP 8.3+
- Continuous support for Laravel 12 or 13

## Installation

```bash
composer require itmosoft/laravel-audit-log
php artisan vendor:publish --tag=audit-log-config
php artisan migrate
```

Laravel package auto-discovery registers the service provider.

## Configuration

Review `config/audit-log.php` before enabling auditing in production. Queue auditing is disabled by default.

```php
'queue' => [
    'enabled' => true,
    'include_exception_message' => false,
    'events' => [
        'queued',
        'processing',
        'processed',
        'failed',
    ],
],
```

Available queue event filters are `queued`, `processing`, `processed`, and `failed`.

Failed-job exception classes are recorded. Exception messages are omitted by default because they may contain sensitive information. Enable `include_exception_message` only when your logging policy permits it. Captured messages are limited to 1,000 characters.

## Manual audit logging

```php
use ItmoSoft\AuditLog\Facades\AuditLog;

AuditLog::record('invoice.approved', [
    'invoice_id' => 125,
    'amount' => 50000,
]);
```

You can also supply a description, log name, subject, old values, new values, and batch metadata through the supported named arguments.

### Actor information

```php
AuditLog::actingAs([
    'type' => 'admin',
    'id' => 'admin-123',
    'name' => 'Administrator',
])->record('invoice.approved');

AuditLog::asSystem('Nightly Scheduler')
    ->record('maintenance.completed');
```

## Automatic Eloquent model auditing

Use the `Auditable` trait on a model:

```php
use Illuminate\Database\Eloquent\Model;
use ItmoSoft\AuditLog\Concerns\Auditable;

class Invoice extends Model
{
    use Auditable;
}
```

The trait records configured model lifecycle events, including creation, updates, and deletion. Old and new attribute values are filtered according to package configuration. Models can opt out by overriding `shouldAudit()`.

## Group related activities

```php
AuditLog::group('Order updated', function () use ($order): void {
    AuditLog::record('order.status_changed', [
        'status' => 'confirmed',
    ]);

    AuditLog::record('order.note_added', [
        'note' => 'Customer confirmed.',
    ]);
});
```

Entries in a group share a batch UUID and batch name. Nested groups reuse the outer context, which is restored when the callback completes or throws. Standalone entries receive their own batch UUID.

## Query audit entries

```php
use ItmoSoft\AuditLog\Models\AuditLog;

$entries = AuditLog::query()
    ->forEvent('invoice.approved')
    ->latest('created_at')
    ->get();
```

Available query scopes include:

- `forSubject($model)`
- `byActor($model)`
- `forEvent($event)`
- `between($start, $end)`

Retrieve grouped activities for a subject:

```php
$activities = AuditLog::activityGroupsForSubject($order);
```

Each activity group includes its batch UUID, name, timestamp, and associated events.

## Queue auditing

When enabled, the package listens for Laravel queue lifecycle events and writes audit entries to the `queue` log. Recorded metadata may include the connection, queue, job ID, and job name. Queue payloads are not stored by this integration. Job names are best-effort metadata and may vary by queue driver.

## Database considerations

Subject IDs are stored as strings to support numeric, UUID, and ULID model keys.

**Existing installations:** changing an already-run migration file does not modify existing databases. If a previous release or deployed application has already run this migration, schema changes must be delivered through a separate upgrade migration.

## Testing

```bash
composer test
composer test:unit
composer test:feature
```

## Security notes

Audit records may contain sensitive information. Restrict access to the audit table and review attribute-filtering and exception-message settings before production use. Bulk Eloquent query updates and deletes do not dispatch per-model lifecycle events.

## License

MIT
