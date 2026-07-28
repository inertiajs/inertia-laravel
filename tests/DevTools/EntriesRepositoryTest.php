<?php

namespace Inertia\Tests\DevTools;

use Illuminate\Support\Str;
use Inertia\DevTools\EntriesRepository;
use Inertia\Tests\TestCase;
use InvalidArgumentException;

class EntriesRepositoryTest extends TestCase
{
    protected string $storagePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->storagePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'inertia-devtools-'.bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->storagePath);

        parent::tearDown();
    }

    protected function makeRepository(): EntriesRepository
    {
        return new EntriesRepository(
            path: $this->storagePath,
            autoPruneHours: 24,
        );
    }

    /**
     * @param  array<string, mixed>  $metaOverrides
     * @return array<string, mixed>
     */
    protected function envelope(array $metaOverrides = []): array
    {
        $id = $metaOverrides['id'] ?? (string) Str::ulid();

        return [
            '__meta' => array_merge([
                'id' => $id,
                'tabUuid' => 'tab-a',
                'batchId' => null,
                'timestamp' => '2026-05-12T10:00:00.000Z',
                'utime' => microtime(true),
                'method' => 'GET',
                'url' => 'http://app.test/users',
                'component' => 'Users/Index',
                'requestType' => 'navigate',
                'status' => 200,
                'serverTimingMs' => 1.5,
            ], $metaOverrides),
            'http' => ['requestHeaders' => [], 'responseHeaders' => [], 'requestBody' => null, 'responseBody' => null],
            'props' => [],
            'propValues' => [],
            'route' => ['name' => null, 'uri' => '', 'action' => null],
        ];
    }

    public function test_save_and_get_round_trip(): void
    {
        $repo = $this->makeRepository();
        $payload = $this->envelope();
        $id = $payload['__meta']['id'];

        $repo->save($id, $payload);

        $this->assertSame($payload, $repo->get($id));
    }

    public function test_get_returns_null_for_missing_entry(): void
    {
        $repo = $this->makeRepository();

        $this->assertNull($repo->get('does-not-exist'));
    }

    public function test_save_rejects_invalid_entry_ids(): void
    {
        $repo = $this->makeRepository();

        $this->expectException(InvalidArgumentException::class);

        $repo->save('../secret', $this->envelope(['id' => '../secret']));
    }

    public function test_gitignore_is_written_on_first_save(): void
    {
        $repo = $this->makeRepository();
        $payload = $this->envelope();
        $id = $payload['__meta']['id'];

        $repo->save($id, $payload);

        $gitignore = $this->storagePath.DIRECTORY_SEPARATOR.'.gitignore';

        $this->assertFileExists($gitignore);
        $this->assertSame("*\n", file_get_contents($gitignore));
    }

    public function test_save_updates_meta_index_for_hot_path_lookups(): void
    {
        $repo = $this->makeRepository();
        $payload = $this->envelope();

        $repo->save($payload['__meta']['id'], $payload);

        $metaPath = $this->storagePath.DIRECTORY_SEPARATOR.'_meta.json';

        $this->assertFileExists($metaPath);
        $index = json_decode((string) file_get_contents($metaPath), true);

        $this->assertIsArray($index);
        $this->assertSame($payload['__meta']['id'], $index[$payload['__meta']['id']]['id']);
        $this->assertSame($payload['__meta']['tabUuid'], $index[$payload['__meta']['id']]['tabUuid']);
    }

    public function test_all_rebuilds_the_index_when_it_is_corrupt(): void
    {
        $repo = $this->makeRepository();
        $payload = $this->envelope();
        $repo->save($payload['__meta']['id'], $payload);

        file_put_contents($this->storagePath.DIRECTORY_SEPARATOR.'_meta.json', '{ not valid json');

        $found = $repo->all();

        $this->assertCount(1, $found);
        $this->assertSame($payload['__meta']['id'], $found[0]['id']);
    }

    public function test_a_corrupt_index_does_not_drop_prior_entries_on_the_next_save(): void
    {
        $repo = $this->makeRepository();
        $first = $this->envelope();
        $repo->save($first['__meta']['id'], $first);

        file_put_contents($this->storagePath.DIRECTORY_SEPARATOR.'_meta.json', 'garbage');

        $second = $this->envelope();
        $repo->save($second['__meta']['id'], $second);

        $ids = array_column($repo->all(), 'id');

        $this->assertContains($first['__meta']['id'], $ids);
        $this->assertContains($second['__meta']['id'], $ids);
    }

    public function test_all_returns_every_entry_sorted_descending_by_id(): void
    {
        $repo = $this->makeRepository();

        $ids = [(string) Str::ulid(), (string) Str::ulid(), (string) Str::ulid()];
        sort($ids);

        foreach ($ids as $id) {
            $payload = $this->envelope(['id' => $id]);
            $repo->save($id, $payload);
        }

        $found = $repo->all();

        $expected = array_reverse($ids);

        $this->assertSame($expected, array_column($found, 'id'));
    }

    public function test_enforce_tab_limit_drops_oldest_entries(): void
    {
        $repo = $this->makeRepository();

        $ids = [];
        for ($i = 0; $i < 5; $i++) {
            $id = (string) Str::ulid();
            $ids[] = $id;
            $repo->save($id, $this->envelope(['id' => $id, 'tabUuid' => 'tab-a']));
        }

        sort($ids);

        $repo->enforceTabLimit('tab-a', 2);

        $remaining = array_column($repo->all(), 'id');
        sort($remaining);

        $this->assertSame(array_slice($ids, -2), $remaining);
    }

    public function test_prune_drops_entries_older_than_cutoff(): void
    {
        $repo = $this->makeRepository();
        $old = $this->envelope(['utime' => microtime(true) - (48 * 3600)]);
        $fresh = $this->envelope();

        $repo->save($old['__meta']['id'], $old);
        $repo->save($fresh['__meta']['id'], $fresh);

        $repo->prune(24);

        $this->assertNull($repo->get($old['__meta']['id']));
        $this->assertNotNull($repo->get($fresh['__meta']['id']));
    }

    public function test_prune_if_due_skips_until_interval_elapsed(): void
    {
        $repo = $this->makeRepository();
        $old = $this->envelope(['utime' => microtime(true) - (48 * 3600)]);

        $repo->save($old['__meta']['id'], $old);
        $repo->pruneIfDue();

        $this->assertNull($repo->get($old['__meta']['id']));

        $fresh = $this->envelope();
        $repo->save($fresh['__meta']['id'], $fresh);
        file_put_contents($this->storagePath.DIRECTORY_SEPARATOR.'_last_prune', (string) time(), LOCK_EX);
        $repo->pruneIfDue();

        $this->assertNotNull($repo->get($fresh['__meta']['id']));
    }

    protected function removeDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        foreach (new \FilesystemIterator($path, \FilesystemIterator::SKIP_DOTS) as $item) {
            if ($item->isDir()) {
                $this->removeDirectory($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }

        @rmdir($path);
    }
}
