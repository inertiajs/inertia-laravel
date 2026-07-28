<?php

namespace Inertia\Tests\DevTools;

use Inertia\DevTools\IncomingEntryBuilder;
use Inertia\DevTools\SourceLocator;
use Inertia\Tests\TestCase;

class IncomingEntryBuilderTest extends TestCase
{
    protected function makeBuilder(): ExposedIncomingEntryBuilder
    {
        return new ExposedIncomingEntryBuilder(new SourceLocator);
    }

    public function test_capture_body_value_keeps_encodable_payloads(): void
    {
        $result = $this->makeBuilder()->exposeCaptureBodyValue(['name' => 'John', 'items' => [1, 2, 3]]);

        $this->assertSame('present', $result['status']);
        $this->assertSame(['name' => 'John', 'items' => [1, 2, 3]], $result['value']);
    }

    public function test_capture_body_value_omits_unserializable_payloads(): void
    {
        $result = $this->makeBuilder()->exposeCaptureBodyValue(['blob' => "\xB1\x31"]);

        $this->assertSame('omitted', $result['status']);
        $this->assertSame('unserializable', $result['reason']);
        $this->assertArrayNotHasKey('value', $result);
    }

    public function test_sanitize_for_json_marks_unserializable_leaves_and_keeps_siblings(): void
    {
        $sanitized = $this->makeBuilder()->exposeSanitizeForJson([
            'name' => 'John',
            'user' => ['email' => 'john@example.com', 'avatar' => "\xB1\x31"],
            'items' => [1, 2, 3],
        ]);

        $this->assertSame([
            'name' => 'John',
            'user' => ['email' => 'john@example.com', 'avatar' => '[UNSERIALIZABLE]'],
            'items' => [1, 2, 3],
        ], $sanitized);
    }
}

class ExposedIncomingEntryBuilder extends IncomingEntryBuilder
{
    /**
     * @return array{status: string, value?: mixed, reason?: string}
     */
    public function exposeCaptureBodyValue(mixed $value): array
    {
        return $this->captureBodyValue($value);
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    public function exposeSanitizeForJson(array $data): array
    {
        return $this->sanitizeForJson($data);
    }
}
