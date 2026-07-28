<?php

namespace Inertia\DevTools;

use Illuminate\Support\Facades\Log;
use Inertia\DevTools\Data\IncomingEntry;
use Throwable;

class EntryStore
{
    use RedactsSensitiveData;

    protected const SUPPRESS_SECONDS = 30;

    /**
     * Deliberately static: a write failure suppresses recording for a short window. Each
     * long-lived worker keeps its own breaker, and process-per-request setups reset it.
     */
    protected static ?float $suppressedUntil = null;

    protected ?IncomingEntry $pending = null;

    public function record(IncomingEntry $entry): void
    {
        $this->pending = $entry;
    }

    public function current(): ?IncomingEntry
    {
        return $this->pending;
    }

    public function reset(): void
    {
        $this->pending = null;
    }

    public function flush(EntriesRepository $repo): void
    {
        $entry = $this->pending;

        if ($entry === null) {
            return;
        }

        $this->pending = null;

        if (static::$suppressedUntil !== null && microtime(true) < static::$suppressedUntil) {
            return;
        }

        try {
            $repo->save($entry->id, $this->redactSensitiveStoragePayload($entry->toArray()));

            if ($entry->tabUuid !== null) {
                $limit = config()->integer('inertia.devtools.storage.limit', 100);

                if ($limit > 0) {
                    $repo->enforceTabLimit($entry->tabUuid, $limit);
                }
            }

            static::$suppressedUntil = null;
        } catch (Throwable $e) {
            if (static::$suppressedUntil === null) {
                Log::warning('Inertia DevTools: failed to persist entry: '.$e->getMessage());
            }

            static::$suppressedUntil = microtime(true) + self::SUPPRESS_SECONDS;
        }
    }

    public static function resetCircuitBreaker(): void
    {
        static::$suppressedUntil = null;
    }
}
