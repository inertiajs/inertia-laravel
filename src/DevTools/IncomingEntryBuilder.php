<?php

namespace Inertia\DevTools;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Inertia\DevTools\Data\IncomingEntry;
use Inertia\DevTools\Data\RequestType;
use Inertia\Support\Header;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Builds an {@see IncomingEntry} from a request/response pair plus the per-request
 * collector payload. This is pure entry construction: no response mutation, no
 * recording. The recorder owns lineage/header/tag concerns and hands the result here.
 */
class IncomingEntryBuilder
{
    use RedactsSensitiveData;

    protected const RAW_BODY_LIMIT = 256_000;

    public function __construct(protected SourceLocator $sourceLocator) {}

    public function build(Request $request, SymfonyResponse $response, string $id, ?string $batchId, bool $isPrefetch): IncomingEntry
    {
        $entry = new IncomingEntry($id);
        $entry->tabUuid = DevToolsHeader::read($request, DevToolsHeader::DEVTOOLS_TAB);
        $entry->batchId = $batchId;
        $entry->visitId = DevToolsHeader::read($request, DevToolsHeader::DEVTOOLS_VISIT);
        $entry->method = $request->getMethod();
        $entry->url = $request->fullUrl();
        $entry->status = $response->getStatusCode();
        $entry->requestType = $this->resolveRequestType($request, $response, $isPrefetch);
        $entry->redirectLocation = $this->resolveRedirectLocation($response);
        $entry->serverTimingMs = $this->elapsedMs($request);

        $entry->http = [
            'requestHeaders' => $this->redactHeaders($request->headers->all()),
            'responseHeaders' => $this->redactHeaders($response->headers->all()),
            'requestBody' => $this->captureRequestBody($request),
            'responseBody' => $this->captureResponseBody($request, $response),
        ];

        $this->mergeCollectorPayload($entry, $request);

        if ($this->routeIsEmpty($entry->route)) {
            $entry->route = $this->routeFromRequest($request) ?? $entry->route;
        }

        if ($entry->renderSource === null) {
            $entry->renderSource = $this->renderSourceFromRoute($request);
        }

        return $entry;
    }

