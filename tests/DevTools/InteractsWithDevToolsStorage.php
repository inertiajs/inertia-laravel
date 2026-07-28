<?php

namespace Inertia\Tests\DevTools;

use Inertia\DevTools\EntriesRepository;

/**
 * Binds a real file-backed EntriesRepository over a throwaway temp directory, so devtools
 * tests exercise the actual storage rather than an in-memory double that could drift from it.
 */
trait InteractsWithDevToolsStorage
{
    protected EntriesRepository $repo;

    protected string $devtoolsStoragePath;

    protected function bindEntriesRepository(): void
    {
        $this->devtoolsStoragePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'inertia-devtools-'.bin2hex(random_bytes(8));

        $this->repo = new EntriesRepository(path: $this->devtoolsStoragePath, autoPruneHours: 24);

        $this->app->instance(EntriesRepository::class, $this->repo);
    }

    protected function clearDevToolsStorage(): void
    {
        if (isset($this->devtoolsStoragePath) && is_dir($this->devtoolsStoragePath)) {
            $this->removeDirectory($this->devtoolsStoragePath);
        }
    }

    /**
     * Recorded entry metadata, newest first.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function recordedEntries(): array
    {
        return $this->repo->all();
    }

    /**
     * The full payload of the most recently recorded entry.
     *
     * @return array<string, mixed>|null
     */
    protected function latestRecordedEntry(): ?array
    {
        $metas = $this->recordedEntries();

        return $metas === [] ? null : $this->repo->get($metas[0]['id']);
    }

    protected function removeDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        foreach (scandir($path) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $full = $path.DIRECTORY_SEPARATOR.$item;

            is_dir($full) ? $this->removeDirectory($full) : @unlink($full);
        }

        @rmdir($path);
    }
}
