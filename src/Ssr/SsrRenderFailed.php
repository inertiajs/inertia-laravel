<?php

namespace Inertia\Ssr;

use Illuminate\Foundation\Events\Dispatchable;

class SsrRenderFailed
{
    use Dispatchable;

    /**
     * Create a new event instance.
     *
     * @param  array<string, mixed>  $page  The page data that was being rendered
     * @param  string  $error  The error message
     * @param  SsrErrorType  $type  The error type
     * @param  string|null  $hint  A helpful hint on how to fix the error
     * @param  string|null  $browserApi  The browser API that was accessed (if type is browser-api)
     * @param  string|null  $stack  The stack trace
     * @param  string|null  $sourceLocation  The source location (file:line:column) where the error occurred
     */
    public function __construct(
        public array $page,
        public string $error,
        public SsrErrorType $type = SsrErrorType::Unknown,
        public ?string $hint = null,
        public ?string $browserApi = null,
        public ?string $stack = null,
        public ?string $sourceLocation = null,
    ) {}

    /**
     * Get the component name from the page data.
     */
    public function component(): string
    {
        return $this->page['component'] ?? 'Unknown';
    }

    /**
     * Get the URL from the page data.
     */
    public function url(): string
    {
        return $this->page['url'] ?? '/';
    }

    /**
     * Convert the event to an array for logging.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'component' => $this->component(),
            'url' => $this->url(),
            'error' => $this->error,
            'type' => $this->type->value,
            'hint' => $this->hint,
            'browser_api' => $this->browserApi,
            'source_location' => $this->sourceLocation,
        ]);
    }
}
