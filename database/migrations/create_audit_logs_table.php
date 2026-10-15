<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::connection(config('audit-log.connection') ?: null)
            ->create(config('audit-log.table_name', 'audit_logs'), function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->string('log_name')->nullable()->index();
                $table->string('event')->index();
                $table->text('description')->nullable();
                $table->string('subject_type')->nullable();
                $table->string('subject_id')->nullable();
                $table->index(['subject_type', 'subject_id']);
                $table->string('actor_type')->nullable();
                $table->string('actor_id')->nullable();
                $table->string('actor_name')->nullable();
                $table->json('old_values');
                $table->json('new_values');
                $table->json('properties');
                $table->uuid('batch_uuid')->nullable()->index();
                $table->string('batch_name')->nullable()->index();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['actor_type', 'actor_id']);
                $table->index(['event', 'created_at']);
                $table->index('created_at');
            });
    }

    public function down(): void
    {
        Schema::connection(config('audit-log.connection') ?: null)
            ->dropIfExists(config('audit-log.table_name', 'audit_logs'));
    }
};
