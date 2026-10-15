<?php

namespace ItmoSoft\AuditLog\Services;

class AttributeFilter
{
    public function filter(array $values): array
    {
        $excluded = array_map(
            static fn ($key) => strtolower((string) $key),
            (array) config('audit-log.exclude', [])
        );

        return $this->filterRecursive($values, $excluded);
    }

    private function filterRecursive(array $values, array $excluded): array
    {
        $filtered = [];

        foreach ($values as $key => $value) {
            if (in_array(strtolower((string) $key), $excluded, true)) {
                continue;
            }

            $filtered[$key] = is_array($value)
                ? $this->filterRecursive($value, $excluded)
                : $value;
        }

        return $filtered;
    }
}
