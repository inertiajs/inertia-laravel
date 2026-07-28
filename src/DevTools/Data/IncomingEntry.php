<?php

namespace Inertia\DevTools\Data;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class IncomingEntry
{
    public string $id;

    public ?string $tabUuid = null;

    public ?string $batchId = null;

    public ?string $visitId = null;

    public string $timestamp;

    public float $utime;

    public string $method = 'GET';

    public string $url = '';

    public ?string $component = null;

    public RequestType $requestType = RequestType::Navigate;

    public int $status = 0;

    public ?string $redirectLocation = null;

    public float $serverTimingMs = 0.0;

    /** @var array<string, mixed> */
    public array $http = ['requestHeaders' => [], 'responseHeaders' => [], 'requestBody' => null, 'responseBody' => null];

    /** @var array<string, mixed> */
    public array $props = [];

    /** @var array<string, mixed> */
    public array $propValues = [];

    /** @var array{name: ?string, uri: string, action: ?string, actionSource?: array{file: string, line: int}} */
    public array $route = ['name' => null, 'uri' => '', 'action' => null];

    /** @var array{file: string, line: int}|null */
    public ?array $renderSource = null;

    public ?string $componentPath = null;

    public function __construct(?string $id = null)
    {
        $this->id = $id ?? (string) Str::ulid();
        $this->utime = microtime(true);
        $this->timestamp = Carbon::createFromTimestampMs((int) ($this->utime * 1000), 'UTC')->format('Y-m-d\TH:i:s.v\Z');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            '__meta' => [
                'id' => $this->id,
                'tabUuid' => $this->tabUuid,
                'batchId' => $this->batchId,
                'timestamp' => $this->timestamp,
                'utime' => $this->utime,
                'method' => $this->method,
                'url' => $this->url,
                'component' => $this->component,
                'requestType' => $this->requestType->value,
                'status' => $this->status,
                'redirectLocation' => $this->redirectLocation,
                'serverTimingMs' => $this->serverTimingMs,
                'visitId' => $this->visitId,
            ],
            'http' => $this->http,
            'props' => $this->props,
            'propValues' => $this->propValues,
            'route' => $this->route,
            'renderSource' => $this->renderSource,
            'componentPath' => $this->componentPath,
        ];
    }
}
