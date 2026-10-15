<?php

namespace ItmoSoft\AuditLog\Tests\Unit;

use ItmoSoft\AuditLog\Services\AttributeFilter;
use ItmoSoft\AuditLog\Tests\TestCase;

class AttributeFilterTest extends TestCase
{
    public function test_it_removes_sensitive_fields_recursively(): void
    {
        config()->set('audit-log.exclude', [
            'password',
            'access_token',
        ]);

        $filter = new AttributeFilter();

        $result = $filter->filter([
            'name' => 'Adeel',
            'password' => 'secret',
            'metadata' => [
                'access_token' => 'token-value',
                'department' => 'Engineering',
            ],
        ]);

        $this->assertSame([
            'name' => 'Adeel',
            'metadata' => [
                'department' => 'Engineering',
            ],
        ], $result);
    }

    public function test_it_preserves_non_sensitive_values(): void
    {
        $filter = new AttributeFilter();

        $result = $filter->filter([
            'name' => 'Product',
            'price' => 2500,
        ]);

        $this->assertSame([
            'name' => 'Product',
            'price' => 2500,
        ], $result);
    }
}
