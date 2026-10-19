<?php

namespace ItmoSoft\AuditLog\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'properties' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function getTable(): string
    {
        return config('audit-log.table_name', 'audit_logs');
    }

    public function getConnectionName(): ?string
    {
        return config('audit-log.connection') ?: parent::getConnectionName();
    }

    public function scopeForSubject(Builder $query, Model $subject): Builder
    {
        return $query->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey());
    }

    public function scopeByActor(Builder $query, Model $actor): Builder
    {
        return $query->where('actor_type', $actor->getMorphClass())
            ->where('actor_id', $actor->getKey());
    }

    public function scopeForEvent(Builder $query, string $event): Builder
    {
        return $query->where('event', $event);
    }

    public function scopeBetween(Builder $query, mixed $from, mixed $to): Builder
    {
        return $query->whereBetween('created_at', [$from, $to]);
    }

    /**
     * Return audit activity groups for a subject, newest activity first.
     *
     * @return Collection<int, array{
     *     batch_uuid: ?string,
     *     name: ?string,
     *     created_at: mixed,
     *     events: Collection<int, static>
     * }>
     */
    public static function activityGroupsForSubject(
        Model $subject
    ): Collection {
        $entries = static::query()
            ->forSubject($subject)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return $entries
            ->groupBy(
                fn (self $entry): string => $entry->batch_uuid
                    ?? 'entry:' . $entry->getKey()
            )
            ->map(function (Collection $events): array {
                $latest = $events->first();

                return [
                    'batch_uuid' => $latest->batch_uuid,
                    'name' => $latest->batch_name,
                    'created_at' => $latest->created_at,
                    'events' => $events->sortBy('id')->values(),
                ];
            })
            ->values();
    }

}
