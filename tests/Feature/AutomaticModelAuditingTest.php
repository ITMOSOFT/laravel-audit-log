<?php

namespace ItmoSoft\AuditLog\Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use ItmoSoft\AuditLog\Models\AuditLog as AuditLogModel;
use ItmoSoft\AuditLog\Tests\TestCase;

class AutomaticModelAuditingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        \Illuminate\Support\Facades\Schema::create(
            'products',
            function (\Illuminate\Database\Schema\Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->decimal('price', 10, 2);
                $table->string('password')->nullable();
                $table->timestamps();
            }
        );

        \Illuminate\Support\Facades\Schema::create(
            'audit_logs',
            function (\Illuminate\Database\Schema\Blueprint $table): void {
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
            }
        );
    }

    public function test_it_audits_created_models(): void
    {
        $product = AuditedProduct::create([
            'name' => 'Keyboard',
            'price' => 2500,
            'password' => 'secret',
        ]);

        $entry = AuditLogModel::query()
            ->where('event', 'created')
            ->firstOrFail();

        $this->assertSame('products', $product->getTable());
        $this->assertSame(
            (string) $product->getKey(),
            $entry->subject_id
        );
        $this->assertSame('Keyboard', $entry->new_values['name']);
        $this->assertArrayNotHasKey('password', $entry->new_values);
        $this->assertSame([], $entry->old_values);
    }

    public function test_it_audits_only_changed_attributes(): void
    {
        $product = AuditedProduct::create([
            'name' => 'Keyboard',
            'price' => 2500,
        ]);

        $product->update(['name' => 'Mechanical Keyboard']);

        $entry = AuditLogModel::query()
            ->where('event', 'updated')
            ->firstOrFail();

        $this->assertSame(
            ['name' => 'Keyboard'],
            $entry->old_values
        );

        $this->assertSame(
            ['name' => 'Mechanical Keyboard'],
            $entry->new_values
        );
    }

    public function test_it_does_not_audit_models_that_opt_out(): void
    {
        NonAuditedProduct::create([
            'name' => 'Private Product',
            'price' => 100,
        ]);

        $this->assertSame(
            0,
            AuditLogModel::query()
                ->where('event', 'created')
                ->count()
        );
    }

    public function test_it_audits_deleted_models(): void
    {
        $product = AuditedProduct::create([
            'name' => 'Keyboard',
            'price' => 2500,
        ]);

        $product->delete();

        $entry = AuditLogModel::query()
            ->where('event', 'deleted')
            ->firstOrFail();

        $this->assertSame('Keyboard', $entry->old_values['name']);
        $this->assertSame([], $entry->new_values);
    }

    public function test_it_respects_configured_model_events(): void
    {
        config()->set('audit-log.model_events', ['created']);

        $product = AuditedProduct::create([
            'name' => 'Keyboard',
            'price' => 2500,
        ]);

        $product->update([
            'name' => 'Mechanical Keyboard',
        ]);

        $this->assertSame(
            1,
            AuditLogModel::query()->where('event', 'created')->count()
        );

        $this->assertSame(
            0,
            AuditLogModel::query()->where('event', 'updated')->count()
        );
    }

    public function test_it_does_not_audit_unchanged_updates(): void
    {
        $product = AuditedProduct::create([
            'name' => 'Keyboard',
            'price' => 2500,
        ]);

        $product->update([
            'name' => 'Keyboard',
        ]);

        $this->assertSame(
            1,
            AuditLogModel::query()->count()
        );
    }

    public function test_it_groups_automatic_model_events(): void
    {
        \ItmoSoft\AuditLog\Facades\AuditLog::group(
            'Product maintenance',
            function (): void {
                $product = AuditedProduct::create([
                    'name' => 'Keyboard',
                    'price' => 2500,
                ]);

                $product->update([
                    'name' => 'Mechanical Keyboard',
                ]);
            }
        );

        $entries = AuditLogModel::query()
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $entries);
        $this->assertSame(
            $entries[0]->batch_uuid,
            $entries[1]->batch_uuid
        );
        $this->assertSame('Product maintenance', $entries[0]->batch_name);
        $this->assertSame('Product maintenance', $entries[1]->batch_name);
    }

    public function test_nested_groups_reuse_the_outer_group(): void
    {
        \ItmoSoft\AuditLog\Facades\AuditLog::group(
            'Order updated',
            function (): void {
                \ItmoSoft\AuditLog\Facades\AuditLog::record('order.updated');

                \ItmoSoft\AuditLog\Facades\AuditLog::group(
                    'Nested operation',
                    function (): void {
                        \ItmoSoft\AuditLog\Facades\AuditLog::record(
                            'order.item_updated'
                        );
                    }
                );
            }
        );

        $entries = AuditLogModel::query()
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $entries);
        $this->assertSame(
            $entries[0]->batch_uuid,
            $entries[1]->batch_uuid
        );
        $this->assertSame('Order updated', $entries[1]->batch_name);
    }

    public function test_it_returns_subject_history_grouped_by_activity(): void
    {
        $product = AuditedProduct::create([
            'name' => 'Keyboard',
            'price' => 2500,
        ]);

        \ItmoSoft\AuditLog\Facades\AuditLog::group(
            'Product updated',
            function () use ($product): void {
                $product->update(['name' => 'Mechanical Keyboard']);

                \ItmoSoft\AuditLog\Facades\AuditLog::record(
                    'product.note_added',
                    ['note' => 'Reviewed'],
                    subject: $product,
                );
            }
        );

        $activities = AuditLogModel::activityGroupsForSubject($product);

        $this->assertCount(2, $activities);
        $this->assertSame('Product updated', $activities[0]['name']);
        $this->assertCount(2, $activities[0]['events']);
        $this->assertSame('created', $activities[1]['events']->first()->event);
    }

    public function test_it_supports_uuid_subject_identifiers(): void
    {
        \Illuminate\Support\Facades\Schema::create(
            'uuid_products',
            function (\Illuminate\Database\Schema\Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->timestamps();
            }
        );

        $product = UuidAuditedProduct::create([
            'name' => 'UUID Product',
        ]);

        $entry = AuditLogModel::query()
            ->where('subject_type', $product->getMorphClass())
            ->where('subject_id', $product->getKey())
            ->firstOrFail();

        $this->assertSame($product->getKey(), $entry->subject_id);

        $activities = AuditLogModel::activityGroupsForSubject($product);

        $this->assertCount(1, $activities);
        $this->assertSame(
            $entry->batch_uuid,
            $activities[0]['batch_uuid']
        );
    }

    public function test_it_supports_ulid_subject_identifiers(): void
    {
        \Illuminate\Support\Facades\Schema::create(
            'ulid_products',
            function (\Illuminate\Database\Schema\Blueprint $table): void {
                $table->ulid('id')->primary();
                $table->string('name');
                $table->timestamps();
            }
        );

        $product = UlidAuditedProduct::create([
            'name' => 'ULID Product',
        ]);

        $entry = AuditLogModel::query()
            ->where('subject_type', $product->getMorphClass())
            ->where('subject_id', $product->getKey())
            ->firstOrFail();

        $this->assertSame($product->getKey(), $entry->subject_id);

        $activities = AuditLogModel::activityGroupsForSubject($product);

        $this->assertCount(1, $activities);
        $this->assertSame(
            $entry->batch_uuid,
            $activities[0]['batch_uuid']
        );
    }

    public function test_ungrouped_entries_are_returned_as_separate_activities(): void
    {
        $product = AuditedProduct::create([
            'name' => 'Keyboard',
            'price' => 2500,
        ]);

        $product->update([
            'name' => 'Mechanical Keyboard',
        ]);

        $entries = AuditLogModel::query()
            ->where('subject_type', $product->getMorphClass())
            ->where('subject_id', (string) $product->getKey())
            ->orderBy('id')
            ->get();

        $this->assertGreaterThanOrEqual(
            2,
            $entries->count(),
            'The regression test requires at least two audit entries.',
        );

        // Simulate legacy or manually inserted entries without a batch UUID.
        foreach ($entries as $entry) {
            $entry->batch_uuid = null;
            $entry->batch_name = null;
            $entry->save();
        }

        $activities = AuditLogModel::activityGroupsForSubject($product);

        $this->assertCount(
            $entries->count(),
            $activities,
            'Entries without a batch UUID must not be merged into one activity.',
        );
    }
}

class AuditedProduct extends Model
{
    use \ItmoSoft\AuditLog\Concerns\Auditable;

    protected $table = 'products';

    protected $guarded = [];
}

class NonAuditedProduct extends Model
{
    use \ItmoSoft\AuditLog\Concerns\Auditable;

    protected $table = 'products';

    protected $guarded = [];

    public function shouldAudit(): bool
    {
        return false;
    }
}

class UuidAuditedProduct extends Model
{
    use \Illuminate\Database\Eloquent\Concerns\HasUuids;
    use \ItmoSoft\AuditLog\Concerns\Auditable;

    protected $table = 'uuid_products';

    protected $guarded = [];
}

class UlidAuditedProduct extends Model
{
    use \Illuminate\Database\Eloquent\Concerns\HasUlids;
    use \ItmoSoft\AuditLog\Concerns\Auditable;

    protected $table = 'ulid_products';

    protected $guarded = [];
}