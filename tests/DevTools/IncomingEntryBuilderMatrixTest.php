<?php

namespace Inertia\Tests\DevTools;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Inertia\DevTools\Data\IncomingEntry;
use Inertia\DevTools\Data\RequestType;
use Inertia\DevTools\DevToolsHeader;
use Inertia\DevTools\IncomingEntryBuilder;
use Inertia\DevTools\RequestAttribute;
use Inertia\DevTools\RequestRecorder;
use Inertia\DevTools\SourceLocator;
use Inertia\Support\Header;
use Inertia\Tests\TestCase;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exercises IncomingEntryBuilder::build() across the full request/response matrix by
 * feeding it hand-built Request/Response pairs, and proves the recorder never lets a
 * failure during recording turn the user's real response into a 500.
 */
class IncomingEntryBuilderMatrixTest extends TestCase
{
    use InteractsWithDevToolsStorage;

    protected function defineEnvironment($app): void
    {
        $app['config']->set('inertia.devtools.enabled', true);
        $app['config']->set('inertia.devtools.except', []);
    }

    protected function builder(): IncomingEntryBuilder
    {
        return new IncomingEntryBuilder(new SourceLocator);
    }

    /**
     * @param  array<string, mixed>  $headers
     * @param  array<string, mixed>|null  $payload
     */
    protected function request(string $method = 'GET', string $uri = 'http://localhost/dashboard', array $headers = [], ?array $payload = null): Request
    {
        $request = Request::create($uri, $method);

        foreach ($headers as $name => $value) {
            $request->headers->set($name, $value);
        }

        if ($payload !== null) {
            $request->attributes->set(RequestAttribute::PAYLOAD, $payload);
        }

        return $request;
    }

    protected function build(Request $request, Response $response, bool $isPrefetch = false): IncomingEntry
    {
        return $this->builder()->build($request, $response, 'entry-id', null, $isPrefetch);
    }

    public function test_request_type_precedence_across_the_header_matrix(): void
    {
        $response = new Response;

        $precognition = $this->request(headers: [Header::INERTIA => 'true', Header::PRECOGNITION => 'true', Header::PARTIAL_COMPONENT => 'Users/Form']);
        $this->assertSame(RequestType::Precognition, $this->build($precognition, $response)->requestType);

        $http = $this->request();
        $this->assertSame(RequestType::Http, $this->build($http, $response)->requestType);

        $initial = $this->request(payload: ['component' => 'Users/Index']);
        $this->assertSame(RequestType::Initial, $this->build($initial, $response)->requestType);

        $deferred = $this->request(headers: [Header::INERTIA => 'true', DevToolsHeader::DEVTOOLS_DEFERRED => '1', Header::PARTIAL_COMPONENT => 'Users/Index']);
        $this->assertSame(RequestType::Deferred, $this->build($deferred, $response)->requestType);

        $poll = $this->request(headers: [Header::INERTIA => 'true', DevToolsHeader::DEVTOOLS_POLL => '1', Header::PARTIAL_COMPONENT => 'Users/Index']);
        $this->assertSame(RequestType::Poll, $this->build($poll, $response)->requestType);

        $partial = $this->request(headers: [Header::INERTIA => 'true', Header::PARTIAL_COMPONENT => 'Users/Index']);
        $this->assertSame(RequestType::Partial, $this->build($partial, $response)->requestType);

        $prefetch = $this->request(headers: [Header::INERTIA => 'true']);
        $this->assertSame(RequestType::Prefetch, $this->build($prefetch, $response, isPrefetch: true)->requestType);

        $navigate = $this->request(headers: [Header::INERTIA => 'true']);
        $this->assertSame(RequestType::Navigate, $this->build($navigate, $response)->requestType);
    }

    public function test_deferred_and_poll_headers_take_precedence_over_partial(): void
    {
        $response = new Response;

        $deferredPartial = $this->request(headers: [
            Header::INERTIA => 'true',
            Header::PARTIAL_COMPONENT => 'Users/Index',
            DevToolsHeader::DEVTOOLS_DEFERRED => '1',
        ]);

        $this->assertSame(RequestType::Deferred, $this->build($deferredPartial, $response)->requestType);
    }

    public function test_non_inertia_request_without_a_string_component_payload_stays_http(): void
    {
        $response = new Response;

        $emptyComponent = $this->request(payload: ['component' => '']);
        $this->assertSame(RequestType::Http, $this->build($emptyComponent, $response)->requestType);

        $nonStringComponent = $this->request(payload: ['component' => ['nested']]);
        $this->assertSame(RequestType::Http, $this->build($nonStringComponent, $response)->requestType);
    }

    public function test_inertia_location_header_wins_over_status_for_redirect_location(): void
    {
        $request = $this->request(headers: [Header::INERTIA => 'true']);
        $response = new Response('', 409, [Header::LOCATION => 'https://example.com/external']);

        $entry = $this->build($request, $response);

        $this->assertSame('https://example.com/external', $entry->redirectLocation);
        $this->assertSame(409, $entry->status);
    }

