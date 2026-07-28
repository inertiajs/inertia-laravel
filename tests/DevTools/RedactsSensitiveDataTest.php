<?php

namespace Inertia\Tests\DevTools;

use Illuminate\Http\UploadedFile;
use Inertia\DevTools\RedactsSensitiveData;
use Inertia\Tests\TestCase;

class RedactsSensitiveDataTest extends TestCase
{
    protected ExposedRedactsSensitiveData $redactor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->redactor = new ExposedRedactsSensitiveData;
    }

    public function test_nested_body_keys_are_redacted_case_insensitively(): void
    {
        $redacted = $this->redactor->exposeRedact([
            'Password' => 'secret',
            'profile' => [
                'name' => 'John',
                'AUTH' => [
                    'Api_Key' => 'key-1',
                ],
            ],
        ], ['password', 'api_key']);

        $this->assertSame('[REDACTED]', $redacted['Password']);
        $this->assertSame('John', $redacted['profile']['name']);
        $this->assertSame('[REDACTED]', $redacted['profile']['AUTH']['Api_Key']);
    }

    public function test_query_string_secrets_are_redacted_case_insensitively(): void
    {
        $redacted = $this->redactor->exposeRedactUrls([
            '__meta' => [
                'url' => 'https://app.test/users?Token=abc&safe=1&filter[CLIENT_SECRET]=xyz#section',
                'redirectLocation' => '/next?api_key=key-1',
            ],
        ], ['token', 'client_secret', 'api_key']);

        // Illuminate\Support\Uri rebuilds the query via http_build_query, so the redacted marker
        // and bracketed nested keys come back percent-encoded. Scheme, host, path, non-sensitive
        // params and the fragment are preserved.
        $this->assertSame(
            'https://app.test/users?Token=%5BREDACTED%5D&safe=1&filter%5BCLIENT_SECRET%5D=%5BREDACTED%5D#section',
            $redacted['__meta']['url'],
        );
        $this->assertSame('/next?api_key=%5BREDACTED%5D', $redacted['__meta']['redirectLocation']);
    }

    public function test_relative_urls_are_redacted_and_unparseable_urls_are_returned_unchanged(): void
    {
        $redacted = $this->redactor->exposeRedactUrls([
            '__meta' => [
                'url' => '/search?password=secret&q=hi',
                'redirectLocation' => 'http://exa mple.test/?token=abc',
            ],
        ], ['password', 'token']);

        $this->assertSame('/search?password=%5BREDACTED%5D&q=hi', $redacted['__meta']['url']);
        $this->assertSame('http://exa mple.test/?token=abc', $redacted['__meta']['redirectLocation']);
    }

    public function test_header_bags_redact_cookies_and_auth_headers_case_insensitively(): void
    {
        config()->set('inertia.devtools.redact.headers', ['authorization', 'cookie', 'set-cookie']);

        $redacted = $this->redactor->exposeRedactHeaderBags([
            'http' => [
                'requestHeaders' => [
                    'Authorization' => ['Bearer secret'],
                    'Cookie' => ['session=abc'],
                    'Accept' => ['application/json'],
                ],
                'responseHeaders' => [
                    'Set-Cookie' => ['session=abc'],
                    'Content-Type' => ['application/json'],
                ],
            ],
        ]);

        $this->assertSame('[REDACTED]', $redacted['http']['requestHeaders']['Authorization']);
        $this->assertSame('[REDACTED]', $redacted['http']['requestHeaders']['Cookie']);
        $this->assertSame('application/json', $redacted['http']['requestHeaders']['Accept']);
        $this->assertSame('[REDACTED]', $redacted['http']['responseHeaders']['Set-Cookie']);
        $this->assertSame('application/json', $redacted['http']['responseHeaders']['Content-Type']);
    }

    public function test_storage_payload_redacts_response_bodies_custom_keys_uploads_invalid_utf8_and_large_arrays(): void
    {
        config()->set('inertia.devtools.redact.keys', ['password', 'token', 'client_secret', 'internal_flag']);
        config()->set('inertia.devtools.redact.headers', ['authorization', 'cookie']);

        $items = array_map(fn (int $i): array => [
            'name' => 'John '.$i,
            'Internal_Flag' => 'flag-'.$i,
        ], range(1, 1200));

        $redacted = $this->redactor->exposeRedactSensitiveStoragePayload([
            '__meta' => [
                'url' => 'https://app.test/users?token=abc&name=Jane',
            ],
            'http' => [
                'requestHeaders' => [
                    'Authorization' => ['Bearer secret'],
                    'Cookie' => ['session=abc'],
                ],
                'requestBody' => [
                    'status' => 'present',
                    'value' => [
                        'Password' => 'secret',
                        'avatar' => UploadedFile::fake()->create('avatar.pdf', 10),
                        'invalid' => "\xB1\x31",
                    ],
                ],
                'responseBody' => [
                    'status' => 'present',
                    'value' => [
                        'CLIENT_SECRET' => 'response-secret',
                        'items' => $items,
                    ],
                ],
            ],
        ]);

        $this->assertSame('https://app.test/users?token=%5BREDACTED%5D&name=Jane', $redacted['__meta']['url']);
        $this->assertSame('[REDACTED]', $redacted['http']['requestHeaders']['Authorization']);
        $this->assertSame('[REDACTED]', $redacted['http']['requestHeaders']['Cookie']);
        $this->assertSame('[REDACTED]', $redacted['http']['requestBody']['value']['Password']);
        $this->assertSame('[UNSERIALIZABLE]', $redacted['http']['requestBody']['value']['avatar']);
        $this->assertSame('[UNSERIALIZABLE]', $redacted['http']['requestBody']['value']['invalid']);
        $this->assertSame('[REDACTED]', $redacted['http']['responseBody']['value']['CLIENT_SECRET']);
        $this->assertCount(1200, $redacted['http']['responseBody']['value']['items']);
        $this->assertSame('John 1', $redacted['http']['responseBody']['value']['items'][0]['name']);
        $this->assertSame('[REDACTED]', $redacted['http']['responseBody']['value']['items'][1199]['Internal_Flag']);
    }
}

class ExposedRedactsSensitiveData
{
    use RedactsSensitiveData;

    /**
     * @param  array<array-key, mixed>  $data
     * @param  array<int, string>  $keys
     * @return array<array-key, mixed>
     */
    public function exposeRedact(array $data, array $keys): array
    {
        return $this->redact($data, $keys);
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @param  array<int, string>  $keys
     * @return array<array-key, mixed>
     */
    public function exposeRedactUrls(array $data, array $keys): array
    {
        return $this->redactUrls($data, $keys);
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    public function exposeRedactHeaderBags(array $data): array
    {
        return $this->redactHeaderBags($data);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function exposeRedactSensitiveStoragePayload(array $payload): array
    {
        return $this->redactSensitiveStoragePayload($payload);
    }
}
