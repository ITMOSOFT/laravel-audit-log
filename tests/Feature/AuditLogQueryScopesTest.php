<?php

namespace ItmoSoft\AuditLog\Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use ItmoSoft\AuditLog\Models\AuditLog;
use ItmoSoft\AuditLog\Tests\TestCase;

class AuditLogQueryScopesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });

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

    public function test_it_filters_logs_for_a_subject(): void
    {
        $product = QueryScopeProduct::create(['name' => 'Keyboard']);
        $otherProduct = QueryScopeProduct::create(['name' => 'Mouse']);

        $this->createEntry([
            'event' => 'updated',
            'subject_type' => $product->getMorphClass(),
            'subject_id' => (string) $product->getKey(),
        ]);

        $this->createEntry([
            'event' => 'updated',
            'subject_type' => $otherProduct->getMorphClass(),
            'subject_id' => (string) $otherProduct->getKey(),
        ]);

        $this->assertCount(1, AuditLog::forSubject($product)->get());
    }

    public function test_it_filters_logs_by_actor(): void
    {
        $actor = QueryScopeUser::create(['name' => 'Alice']);
        $otherActor = QueryScopeUser::create(['name' => 'Bob']);

        $this->createEntry([
            'event' => 'updated',
            'actor_type' => $actor->getMorphClass(),
            'actor_id' => (string) $actor->getKey(),
        ]);

        $this->createEntry([
            'event' => 'deleted',
            'actor_type' => $otherActor->getMorphClass(),
            'actor_id' => (string) $otherActor->getKey(),
        ]);

        $this->assertCount(1, AuditLog::byActor($actor)->get());
    }

    public function test_it_filters_logs_by_event(): void
    {
        $this->createEntry(['event' => 'created']);
        $this->createEntry(['event' => 'updated']);
        $this->createEntry(['event' => 'deleted']);

        $this->assertCount(1, AuditLog::forEvent('updated')->get());
    }

    public function test_it_filters_logs_between_dates(): void
    {
        $this->createEntry([
            'event' => 'created',
            'created_at' => '2026-01-01 10:00:00',
        ]);

        $this->createEntry([
            'event' => 'updated',
            'created_at' => '2026-01-15 12:00:00',
        ]);

        $this->createEntry([
            'event' => 'deleted',
            'created_at' => '2026-02-01 10:00:00',
        ]);

        $entries = AuditLog::between(
            '2026-01-10 00:00:00',
            '2026-01-20 23:59:59',
        )->get();

        $this->assertCount(1, $entries);
        $this->assertSame('updated', $entries->first()->event);
    }

    private function createEntry(array $attributes = []): AuditLog
    {
        return AuditLog::query()->create(array_merge([
            'log_name' => 'default',
            'event' => 'created',
            'description' => null,
            'subject_type' => null,
            'subject_id' => null,
            'actor_type' => null,
            'actor_id' => null,
            'actor_name' => null,
            'old_values' => [],
            'new_values' => [],
            'properties' => [],
            'batch_uuid' => null,
            'batch_name' => null,
            'created_at' => '2026-01-01 00:00:00',
        ], $attributes));
    }
}

class QueryScopeProduct extends Model
{
    protected $table = 'products';

    public $timestamps = false;

    protected $guarded = [];
}

class QueryScopeUser extends Model
{
    protected $table = 'users';

    public $timestamps = false;

    protected $guarded = [];
}