    public function test_3xx_responses_use_the_standard_location_header(): void
    {
        foreach ([301, 302, 307, 308] as $status) {
            $response = new Response('', $status, ['Location' => '/elsewhere']);

            $this->assertSame('/elsewhere', $this->build($this->request(), $response)->redirectLocation);
        }
    }

    public function test_redirect_location_is_null_when_no_location_applies(): void
    {
        $this->assertNull($this->build($this->request(), new Response('', 200))->redirectLocation);
        $this->assertNull($this->build($this->request(), new Response('', 302))->redirectLocation);
        $this->assertNull($this->build($this->request(), new Response('', 404, ['Location' => '/ignored']))->redirectLocation);
        $this->assertNull($this->build($this->request(), new Response('', 500))->redirectLocation);
    }

    public function test_non_textual_streamed_and_oversized_response_bodies_are_omitted(): void
    {
        $binary = new Response('DATA', 200, ['Content-Type' => 'application/octet-stream']);
        $this->assertSame(['status' => 'omitted', 'reason' => 'non-textual'], $this->build($this->request(), $binary)->http['responseBody']);

        $streamed = new StreamedResponse(fn () => print ('chunk'), 200, ['Content-Type' => 'text/plain']);
        $this->assertSame(['status' => 'omitted', 'reason' => 'streamed'], $this->build($this->request(), $streamed)->http['responseBody']);

        $huge = new Response(str_repeat('a', 256_001), 200, ['Content-Type' => 'text/plain']);
        $this->assertSame(['status' => 'omitted', 'reason' => 'too-large'], $this->build($this->request(), $huge)->http['responseBody']);
    }

    public function test_response_body_just_under_the_limit_is_captured(): void
    {
        $body = str_repeat('a', 256_000);
        $response = new Response($body, 200, ['Content-Type' => 'text/plain']);

        $this->assertSame(['status' => 'present', 'value' => $body], $this->build($this->request(), $response)->http['responseBody']);
    }

    public function test_textual_and_empty_response_bodies_are_captured(): void
    {
        $empty = new Response('', 200, ['Content-Type' => 'text/plain']);
        $this->assertSame(['status' => 'empty'], $this->build($this->request(), $empty)->http['responseBody']);

        $json = new Response('{"token":"secret","name":"John"}', 200, ['Content-Type' => 'application/json']);
        config()->set('inertia.devtools.redact.keys', ['token']);
        $this->assertSame([
            'status' => 'present',
            'value' => ['token' => '[REDACTED]', 'name' => 'John'],
        ], $this->build($this->request(), $json)->http['responseBody']);

        $text = new Response('plain body', 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
        $this->assertSame(['status' => 'present', 'value' => 'plain body'], $this->build($this->request(), $text)->http['responseBody']);
    }

    public function test_malformed_json_response_falls_back_to_the_raw_string(): void
    {
        $response = new Response('{not valid json', 200, ['Content-Type' => 'application/json']);

        $this->assertSame(['status' => 'present', 'value' => '{not valid json'], $this->build($this->request(), $response)->http['responseBody']);
    }

    public function test_textual_response_body_with_invalid_utf8_is_omitted_as_binary(): void
    {
        $response = new Response("valid\xB1\x31text", 200, ['Content-Type' => 'text/plain']);

        $this->assertSame(['status' => 'omitted', 'reason' => 'binary'], $this->build($this->request(), $response)->http['responseBody']);
    }

    public function test_inertia_response_body_variants_are_captured_from_the_payload(): void
    {
        $string = $this->request(payload: ['responseBody' => 'rendered html']);
        $this->assertSame(['status' => 'present', 'value' => 'rendered html'], $this->build($string, new Response)->http['responseBody']);

        config()->set('inertia.devtools.redact.keys', ['token']);
        $array = $this->request(payload: ['responseBody' => ['props' => ['token' => 'secret', 'name' => 'John']]]);
        $this->assertSame([
            'status' => 'present',
            'value' => ['props' => ['token' => '[REDACTED]', 'name' => 'John']],
        ], $this->build($array, new Response)->http['responseBody']);

        $null = $this->request(payload: ['responseBody' => null]);
        $this->assertSame(['status' => 'empty'], $this->build($null, new Response)->http['responseBody']);

        $scalar = $this->request(payload: ['responseBody' => 42]);
        $this->assertSame(['status' => 'present', 'value' => 42], $this->build($scalar, new Response)->http['responseBody']);
    }

    public function test_non_inertia_write_request_bodies_are_recorded_as_metadata_only(): void
    {
        $request = Request::create('http://localhost/save', 'POST', ['name' => 'John']);

        $this->assertSame(['status' => 'omitted', 'reason' => 'non-inertia-request'], $this->build($request, new Response)->http['requestBody']);
    }

    public function test_inertia_json_request_body_is_redacted(): void
    {
        config()->set('inertia.devtools.redact.keys', ['password']);

        $request = Request::create('http://localhost/save', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_INERTIA' => 'true',
        ], json_encode(['password' => 'secret', 'name' => 'John']));

        $this->assertSame([
            'status' => 'present',
            'value' => ['password' => '[REDACTED]', 'name' => 'John'],
        ], $this->build($request, new Response)->http['requestBody']);
    }

