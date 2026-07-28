<?php

namespace Inertia\Tests\DevTools;

use Illuminate\Support\Facades\Log;
use Inertia\DevTools\Data\IncomingEntry;
use Inertia\DevTools\EntriesRepository;
use Inertia\DevTools\EntryStore;
use Inertia\Tests\TestCase;
use RuntimeException;

class EntryStoreTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        EntryStore::resetCircuitBreaker();
    }

    public function test_flush_persists_the_pending_entry_payload(): void
    {
        $recorder = new EntryStore;
        $entry = new IncomingEntry;
        $entry->tabUuid = 'tab-a';
        $entry->component = 'Users/Index';

        $repo = new RecorderSpyRepo;

        $recorder->record($entry);
        $recorder->flush($repo);

        $this->assertCount(1, $repo->saved);
        $this->assertSame($entry->id, array_key_first($repo->saved));
        $this->assertSame('Users/Index', $repo->saved[$entry->id]['__meta']['component']);
    }

    public function test_flush_without_a_pending_entry_leaves_repository_unchanged(): void
    {
        $recorder = new EntryStore;
        $repo = new RecorderSpyRepo;

        $recorder->flush($repo);

        $this->assertSame([], $repo->saved);
        $this->assertSame([], $repo->tabLimitCalls);
    }

    public function test_flush_enforces_the_configured_tab_limit_for_tabbed_entries(): void
    {
        config()->set('inertia.devtools.storage.limit', 12);

        $recorder = new EntryStore;
        $entry = new IncomingEntry;
        $entry->tabUuid = 'tab-a';
        $repo = new RecorderSpyRepo;

        $recorder->record($entry);
        $recorder->flush($repo);

        $this->assertSame([['tabUuid' => 'tab-a', 'limit' => 12]], $repo->tabLimitCalls);
    }

    public function test_flush_skips_tab_limit_enforcement_for_entries_without_tab_id(): void
    {
        config()->set('inertia.devtools.storage.limit', 12);

        $recorder = new EntryStore;
        $repo = new RecorderSpyRepo;

        $recorder->record(new IncomingEntry);
        $recorder->flush($repo);

        $this->assertSame([], $repo->tabLimitCalls);
    }

    public function test_circuit_breaker_suppresses_after_repository_error(): void
    {
        Log::shouldReceive('warning')->once();

        $recorder = new EntryStore;
        $failing = new FailingEntriesRepository;

        $first = new IncomingEntry;
        $recorder->record($first);
        $recorder->flush($failing);

        $second = new IncomingEntry;
        $recorder->record($second);
        $recorder->flush($failing);

        $this->assertSame(1, $failing->calls, 'Second flush should be suppressed by circuit breaker.');
    }
}

/**
 * In-memory spy over the real repository, so the EntryStore can be unit-tested without disk I/O.
 */
class RecorderSpyRepo extends EntriesRepository
{
    /** @var array<string, array<string, mixed>> */
    public array $saved = [];

    /** @var array<int, array{tabUuid: string, limit: int}> */
    public array $tabLimitCalls = [];

    public function __construct() {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function save(string $id, array $data): void
    {
        $this->saved[$id] = $data;
    }

    public function enforceTabLimit(string $tabUuid, int $limit): void
    {
        $this->tabLimitCalls[] = ['tabUuid' => $tabUuid, 'limit' => $limit];
    }
}

// Local failing stub for the circuit-breaker test below.
class FailingEntriesRepository extends EntriesRepository
{
    public int $calls = 0;

    public function __construct() {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function save(string $id, array $data): void
    {
        $this->calls++;

        throw new RuntimeException('disk full');
    }
}
