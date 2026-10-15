<?php

namespace ItmoSoft\AuditLog\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use ItmoSoft\AuditLog\Models\AuditLog as AuditLogModel;
use ItmoSoft\AuditLog\Tests\TestCase;

class QueueAuditingTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // These settings must be present before the provider boots.
        $app['config']->set('audit-log.queue.enabled', true);
        $app['config']->set('audit-log.queue.events', [
            'queued',
            'processing',
            'processed',
            'failed',
        ]);
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

    public function test_it_records_a_queued_job(): void
    {
        Event::dispatch(new JobQueued(
            'redis',
            'emails',
            'job-123',
            'App\\Jobs\\SendInvoice',
            '{}',
            null,
        ));

        $entry = AuditLogModel::query()->firstOrFail();

        $this->assertSame('queue.job_queued', $entry->event);
        $this->assertSame('queue', $entry->log_name);
        $this->assertSame('redis', $entry->properties['connection']);
        $this->assertSame('emails', $entry->properties['queue']);
        $this->assertSame('job-123', $entry->properties['job_id']);
        $this->assertSame(
            'App\\Jobs\\SendInvoice',
            $entry->properties['job_name'],
        );
    }

    public function test_it_records_processing_and_processed_jobs(): void
    {
        $job = \Mockery::mock(Job::class);

        $job->shouldReceive('payload')
          ->zeroOrMoreTimes()
          ->andReturn([]);

        $job->shouldReceive('getQueue')
            ->twice()
            ->andReturn('emails');

        $job->shouldReceive('getJobId')
            ->twice()
            ->andReturn('job-456');

        $job->shouldReceive('resolveName')
            ->twice()
            ->andReturn('App\\Jobs\\SendInvoice');

        Event::dispatch(new JobProcessing('redis', $job));
        Event::dispatch(new JobProcessed('redis', $job, 12.5));

        $entries = AuditLogModel::query()
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $entries);

        $this->assertSame(
            'queue.job_processing',
            $entries[0]->event,
        );

        $this->assertSame(
            'queue.job_processed',
            $entries[1]->event,
        );

        $this->assertSame(
            'job-456',
            $entries[0]->properties['job_id'],
        );

        $this->assertSame(
            'emails',
            $entries[1]->properties['queue'],
        );
    }

    public function test_it_records_failed_jobs_with_bounded_exception_details(): void
    {
        $job = \Mockery::mock(Job::class);

        $job->shouldReceive('getQueue')
            ->once()
            ->andReturn('critical');

        $job->shouldReceive('getJobId')
            ->once()
            ->andReturn('job-789');

        $job->shouldReceive('resolveName')
            ->once()
            ->andReturn('App\\Jobs\\ChargePayment');

        $exception = new \RuntimeException(
            str_repeat('x', 1200),
        );

        Event::dispatch(new JobFailed(
            'redis',
            $job,
            $exception,
        ));

        $entry = AuditLogModel::query()->firstOrFail();

        $this->assertSame('queue.job_failed', $entry->event);
        $this->assertSame(
            \RuntimeException::class,
            $entry->properties['exception_class'],
        );

        $this->assertArrayNotHasKey(
            'exception_message',
            $entry->properties,
        );

        $this->assertSame('job-789', $entry->properties['job_id']);
        $this->assertSame('critical', $entry->properties['queue']);
    }
    
    public function test_it_can_store_a_bounded_exception_message_when_enabled(): void
    {
        config([
            'audit-log.queue.include_exception_message' => true,
        ]);

        $job = \Mockery::mock(\Illuminate\Contracts\Queue\Job::class);

        $job->shouldReceive('payload')
            ->zeroOrMoreTimes()
            ->andReturn([]);

        $job->shouldReceive('getQueue')->once()->andReturn('critical');
        $job->shouldReceive('getJobId')->once()->andReturn('job-message');
        $job->shouldReceive('resolveName')->once()->andReturn('App\\Jobs\\Example');

        \Illuminate\Support\Facades\Event::dispatch(
            new \Illuminate\Queue\Events\JobFailed(
                'redis',
                $job,
                new \RuntimeException(str_repeat('x', 1200)),
            ),
        );

        $entry = \ItmoSoft\AuditLog\Models\AuditLog::query()->firstOrFail();

        $this->assertSame(
            1000,
            mb_strlen($entry->properties['exception_message']),
        );
    }
}