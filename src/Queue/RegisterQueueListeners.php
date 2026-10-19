<?php

namespace ItmoSoft\AuditLog\Queue;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Support\Facades\Event;
use ItmoSoft\AuditLog\Services\AuditLogger;

class RegisterQueueListeners
{
    private bool $registered = false;

    public function __construct(
        private readonly Application $app,
    ) {}

    public function register(): void
    {
        if ($this->registered) {
            return;
        }

        $this->registered = true;

        $events = config('audit-log.queue.events', [
            'queued',
            'processing',
            'processed',
            'failed',
        ]);

        if (in_array('queued', $events, true)) {
            Event::listen(JobQueued::class, function (JobQueued $event): void {
                $jobName = is_object($event->job)
                    ? $event->job::class
                    : (is_string($event->job) ? $event->job : null);

                $this->record(
                    event: 'queue.job_queued',
                    connection: $event->connectionName,
                    queue: $event->queue,
                    jobId: isset($event->id) ? (string) $event->id : null,
                    jobName: $jobName,
                );
            });
        }

        if (in_array('processing', $events, true)) {
            Event::listen(JobProcessing::class, function (JobProcessing $event): void {
                $this->record(
                    event: 'queue.job_processing',
                    connection: $event->connectionName,
                    queue: $event->job->getQueue(),
                    jobId: $event->job->getJobId(),
                    jobName: $event->job->resolveName(),
                );
            });
        }

        if (in_array('processed', $events, true)) {
            Event::listen(JobProcessed::class, function (JobProcessed $event): void {
                $this->record(
                    event: 'queue.job_processed',
                    connection: $event->connectionName,
                    queue: $event->job->getQueue(),
                    jobId: $event->job->getJobId(),
                    jobName: $event->job->resolveName(),
                );
            });
        }

        if (in_array('failed', $events, true)) {
            Event::listen(JobFailed::class, function (JobFailed $event): void {
                $this->record(
                    event: 'queue.job_failed',
                    connection: $event->connectionName,
                    queue: $event->job->getQueue(),
                    jobId: $event->job->getJobId(),
                    jobName: $event->job->resolveName(),
                    exception: $event->exception,
                );
            });
        }
    }

    private function record(
        string $event,
        ?string $connection,
        ?string $queue,
        ?string $jobId,
        ?string $jobName,
        ?\Throwable $exception = null,
    ): void {
        $properties = [
            'connection' => $connection,
            'queue' => $queue,
            'job_id' => $jobId,
            'job_name' => $jobName,
        ];

        if ($exception !== null) {
            $properties['exception_class'] = $exception::class;

            if (config('audit-log.queue.include_exception_message', false)) {
                $properties['exception_message'] = mb_substr(
                    $exception->getMessage(),
                    0,
                    1000,
                );
            }
        }

        $this->app->make(AuditLogger::class)->record(
            event: $event,
            properties: $properties,
            description: $jobName
                ? "Queue job {$jobName}: {$event}"
                : "Queue lifecycle event: {$event}",
            logName: 'queue',
        );
    }
}