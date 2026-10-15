<?php

namespace ItmoSoft\AuditLog\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use ItmoSoft\AuditLog\Facades\AuditLog;
use ItmoSoft\AuditLog\Models\AuditLog as AuditLogModel;
use ItmoSoft\AuditLog\Tests\TestCase;

class ManualRecordingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('log_name')->nullable();
            $table->string('event');
            $table->text('description')->nullable();
            $table->string('subject_type')->nullable();
            $table->string('subject_id')->nullable();
            $table->string('actor_type')->nullable();
            $table->string('actor_id')->nullable();
            $table->string('actor_name')->nullable();
            $table->json('old_values');
            $table->json('new_values');
            $table->json('properties');
            $table->uuid('batch_uuid')->nullable();
            $table->string('batch_name')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function test_it_records_a_manual_business_event(): void
    {
        AuditLog::record('invoice.approved', [
            'invoice_id' => 125,
            'amount' => 50000,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'invoice.approved',
            'log_name' => 'default',
        ]);

        $entry = AuditLogModel::query()->firstOrFail();

        $this->assertSame(125, $entry->properties['invoice_id']);
        $this->assertSame(50000, $entry->properties['amount']);
    }

    public function test_it_does_not_store_excluded_properties(): void
    {
        AuditLog::record('user.updated', [
            'name' => 'Adeel',
            'password' => 'secret',
        ]);

        $entry = AuditLogModel::query()->firstOrFail();

        $this->assertSame([
            'name' => 'Adeel',
        ], $entry->properties);
    }

    public function test_it_records_an_explicit_actor(): void
    {
        AuditLog::actingAs([
            'type' => 'admin',
            'id' => 'admin-uuid-123',
            'name' => 'Adeel',
        ])->record('invoice.approved');

        $entry = AuditLogModel::query()->firstOrFail();

        $this->assertSame('admin', $entry->actor_type);
        $this->assertSame('admin-uuid-123', $entry->actor_id);
        $this->assertSame('Adeel', $entry->actor_name);
    }

    public function test_it_records_a_system_actor(): void
    {
        AuditLog::asSystem('Nightly Scheduler')
            ->record('maintenance.completed');

        $entry = AuditLogModel::query()->firstOrFail();

        $this->assertNull($entry->actor_type);
        $this->assertNull($entry->actor_id);
        $this->assertSame('Nightly Scheduler', $entry->actor_name);
    }

    public function test_it_groups_manual_events_under_one_activity(): void
    {
        AuditLog::group('Order updated', function (): void {
            AuditLog::record('order.status_changed', [
                'status' => 'confirmed',
            ]);

            AuditLog::record('order.note_added', [
                'note' => 'Customer confirmed.',
            ]);
        });

        $entries = AuditLogModel::query()
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $entries);

        $this->assertNotNull($entries[0]->batch_uuid);
        $this->assertSame(
            $entries[0]->batch_uuid,
            $entries[1]->batch_uuid
        );

        $this->assertSame('Order updated', $entries[0]->batch_name);
        $this->assertSame('Order updated', $entries[1]->batch_name);
    }

    public function test_group_context_is_restored_after_an_exception(): void
    {
        try {
            AuditLog::group('Order update failed', function (): void {
                AuditLog::record('order.status_changed');

                throw new \RuntimeException('Simulated failure');
            });

            $this->fail('Expected the callback to throw.');
        } catch (\RuntimeException $exception) {
            $this->assertSame(
                'Simulated failure',
                $exception->getMessage()
            );
        }

        AuditLog::record('unrelated.event');

        $entries = AuditLogModel::query()
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $entries);

        $this->assertSame('Order update failed', $entries[0]->batch_name);

        $this->assertNotSame(
            $entries[0]->batch_uuid,
            $entries[1]->batch_uuid
        );

        $this->assertNull($entries[1]->batch_name);
    }

    public function test_it_accepts_an_explicit_group_uuid_and_name(): void
    {
        $uuid = (string) \Illuminate\Support\Str::uuid();

        AuditLog::record(
            event: 'order.status_changed',
            properties: ['status' => 'confirmed'],
            batchUuid: $uuid,
            batchName: 'Order confirmation',
        );

        $entry = AuditLogModel::query()->firstOrFail();

        $this->assertSame($uuid, $entry->batch_uuid);
        $this->assertSame('Order confirmation', $entry->batch_name);
    }

    public function test_group_context_can_be_reset(): void
    {
        $context = app(
            \ItmoSoft\AuditLog\Services\AuditGroupContext::class
        );

        $context->run('Temporary group', function () use ($context): void {
            $this->assertSame(
                'Temporary group',
                $context->current()['name']
            );
        });

        $context->reset();

        $this->assertNull($context->current());
    }

    public function test_a_fresh_scoped_context_has_no_active_group(): void
    {
        $first = app(
            \ItmoSoft\AuditLog\Services\AuditGroupContext::class
        );

        $first->run('Temporary activity', function () use ($first): void {
            $this->assertSame(
                'Temporary activity',
                $first->current()['name']
            );
        });

        app()->forgetScopedInstances();

        $second = app(
            \ItmoSoft\AuditLog\Services\AuditGroupContext::class
        );

        $this->assertNull($second->current());
    }

    public function test_a_fresh_scope_resolves_a_new_logger_and_group_context(): void
    {
        $firstLogger = app(
            \ItmoSoft\AuditLog\Services\AuditLogger::class
        );

        $firstContext = app(
            \ItmoSoft\AuditLog\Services\AuditGroupContext::class
        );

        $this->assertSame(
            $firstContext,
            $this->getLoggerContext($firstLogger),
        );

        app()->forgetScopedInstances();

        \ItmoSoft\AuditLog\Facades\AuditLog::clearResolvedInstance(
            \ItmoSoft\AuditLog\Services\AuditLogger::class
        );

        $secondLogger = app(
            \ItmoSoft\AuditLog\Services\AuditLogger::class
        );

        $secondContext = app(
            \ItmoSoft\AuditLog\Services\AuditGroupContext::class
        );

        $this->assertNotSame($firstLogger, $secondLogger);
        $this->assertNotSame($firstContext, $secondContext);
        $this->assertNull($secondContext->current());
    }

    private function getLoggerContext(
        \ItmoSoft\AuditLog\Services\AuditLogger $logger,
    ): \ItmoSoft\AuditLog\Services\AuditGroupContext {
        $property = new \ReflectionProperty(
            \ItmoSoft\AuditLog\Services\AuditLogger::class,
            'groupContext',
        );

        return $property->getValue($logger);
    }
}
