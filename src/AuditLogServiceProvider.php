<?php

namespace ItmoSoft\AuditLog;

use Illuminate\Support\ServiceProvider;
use ItmoSoft\AuditLog\Services\ActorResolver;
use ItmoSoft\AuditLog\Services\AuditLogger;

class AuditLogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/audit-log.php', 'audit-log');

        $this->app->singleton(ActorResolver::class, fn ($app) => new ActorResolver($app));
        $this->app->singleton(AuditLogger::class, fn ($app) => new AuditLogger(
            $app,
            $app->make(ActorResolver::class),
        ));
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/audit-log.php' => config_path('audit-log.php'),
        ], 'audit-log-config');

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if (config('audit-log.queue.enabled', false)) {
            $this->app->make(Queue\RegisterQueueListeners::class)->register();
        }
    }
}
