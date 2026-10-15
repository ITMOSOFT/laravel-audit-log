<?php

namespace ItmoSoft\AuditLog\Services;

use Illuminate\Support\Str;

class AuditGroupContext
{
    private ?string $uuid = null;

    private ?string $name = null;

    public function current(): ?array
    {
        if ($this->uuid === null) {
            return null;
        }

        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
        ];
    }

    public function run(string $name, callable $callback): mixed
    {
        if (trim($name) === '') {
            throw new \InvalidArgumentException(
                'Audit group name cannot be empty.'
            );
        }

        $previousUuid = $this->uuid;
        $previousName = $this->name;

        $this->uuid ??= (string) Str::uuid();
        $this->name ??= $name;

        try {
            return $callback();
        } finally {
            $this->uuid = $previousUuid;
            $this->name = $previousName;
        }
    }

    public function reset(): void
    {
        $this->uuid = null;
        $this->name = null;
    }
}
