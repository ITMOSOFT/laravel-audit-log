<?php

namespace ItmoSoft\AuditLog\Tests\Feature;

use Illuminate\Contracts\Queue\Job;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use ItmoSoft\AuditLog\Models\AuditLog as AuditLogModel;
use ItmoSoft\AuditLog\Tests\TestCase;

class QueueAuditingEventFilterTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('audit-log.queue.enabled', true);
        $app['config']->set('audit-log.queue.events', ['failed']);
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

    public function test_it_registers_only_configured_queue_events(): void
    {
        Event::dispatch(new JobQueued(
            'redis',
            'emails',
            'job-queued',
            'App\\Jobs\\SendInvoice',
            '{}',
            null,
        ));

        $job = \Mockery::mock(Job::class);

        $job->shouldReceive('payload')
            ->zeroOrMoreTimes()
            ->andReturn([]);

        $job->shouldReceive('getQueue')
            ->once()
            ->andReturn('emails');

        $job->shouldReceive('getJobId')
            ->once()
            ->andReturn('job-failed');

        $job->shouldReceive('resolveName')
            ->once()
            ->andReturn('App\\Jobs\\SendInvoice');

        Event::dispatch(new JobFailed(
            'redis',
            $job,
            new \RuntimeException('Payment failed'),
        ));

        $entries = AuditLogModel::query()->get();

        $this->assertCount(1, $entries);
        $this->assertSame('queue.job_failed', $entries->first()->event);
        $this->assertSame(
            'job-failed',
            $entries->first()->properties['job_id'],
        );
    }
}