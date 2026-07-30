<?php

namespace Inertia\Tests\DevTools;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Inertia\DevTools\DevToolsHeader;
use Inertia\DevTools\EntryStore;
use Inertia\Inertia;
use Inertia\Middleware;
use Inertia\Support\Header;
use Inertia\Tests\Stubs\DevToolsRootViewMiddleware;
use Inertia\Tests\TestCase;
use JsonSerializable;

class MiddlewareDevToolsTest extends TestCase
{
    use InteractsWithDevToolsStorage;

    protected function defineEnvironment($app): void
    {
        $app['config']->set('inertia.devtools.enabled', true);
        $app['config']->set('inertia.devtools.except', ['health', '_inertia/devtools*']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->bindEntriesRepository();

        EntryStore::resetCircuitBreaker();
    }

    protected function tearDown(): void
    {
        $this->clearDevToolsStorage();

        parent::tearDown();
    }

    protected function flushEntryStore(): void
    {
        $this->app->make(EntryStore::class)->flush($this->repo);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function lastSavedEntry(): ?array
    {
        $this->flushEntryStore();

        return $this->latestRecordedEntry();
    }

    public function test_devtools_id_header_is_set_on_every_response(): void
    {
        Route::middleware(Middleware::class)->get('/devtools-target', fn () => 'ok');

        $response = $this->get('/devtools-target');

        $response->assertOk();
        $id = $response->headers->get(DevToolsHeader::DEVTOOLS_ID);

        $this->assertNotNull($id);
        $this->assertNotSame('', $id);
    }

    public function test_devtools_parent_out_header_is_set_on_3xx_redirects(): void
    {
        Route::middleware(Middleware::class)->get('/devtools-redirect', fn () => redirect('/elsewhere'));

        $response = $this->get('/devtools-redirect');

        $response->assertRedirect('/elsewhere');
        $this->assertNotNull($response->headers->get(DevToolsHeader::DEVTOOLS_OUTGOING_PARENT));
    }

    public function test_initial_inertia_html_response_includes_the_devtools_id_script_tag(): void
    {
        Route::middleware(DevToolsRootViewMiddleware::class)->get('/devtools-html', fn () => Inertia::render('Users/Index', ['name' => 'Alice']));

        $response = $this->get('/devtools-html');

        $response->assertOk();
        $content = $response->getContent();

        $this->assertIsString($content);
        $this->assertStringContainsString('data-inertia-devtools-id', $content);
        $this->assertStringContainsString('</body>', $content);
        $this->assertMatchesRegularExpression('/<script data-inertia-devtools-id type="application\/json">"[A-Z0-9]+"<\/script><\/body>/', $content);
    }

    public function test_html_responses_that_render_no_inertia_page_are_left_untouched(): void
    {
        Route::middleware(Middleware::class)->get('/devtools-plain-html', function () {
            return response('<html><body><h1>hi</h1></body></html>')
                ->header('Content-Type', 'text/html; charset=UTF-8');
        });

        $response = $this->get('/devtools-plain-html');

        $response->assertOk();

        // The extension reads the tag as "the server enabled devtools on this page" and warns
        // when no interceptor registry follows, so a non-Inertia page must not carry it.
        $this->assertSame('<html><body><h1>hi</h1></body></html>', $response->getContent());
        $this->assertNotNull($response->headers->get(DevToolsHeader::DEVTOOLS_ID));
    }

    public function test_initial_inertia_page_load_is_recorded_as_initial(): void
    {
        Route::middleware(Middleware::class)->get('/devtools-initial', fn () => Inertia::render('Users/Index', ['name' => 'Alice']));

        $this->get('/devtools-initial');

        $entry = $this->lastSavedEntry();

        $this->assertNotNull($entry);
        $this->assertSame('initial', $entry['__meta']['requestType']);
        $this->assertSame('Users/Index', $entry['__meta']['component']);
    }

    public function test_non_inertia_requests_without_a_rendered_page_are_recorded_as_http(): void
    {
        Route::middleware(Middleware::class)->get('/devtools-raw', fn () => 'ok');

        $this->get('/devtools-raw');

        $entry = $this->lastSavedEntry();

        $this->assertNotNull($entry);
        $this->assertSame('http', $entry['__meta']['requestType']);
        $this->assertNull($entry['__meta']['component']);
    }

    public function test_inertia_json_response_is_not_html_injected(): void
    {
        Route::middleware(Middleware::class)->get('/devtools-json', fn () => response()->json(['ok' => true]));

        $response = $this->get('/devtools-json', ['X-Inertia' => 'true']);

        $this->assertStringNotContainsString('data-inertia-devtools-id', (string) $response->getContent());
        $this->assertNotNull($response->headers->get(DevToolsHeader::DEVTOOLS_ID));
    }

    public function test_excluded_paths_do_not_set_the_devtools_headers(): void
    {
        Route::middleware(Middleware::class)->get('/health', fn () => 'ok');

        $response = $this->get('/health');

        $this->assertNull($response->headers->get(DevToolsHeader::DEVTOOLS_ID));
    }

    public function test_non_inertia_post_body_is_recorded_as_metadata_only(): void
    {
        Route::middleware(Middleware::class)->post('/devtools-form', fn () => 'ok');

        $this->post('/devtools-form', ['password' => 'sekret', 'name' => 'alice']);

        $entry = $this->lastSavedEntry();

        $this->assertNotNull($entry);
        $this->assertSame(['status' => 'omitted', 'reason' => 'non-inertia-request'], $entry['http']['requestBody']);
        $this->assertSame(['status' => 'present', 'value' => 'ok'], $entry['http']['responseBody']);
    }

    public function test_non_inertia_json_response_body_is_captured_and_redacted(): void
    {
        Route::middleware(Middleware::class)->get('/devtools-api', fn () => response()->json([
            'token' => 'sekret',
            'name' => 'alice',
        ]));

        $this->get('/devtools-api');

        $entry = $this->lastSavedEntry();

        $this->assertNotNull($entry);
        $this->assertSame([
            'status' => 'present',
            'value' => ['token' => '[REDACTED]', 'name' => 'alice'],
        ], $entry['http']['responseBody']);
    }

    public function test_object_props_are_recorded_as_the_json_the_client_received(): void
    {
        Route::middleware(Middleware::class)->get('/devtools-objects', fn () => Inertia::render('Users/Show', [
            'date' => Carbon::parse('2026-01-01 10:00:00'),
            'resource' => new class implements JsonSerializable
            {
                public function jsonSerialize(): mixed
                {
                    return ['id' => 7, 'name' => 'Jane'];
                }
            },
        ]));

        $response = $this->get('/devtools-objects', ['X-Inertia' => 'true']);

        $entry = $this->lastSavedEntry();

        $this->assertNotNull($entry);
        $this->assertSame('present', $entry['http']['responseBody']['status']);

        // The recorded body must match the payload the client received, rather than the
        // marker the storage pass writes for values that are still objects.
        $this->assertSame(
            $response->json('props'),
            $entry['http']['responseBody']['value']['props'],
        );

        $this->assertSame([], $entry['http']['responseBody']['value']['props']['errors']);
        $this->assertSame('2026-01-01T10:00:00.000000Z', $entry['http']['responseBody']['value']['props']['date']);
        $this->assertSame(['id' => 7, 'name' => 'Jane'], $entry['http']['responseBody']['value']['props']['resource']);
    }

    public function test_non_textual_response_body_is_omitted(): void
    {
        Route::middleware(Middleware::class)->get('/devtools-binary', fn () => response('DATA', 200, [
            'Content-Type' => 'application/octet-stream',
        ]));

        $this->get('/devtools-binary');

        $entry = $this->lastSavedEntry();

        $this->assertNotNull($entry);
        $this->assertSame(['status' => 'omitted', 'reason' => 'non-textual'], $entry['http']['responseBody']);
    }

    public function test_password_and_token_fields_are_replaced_with_redacted_marker(): void
    {
        config()->set('inertia.devtools.redact.keys', ['password', 'token']);

        Route::middleware(Middleware::class)->post('/devtools-api', fn () => 'ok');

        $this->postJson('/devtools-api', ['password' => 'sekret', 'token' => 't', 'name' => 'alice'], [
            'X-Inertia' => 'true',
        ]);

        $entry = $this->lastSavedEntry();

        $this->assertNotNull($entry);
        $this->assertSame('present', $entry['http']['requestBody']['status']);
        $this->assertSame('[REDACTED]', $entry['http']['requestBody']['value']['password']);
        $this->assertSame('[REDACTED]', $entry['http']['requestBody']['value']['token']);
        $this->assertSame('alice', $entry['http']['requestBody']['value']['name']);
    }

    public function test_uploaded_files_are_summarized_instead_of_serialized(): void
    {
        Route::middleware(Middleware::class)->post('/devtools-upload', fn () => 'ok');

        $this->post('/devtools-upload', [
            'avatar' => UploadedFile::fake()->create('avatar.pdf', 100),
            'name' => 'alice',
        ], ['X-Inertia' => 'true']);

        $entry = $this->lastSavedEntry();

        $this->assertSame('present', $entry['http']['requestBody']['status']);
        $this->assertSame('alice', $entry['http']['requestBody']['value']['name']);
        $this->assertSame('avatar.pdf', $entry['http']['requestBody']['value']['avatar']['name']);
        $this->assertSame(100 * 1024, $entry['http']['requestBody']['value']['avatar']['size']);
        $this->assertArrayHasKey('mimeType', $entry['http']['requestBody']['value']['avatar']);
    }

    public function test_binary_request_bodies_are_omitted_instead_of_stored(): void
    {
        Route::middleware(Middleware::class)->post('/devtools-binary', fn () => 'ok');

        $this->call('POST', '/devtools-binary', [], [], [], [
            'HTTP_X_INERTIA' => 'true',
            'CONTENT_TYPE' => 'application/octet-stream',
        ], "\xff\xfe\x00\x01binary");

        $entry = $this->lastSavedEntry();

        $this->assertSame(['status' => 'omitted', 'reason' => 'binary'], $entry['http']['requestBody']);
    }

    public function test_precognition_requests_are_classified_before_partial_requests(): void
    {
        Route::middleware(Middleware::class)->post('/devtools-precognition', fn () => response()->json([
            'errors' => ['name' => 'Required'],
        ], 422));

        $this->postJson('/devtools-precognition', ['name' => ''], [
            Header::INERTIA => 'true',
            Header::PRECOGNITION => 'true',
            Header::PARTIAL_COMPONENT => 'Users/Form',
            Header::PARTIAL_ONLY => 'errors',
        ]);

        $entry = $this->lastSavedEntry();

        $this->assertNotNull($entry);
        $this->assertSame('precognition', $entry['__meta']['requestType']);
        $this->assertSame(422, $entry['__meta']['status']);
    }

    public function test_route_metadata_falls_back_to_the_request_route_for_non_inertia_redirects(): void
    {
        Route::middleware(Middleware::class)
            ->post('/devtools-redirect-route', fn () => redirect('/elsewhere'))
            ->name('devtools.redirect');

        $this->post('/devtools-redirect-route');

        $entry = $this->lastSavedEntry();

        $this->assertNotNull($entry);
        $this->assertSame('devtools.redirect', $entry['route']['name']);
        $this->assertSame('/devtools-redirect-route', $entry['route']['uri']);
        $this->assertSame('Closure', $entry['route']['action']);
        $this->assertSame(__FILE__, $entry['route']['actionSource']['file']);
        $this->assertIsInt($entry['route']['actionSource']['line']);
    }

    public function test_tab_and_parent_headers_are_recorded_in_entry_metadata(): void
    {
        Route::middleware(Middleware::class)->get('/devtools-headers', fn () => 'ok');

        $this->get('/devtools-headers', [
            DevToolsHeader::DEVTOOLS_TAB => 'tab-uuid-1',
            DevToolsHeader::DEVTOOLS_INCOMING_PARENT => 'parent-id-1',
            DevToolsHeader::DEVTOOLS_VISIT => 'visit-id-1',
            Header::INERTIA => 'true',
            Header::PARTIAL_COMPONENT => 'TestComponent',
        ]);

        $entry = $this->lastSavedEntry();

        $this->assertNotNull($entry);
        $this->assertSame('tab-uuid-1', $entry['__meta']['tabUuid']);
        $this->assertSame('parent-id-1', $entry['__meta']['batchId']);
        $this->assertSame('visit-id-1', $entry['__meta']['visitId']);
    }

    public function test_prefetch_sets_parent_out_to_its_own_id(): void
    {
        Route::middleware(Middleware::class)->get('/devtools-prefetch', fn () => 'ok');

        $response = $this->get('/devtools-prefetch', [
            Header::INERTIA => 'true',
            'Purpose' => 'prefetch',
            DevToolsHeader::DEVTOOLS_TAB => 'tab-prefetch',
            DevToolsHeader::DEVTOOLS_INCOMING_PARENT => 'originating-page',
        ]);

        $id = $response->headers->get(DevToolsHeader::DEVTOOLS_ID);
        $parentOut = $response->headers->get(DevToolsHeader::DEVTOOLS_OUTGOING_PARENT);

        $this->assertNotNull($id);
        $this->assertSame($id, $parentOut);

        $entry = $this->lastSavedEntry();

        $this->assertNotNull($entry);
        $this->assertSame('originating-page', $entry['__meta']['batchId']);
        $this->assertSame('prefetch', $entry['__meta']['requestType']);
    }

    public function test_deferred_follow_up_requests_are_classified_as_deferred(): void
    {
        Route::middleware(Middleware::class)->get('/devtools-deferred', fn () => 'ok');

        $this->get('/devtools-deferred', [
            Header::INERTIA => 'true',
            Header::PARTIAL_COMPONENT => 'SomeComponent',
            Header::PARTIAL_ONLY => 'heavyData',
            DevToolsHeader::DEVTOOLS_DEFERRED => '1',
        ]);

        $entry = $this->lastSavedEntry();

        $this->assertNotNull($entry);
        $this->assertSame('deferred', $entry['__meta']['requestType']);
    }

    public function test_partial_requests_without_the_deferred_header_stay_partial(): void
    {
        Route::middleware(Middleware::class)->get('/devtools-partial-plain', fn () => 'ok');

        $this->get('/devtools-partial-plain', [
            Header::INERTIA => 'true',
            Header::PARTIAL_COMPONENT => 'SomeComponent',
            Header::PARTIAL_ONLY => 'heavyData',
        ]);

        $entry = $this->lastSavedEntry();

        $this->assertNotNull($entry);
        $this->assertSame('partial', $entry['__meta']['requestType']);
    }

    public function test_redirect_responses_keep_request_intent_and_record_redirect_location(): void
    {
        Route::middleware(Middleware::class)->post('/devtools-redirect-type', fn () => redirect('/elsewhere'));

        $this->post('/devtools-redirect-type', [], [Header::INERTIA => 'true']);

        $entry = $this->lastSavedEntry();

        $this->assertNotNull($entry);
        $this->assertSame('navigate', $entry['__meta']['requestType']);
        $this->assertSame(302, $entry['__meta']['status']);
        $this->assertSame('http://localhost/elsewhere', $entry['__meta']['redirectLocation']);
    }

    public function test_external_location_responses_keep_request_intent_and_record_redirect_location(): void
    {
        Route::middleware(Middleware::class)->post(
            '/devtools-location-type',
            fn () => response('', 409)->header(Header::LOCATION, 'https://example.com'),
        );

        $this->post('/devtools-location-type', [], [Header::INERTIA => 'true']);

        $entry = $this->lastSavedEntry();

        $this->assertNotNull($entry);
        $this->assertSame('navigate', $entry['__meta']['requestType']);
        $this->assertSame(409, $entry['__meta']['status']);
        $this->assertSame('https://example.com', $entry['__meta']['redirectLocation']);
    }

    public function test_partial_requests_echo_the_incoming_parent_header_as_parent_out(): void
    {
        Route::middleware(Middleware::class)->get('/devtools-partial', fn () => 'ok');

        $response = $this->get('/devtools-partial', [
            Header::INERTIA => 'true',
            Header::PARTIAL_COMPONENT => 'SomeComponent',
            DevToolsHeader::DEVTOOLS_TAB => 'tab-partial',
            DevToolsHeader::DEVTOOLS_INCOMING_PARENT => 'parent-batch-id',
        ]);

        $parentOut = $response->headers->get(DevToolsHeader::DEVTOOLS_OUTGOING_PARENT);

        $this->assertSame('parent-batch-id', $parentOut);
    }

    public function test_full_navigation_records_no_batch_id_from_incoming_parent_header(): void
    {
        Route::middleware(Middleware::class)->get('/devtools-full-nav', fn () => 'ok');

        $this->get('/devtools-full-nav', [
            DevToolsHeader::DEVTOOLS_TAB => 'tab-uuid-2',
            DevToolsHeader::DEVTOOLS_INCOMING_PARENT => 'should-be-ignored',
        ]);

        $entry = $this->lastSavedEntry();

        $this->assertNotNull($entry);
        $this->assertNull($entry['__meta']['batchId']);
    }

    public function test_full_inertia_reload_groups_under_the_incoming_parent_header(): void
    {
        Route::middleware(Middleware::class)->get('/devtools-reload', fn () => 'ok');

        $this->get('/devtools-reload', [
            Header::INERTIA => 'true',
            DevToolsHeader::DEVTOOLS_TAB => 'tab-reload',
            DevToolsHeader::DEVTOOLS_INCOMING_PARENT => 'origin-page-id',
        ]);

        $entry = $this->lastSavedEntry();

        $this->assertNotNull($entry);
        $this->assertSame('origin-page-id', $entry['__meta']['batchId']);
    }

    public function test_sensitive_request_headers_have_their_values_redacted(): void
    {
        Route::middleware(Middleware::class)->get('/devtools-secret', fn () => 'ok');

        $this->get('/devtools-secret', [
            'Authorization' => 'Bearer secret',
            'Cookie' => 'sid=abc',
            'X-XSRF-TOKEN' => 'csrf-value',
            'Accept' => 'application/json',
        ]);

        $headers = $this->lastSavedEntry()['http']['requestHeaders'];

        $this->assertSame('[REDACTED]', $headers['authorization']);
        $this->assertSame('[REDACTED]', $headers['cookie']);
        $this->assertSame('[REDACTED]', $headers['x-xsrf-token']);
        $this->assertSame('application/json', $headers['accept']);
    }
}
