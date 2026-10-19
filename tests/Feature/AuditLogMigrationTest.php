<?php

namespace ItmoSoft\AuditLog\Tests\Feature;

use Illuminate\Support\Facades\Schema;
use ItmoSoft\AuditLog\Tests\TestCase;

class AuditLogMigrationTest extends TestCase
{
    public function test_migration_creates_the_configured_audit_table(): void
    {
        $this->runAuditLogMigration();

        $this->assertTrue(Schema::hasTable('audit_logs'));

        $this->assertTrue(Schema::hasColumns('audit_logs', [
            'id',
            'event',
            'subject_type',
            'subject_id',
            'actor_type',
            'actor_id',
            'old_values',
            'new_values',
            'properties',
            'batch_uuid',
            'batch_name',
            'created_at',
        ]));
    }

    public function test_migration_rollback_drops_the_audit_table(): void
    {
        $this->runAuditLogMigration();

        $migration = require __DIR__.'/../../database/migrations/create_audit_logs_table.php';
        $migration->down();

        $this->assertFalse(Schema::hasTable('audit_logs'));
    }

    public function test_migration_uses_the_configured_table_name(): void
    {
        config(['audit-log.table_name' => 'custom_audit_entries']);

        $this->runAuditLogMigration();

        $this->assertTrue(Schema::hasTable('custom_audit_entries'));
        $this->assertFalse(Schema::hasTable('audit_logs'));
    }

    private function runAuditLogMigration(): void
    {
        $migration = require __DIR__.'/../../database/migrations/create_audit_logs_table.php';
        $migration->up();
    }
}