    public function test_binary_request_body_is_omitted(): void
    {
        $request = Request::create('http://localhost/upload', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/octet-stream',
            'HTTP_X_INERTIA' => 'true',
        ], "\xff\xfe\x00\x01binary");

        $this->assertSame(['status' => 'omitted', 'reason' => 'binary'], $this->build($request, new Response)->http['requestBody']);
    }

    public function test_uploaded_files_are_summarized_and_invalid_uploads_report_null_size(): void
    {
        $valid = UploadedFile::fake()->create('resume.pdf', 12);
        $request = Request::create('http://localhost/upload', 'POST', ['name' => 'John'], [], ['avatar' => $valid], [
            'HTTP_X_INERTIA' => 'true',
        ]);

        $body = $this->build($request, new Response)->http['requestBody'];

        $this->assertSame('present', $body['status']);
        $this->assertSame('John', $body['value']['name']);
        $this->assertSame('resume.pdf', $body['value']['avatar']['name']);
        $this->assertSame(12 * 1024, $body['value']['avatar']['size']);
        $this->assertArrayHasKey('mimeType', $body['value']['avatar']);

        $invalid = new UploadedFile(__FILE__, 'ghost.pdf', 'application/pdf', UPLOAD_ERR_NO_FILE, test: true);
        $requestInvalid = Request::create('http://localhost/upload', 'POST', [], [], ['avatar' => $invalid], [
            'HTTP_X_INERTIA' => 'true',
        ]);

        $invalidBody = $this->build($requestInvalid, new Response)->http['requestBody'];

        $this->assertSame('ghost.pdf', $invalidBody['value']['avatar']['name']);
        $this->assertNull($invalidBody['value']['avatar']['size']);
    }

    public function test_headers_are_flattened_to_strings_and_sensitive_values_redacted(): void
    {
        config()->set('inertia.devtools.redact.headers', ['authorization']);

        $request = $this->request(headers: [
            'Authorization' => 'Bearer secret',
            'Accept' => 'application/json',
        ]);
        $request->headers->set('X-Multi', ['a', 'b']);

        $response = new Response('', 200, ['X-Response-Multi' => ['x', 'y']]);

        $entry = $this->build($request, $response);

        $this->assertSame('[REDACTED]', $entry->http['requestHeaders']['authorization']);
        $this->assertSame('application/json', $entry->http['requestHeaders']['accept']);
        $this->assertSame('a, b', $entry->http['requestHeaders']['x-multi']);
        $this->assertSame('x, y', $entry->http['responseHeaders']['x-response-multi']);
    }

    public function test_prop_values_are_sanitized_and_huge_values_are_preserved(): void
    {
        $huge = array_map(fn (int $i): array => ['id' => $i, 'name' => 'User '.$i], range(1, 5000));

        $request = $this->request(payload: [
            'component' => 'Users/Index',
            'propValues' => [
                'users' => $huge,
                'blob' => "\xB1\x31",
                'nested' => ['ok' => 'value', 'bad' => "\xB1\x31"],
            ],
        ]);

        $entry = $this->build($request, new Response);

        $this->assertCount(5000, $entry->propValues['users']);
        $this->assertSame('[UNSERIALIZABLE]', $entry->propValues['blob']);
        $this->assertSame('value', $entry->propValues['nested']['ok']);
        $this->assertSame('[UNSERIALIZABLE]', $entry->propValues['nested']['bad']);
    }

    public function test_route_and_render_source_fall_back_when_absent_from_payload(): void
    {
        $entry = $this->build($this->request(), new Response);

        $this->assertSame(['name' => null, 'uri' => '', 'action' => null], $entry->route);
        $this->assertNull($entry->renderSource);
    }

    public function test_recorder_never_lets_a_builder_failure_500_the_response(): void
    {
        $this->app->instance(IncomingEntryBuilder::class, new ThrowingIncomingEntryBuilder(new SourceLocator));

        $request = $this->request();
        $response = new Response('real response body', 200);

        app(RequestRecorder::class)->respondedWith($request, $response);

        $this->assertSame('real response body', $response->getContent());
        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotNull($response->headers->get(DevToolsHeader::DEVTOOLS_ID));
    }

    public function test_build_does_not_throw_on_a_pathological_response(): void
    {
        $request = Request::create('http://localhost/save', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_INERTIA' => 'true',
        ], "\xff\xfe not json");

        $response = new Response("body\xB1\x31", 500, ['Content-Type' => 'text/plain']);

        $entry = $this->build($request, $response);

        $this->assertSame(500, $entry->status);
    }
}

class ThrowingIncomingEntryBuilder extends IncomingEntryBuilder
{
    public function build(Request $request, Response $response, string $id, ?string $batchId, bool $isPrefetch): IncomingEntry
    {
        throw new RuntimeException('builder exploded');
    }
}