    protected function mergeCollectorPayload(IncomingEntry $entry, Request $request): void
    {
        $payload = $request->attributes->get(RequestAttribute::PAYLOAD);

        if (! is_array($payload)) {
            return;
        }

        $entry->component = $this->stringValue($payload, 'component') ?? $entry->component;
        $entry->props = $this->arrayValue($payload, 'props') ?? $entry->props;
        $entry->componentPath = $this->stringValue($payload, 'componentPath') ?? $entry->componentPath;

        $propValues = $this->arrayValue($payload, 'propValues');

        if ($propValues !== null) {
            $entry->propValues = $this->sanitizeForJson($this->redact($propValues, $this->redactKeys()));
        }

        $entry->route = $this->routePayload($payload) ?? $entry->route;
        $entry->renderSource = $this->renderSourcePayload($payload) ?? $entry->renderSource;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function stringValue(array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    protected function arrayValue(array $payload, string $key): ?array
    {
        $value = $payload[$key] ?? null;

        return is_array($value) ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{name: ?string, uri: string, action: ?string, actionSource?: array{file: string, line: int}}|null
     */
    protected function routePayload(array $payload): ?array
    {
        $route = $this->arrayValue($payload, 'route');

        if ($route === null) {
            return null;
        }

        $normalized = [
            'name' => $route['name'] ?? null,
            'uri' => (string) ($route['uri'] ?? ''),
            'action' => $route['action'] ?? null,
        ];

        $actionSource = $this->arrayValue($route, 'actionSource');

        if ($actionSource !== null) {
            $normalized['actionSource'] = [
                'file' => (string) ($actionSource['file'] ?? ''),
                'line' => (int) ($actionSource['line'] ?? 0),
            ];
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{file: string, line: int}|null
     */
    protected function renderSourcePayload(array $payload): ?array
    {
        $renderSource = $this->arrayValue($payload, 'renderSource');

        if ($renderSource === null) {
            return null;
        }

        return [
            'file' => (string) ($renderSource['file'] ?? ''),
            'line' => (int) ($renderSource['line'] ?? 0),
        ];
    }

    protected function resolveRequestType(Request $request, SymfonyResponse $response, ?bool $isPrefetch = null): RequestType
    {
        $isPrefetch ??= $request->prefetch();

        if ($request->header(Header::PRECOGNITION)) {
            return RequestType::Precognition;
        }

        if (! $request->header(Header::INERTIA)) {
            return $this->renderedInertiaPage($request) ? RequestType::Initial : RequestType::Http;
        }

        if ($request->header(DevToolsHeader::DEVTOOLS_DEFERRED)) {
            return RequestType::Deferred;
        }

        if ($request->header(DevToolsHeader::DEVTOOLS_POLL)) {
            return RequestType::Poll;
        }

        if ($request->header(Header::PARTIAL_COMPONENT)) {
            return RequestType::Partial;
        }

        if ($isPrefetch) {
            return RequestType::Prefetch;
        }

        return RequestType::Navigate;
    }

    /**
     * A non-Inertia request that rendered an Inertia page (component present in the
     * collector payload) is the app's initial page load. One that rendered no Inertia
     * page is a plain HTTP request the app serves alongside Inertia.
     */
    protected function renderedInertiaPage(Request $request): bool
    {
        $payload = $request->attributes->get(RequestAttribute::PAYLOAD);

        return is_array($payload)
            && isset($payload['component'])
            && is_string($payload['component'])
            && $payload['component'] !== '';
    }

    protected function resolveRedirectLocation(SymfonyResponse $response): ?string
    {
        $inertiaLocation = $response->headers->get(Header::LOCATION);

        if (is_string($inertiaLocation) && $inertiaLocation !== '') {
            return $inertiaLocation;
        }

        $status = $response->getStatusCode();

        if ($status < 300 || $status >= 400) {
            return null;
        }

        $location = $response->headers->get('Location');

        return is_string($location) && $location !== '' ? $location : null;
    }

    /**
     * @return array{status: string, value?: mixed, reason?: string}
     */
    protected function captureRequestBody(Request $request): array
    {
        $writeMethod = in_array($request->getMethod(), ['POST', 'PUT', 'PATCH', 'DELETE'], true);

        if ($writeMethod && ! $request->header(Header::INERTIA)) {
            return ['status' => 'omitted', 'reason' => 'non-inertia-request'];
        }

        $redactKeys = $this->redactKeys();

        if ($request->isJson()) {
            $body = (array) $request->json()->all();

            return $body === [] ? ['status' => 'empty'] : $this->captureBodyValue($this->redact($body, $redactKeys));
        }

        $input = $request->all();

        if ($input !== []) {
            return $this->captureBodyValue($this->redact($this->summarizeUploads($input), $redactKeys));
        }

        return $this->captureBodyString($request->getContent() ?: null);
    }

    /**
     * @return array{status: string, value?: mixed, reason?: string}
     */
    protected function captureResponseBody(Request $request, SymfonyResponse $response): array
    {
        $payload = $request->attributes->get(RequestAttribute::PAYLOAD);

        if (is_array($payload) && array_key_exists('responseBody', $payload)) {
            return $this->captureInertiaResponseBody($payload['responseBody']);
        }

        return $this->captureRawResponseBody($response);
    }

    /**
     * @return array{status: string, value?: mixed, reason?: string}
     */
    protected function captureInertiaResponseBody(mixed $responseBody): array
    {
        if (is_string($responseBody)) {
            return $this->captureBodyString($responseBody);
        }

        if (is_array($responseBody)) {
            return $this->captureBodyValue($this->redact($this->normalizeResponseBody($responseBody), $this->redactKeys()));
        }

        if ($responseBody === null) {
            return ['status' => 'empty'];
        }

        return $this->captureBodyValue($responseBody);
    }

    /**
     * Cast the page object to the JSON the client received. Resolved props still hold live
     * values here (a model, a date, the always-shared `errors` object), and the storage
     * pass would replace those object leaves with a marker.
     *
     * @param  array<array-key, mixed>  $responseBody
     * @return array<array-key, mixed>
     */
    protected function normalizeResponseBody(array $responseBody): array
    {
        $encoded = json_encode($responseBody);

        if (! is_string($encoded)) {
            return $responseBody;
        }

        $decoded = json_decode($encoded, true);

        return is_array($decoded) ? $decoded : $responseBody;
    }

    /**
     * Capture the body of a non-Inertia response (plain JSON/text endpoints the app
     * serves alongside Inertia). Binary, streamed, and oversized bodies are omitted.
     *
     * @return array{status: string, value?: mixed, reason?: string}
     */
    protected function captureRawResponseBody(SymfonyResponse $response): array
    {
        $contentType = strtolower((string) $response->headers->get('Content-Type', ''));

        if (! $this->isTextualContentType($contentType)) {
            return ['status' => 'omitted', 'reason' => 'non-textual'];
        }

        $content = $response->getContent();

        if ($content === false) {
            return ['status' => 'omitted', 'reason' => 'streamed'];
        }

        if ($content === '') {
            return ['status' => 'empty'];
        }

        if (strlen($content) > self::RAW_BODY_LIMIT) {
            return ['status' => 'omitted', 'reason' => 'too-large'];
        }

        if (str_contains($contentType, 'json')) {
            $decoded = json_decode($content, true);

            if (is_array($decoded)) {
                return $this->captureBodyValue($this->redact($decoded, $this->redactKeys()));
            }
        }

        return $this->captureBodyString($content);
    }

    protected function isTextualContentType(string $contentType): bool
    {
        foreach (['json', 'text/', 'xml', 'javascript'] as $needle) {
            if (str_contains($contentType, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{status: string, value?: mixed, reason?: string}
     */
    protected function captureBodyValue(mixed $value): array
    {
        if (! $this->isEncodable($value)) {
            return ['status' => 'omitted', 'reason' => 'unserializable'];
        }

        return ['status' => 'present', 'value' => $value];
    }

    /**
     * Confirm a value survives JSON encoding with the flags the repository persists it
     * with. An un-encodable value would otherwise throw at save and drop the entry.
     */
    protected function isEncodable(mixed $value): bool
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !== false;
    }

    /**
     * Replace individual leaf values that cannot be JSON encoded with a marker, keeping
     * the surrounding structure intact. Prop values are shared verbatim by the app, so a
     * single non-UTF-8 attribute must not fail the whole entry at save.
     *
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    protected function sanitizeForJson(array $data): array
    {
        return collect($data)
            ->map(function (mixed $value) {
                if (is_array($value)) {
                    return $this->sanitizeForJson($value);
                }

                return $this->isEncodable($value) ? $value : '[UNSERIALIZABLE]';
            })
            ->all();
    }

    /**
     * @return array{status: string, value?: string, reason?: string}
     */
    protected function captureBodyString(?string $body): array
    {
        if ($body === null || $body === '') {
            return ['status' => 'empty'];
        }

        if (! mb_check_encoding($body, 'UTF-8')) {
            return ['status' => 'omitted', 'reason' => 'binary'];
        }

        return ['status' => 'present', 'value' => $body];
    }

    /**
     * Replace uploaded files with a lightweight summary so binary contents are never
     * serialized into an entry.
     *
     * @param  array<array-key, mixed>  $input
     * @return array<array-key, mixed>
     */
    protected function summarizeUploads(array $input): array
    {
        return collect($input)
            ->map(function (mixed $value) {
                if ($value instanceof UploadedFile) {
                    return $this->summarizeUpload($value);
                }

                return is_array($value) ? $this->summarizeUploads($value) : $value;
            })
            ->all();
    }

    /**
     * @return array{name: ?string, size: ?int, mimeType: ?string}
     */
    protected function summarizeUpload(UploadedFile $file): array
    {
        return [
            'name' => $file->getClientOriginalName(),
            'size' => $file->isValid() ? $file->getSize() : null,
            'mimeType' => $file->getClientMimeType(),
        ];
    }

    /**
     * Resolve the render source for route-defined renders (Route::inertia), which have
     * no user-space render call site. The definition location is captured onto the route
     * defaults by the `Route::inertia()` macro when the route is registered.
     *
     * @return array{file: string, line: int}|null
     */
    protected function renderSourceFromRoute(Request $request): ?array
    {
        $source = $request->route()?->defaults[DevTools::RENDER_SOURCE_KEY] ?? null;

        if (! is_array($source) || ! isset($source['file'], $source['line'])) {
            return null;
        }

        return ['file' => (string) $source['file'], 'line' => (int) $source['line']];
    }

    /**
     * @param  array{name: ?string, uri: string, action: ?string, actionSource?: array{file: string, line: int}}  $route
     */
    protected function routeIsEmpty(array $route): bool
    {
        return ($route['name'] ?? null) === null
            && $route['uri'] === ''
            && ($route['action'] ?? null) === null
            && ! array_key_exists('actionSource', $route);
    }

    /**
     * @return array{name: ?string, uri: string, action: ?string, actionSource?: array{file: string, line: int}}|null
     */
    protected function routeFromRequest(Request $request): ?array
    {
        $route = $request->route();

        if ($route === null) {
            return null;
        }

        $normalized = [
            'name' => $route->getName(),
            'uri' => '/'.ltrim($route->uri(), '/'),
            'action' => $route->getActionName(),
        ];

        $actionSource = $this->sourceLocator->resolveActionSource(
            $route->getActionName(),
            $route->getAction('uses')
        );

        if ($actionSource !== null) {
            $normalized['actionSource'] = $actionSource;
        }

        return $normalized;
    }

    protected function elapsedMs(Request $request): float
    {
        $start = $request->attributes->get(RequestAttribute::START);

        if (! is_int($start)) {
            return 0.0;
        }

        return (hrtime(true) - $start) / 1_000_000;
    }

    /**
     * @return array<int|string, mixed>
     */
    protected function redactKeys(): array
    {
        return config()->array('inertia.devtools.redact.keys', []);
    }
}
