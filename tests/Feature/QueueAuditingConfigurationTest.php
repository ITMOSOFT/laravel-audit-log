<?php

namespace ItmoSoft\AuditLog\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use ItmoSoft\AuditLog\Models\AuditLog as AuditLogModel;
use ItmoSoft\AuditLog\Tests\TestCase;

class QueueAuditingConfigurationTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('audit-log.queue.enabled', false);
    }

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

    public function test_it_does_not_register_queue_auditing_when_disabled(): void
    {
        Event::dispatch(new JobQueued(
            'redis',
            'emails',
            'job-disabled',
            'App\\Jobs\\SendInvoice',
            '{}',
            null,
        ));

        $this->assertSame(
            0,
            AuditLogModel::query()->count(),
        );
    }
}