<?php

namespace Inertia\DevTools;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

class EntriesRepository
{
    protected const INDEX_FILE = '_meta.json';

    protected const LAST_PRUNE_FILE = '_last_prune';

    public function __construct(
        protected string $path,
        protected int $autoPruneHours = 24,
        protected Filesystem $files = new Filesystem,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function save(string $id, array $data): void
    {
        if (! $this->isValidEntryId($id)) {
            throw new InvalidArgumentException('Invalid Inertia DevTools entry id.');
        }

        $encoded = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $this->ensureDirectory();
        // Filesystem::replace writes to a temp file and renames it into place, so an interrupted
        // write may never leave a half-written entry: readers see the previous file or the new one.
        $this->files->replace($this->filePath($id), $encoded);
        $this->writeIndexMeta($id, $this->normalizeIndexMeta($data['__meta'] ?? []));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $id): ?array
    {
        if (! $this->isValidEntryId($id)) {
            return null;
        }

        $file = $this->filePath($id);

        if (! $this->files->exists($file)) {
            return null;
        }

        $decoded = $this->readJsonFile($file);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * All recorded entry metadata, newest first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        return collect($this->readIndex())
            ->sortByDesc(fn (array $meta) => (string) ($meta['id'] ?? ''))
            ->values()
            ->all();
    }

    public function prune(int $hours): void
    {
        if (! $this->files->isDirectory($this->path)) {
            return;
        }

        $cutoff = microtime(true) - ($hours * 3600);

        $expired = collect($this->readIndex())
            ->filter(fn (array $meta) => ($meta['utime'] ?? 0) < $cutoff)
            ->keys()
            ->all();

        $this->deleteEntries($expired);
    }

    public function enforceTabLimit(string $tabUuid, int $limit): void
    {
        if ($limit <= 0 || ! $this->files->isDirectory($this->path)) {
            return;
        }

        // Keep the newest $limit entries for the tab; delete everything older.
        $drop = collect($this->readIndex())
            ->where('tabUuid', $tabUuid)
            ->sortByDesc('id')
            ->slice($limit)
            ->keys()
            ->all();

        $this->deleteEntries($drop);
    }

    public function pruneIfDue(): void
    {
        $intervalSeconds = config()->integer('inertia.devtools.storage.prune_interval', 300);

        if ($intervalSeconds <= 0) {
            $this->prune($this->autoPruneHours);

            return;
        }

        $this->ensureDirectory();

        $lastPrunedAt = $this->readLastPrunedAt();

        if ($lastPrunedAt !== null && (time() - $lastPrunedAt) < $intervalSeconds) {
            return;
        }

        $this->prune($this->autoPruneHours);
        $this->writeLastPrunedAt(time());
    }

    protected function ensureDirectory(): void
    {
        $this->files->ensureDirectoryExists($this->path, 0700);

        $gitignore = $this->path.DIRECTORY_SEPARATOR.'.gitignore';

        if ($this->files->missing($gitignore)) {
            $this->files->put($gitignore, "*\n");
        }
    }

    protected function filePath(string $id): string
    {
        return $this->path.DIRECTORY_SEPARATOR.$id.'.json';
    }

    protected function isValidEntryId(string $id): bool
    {
        return Str::isUlid($id);
    }

    protected function indexPath(): string
    {
        return $this->path.DIRECTORY_SEPARATOR.self::INDEX_FILE;
    }

    protected function lastPrunePath(): string
    {
        return $this->path.DIRECTORY_SEPARATOR.self::LAST_PRUNE_FILE;
    }

    /**
     * Normalize an entry's __meta for storage in the index. The full meta is kept so
     * the list endpoint may filter and render without reading every entry file; the
     * fields the storage layer relies on are coerced to known types.
     *
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    protected function normalizeIndexMeta(array $meta): array
    {
        return array_merge($meta, [
            'id' => (string) ($meta['id'] ?? ''),
            'tabUuid' => isset($meta['tabUuid']) && is_string($meta['tabUuid']) && $meta['tabUuid'] !== '' ? $meta['tabUuid'] : null,
            'utime' => isset($meta['utime']) ? (float) $meta['utime'] : microtime(true),
        ]);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function readIndex(): array
    {
        // A missing or corrupt index (e.g. a write interrupted mid-rewrite) must not lose the
        // entries: the per-entry files are the source of truth, so rebuild the index from them.
        if ($this->files->missing($this->indexPath())) {
            return $this->rebuildIndexFromFiles();
        }

        $decoded = $this->readJsonFile($this->indexPath());

        if (! is_array($decoded)) {
            return $this->rebuildIndexFromFiles();
        }

        return collect($decoded)
            ->filter(fn ($meta) => is_array($meta))
            ->map(fn (array $meta) => $this->normalizeIndexMeta($meta))
            ->all();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function rebuildIndexFromFiles(): array
    {
        $index = $this->metaFromFiles();

        if ($index !== []) {
            $this->mutateIndex(fn () => $index);
        }

        return $index;
    }

    /**
     * Scan the per-entry files and build the index map straight from their `__meta`.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function metaFromFiles(): array
    {
        return collect($this->jsonFiles())
            ->map(fn (string $file) => $this->readMeta($file))
            ->filter(fn (?array $meta) => $meta !== null && (string) ($meta['id'] ?? '') !== '')
            ->keyBy(fn (array $meta) => (string) $meta['id'])
            ->map(fn (array $meta) => $this->normalizeIndexMeta($meta))
            ->all();
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function writeIndexMeta(string $id, array $meta): void
    {
        $this->mutateIndex(function (array $index) use ($id, $meta) {
            $index[$id] = $this->normalizeIndexMeta($meta);

            return $index;
        });
    }

    /**
     * @param  callable(array<string, array<string, mixed>>): array<string, array<string, mixed>>  $mutator
     */
    protected function mutateIndex(callable $mutator): void
    {
        $this->ensureDirectory();

        $handle = @fopen($this->indexPath(), 'c+');

        if ($handle === false) {
            return;
        }

        try {
            if (! flock($handle, LOCK_EX)) {
                return;
            }

            $contents = stream_get_contents($handle);
            $decoded = is_string($contents) && $contents !== '' ? json_decode($contents, true) : [];
            // A corrupt on-disk index would otherwise be treated as empty, so this rewrite would
            // drop every prior entry's meta. Reseed from the entry files before applying the change.
            $index = is_array($decoded) ? $decoded : $this->metaFromFiles();
            $index = $mutator($index);

            rewind($handle);
            ftruncate($handle, 0);
            fwrite($handle, (string) json_encode($index, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            fflush($handle);
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }
    }

    /**
     * Delete entry files and remove them from the index in a single rewrite.
     *
     * @param  array<int, string>  $ids
     */
    protected function deleteEntries(array $ids): void
    {
        if ($ids === []) {
            return;
        }

        foreach ($ids as $id) {
            if ($this->isValidEntryId($id)) {
                $this->files->delete($this->filePath($id));
            }
        }

        $this->mutateIndex(function (array $index) use ($ids) {
            foreach ($ids as $id) {
                unset($index[$id]);
            }

            return $index;
        });
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function readMeta(string $file): ?array
    {
        $decoded = $this->readJsonFile($file);

        if (! is_array($decoded) || ! isset($decoded['__meta']) || ! is_array($decoded['__meta'])) {
            return null;
        }

        return $decoded['__meta'];
    }

    protected function readLastPrunedAt(): ?int
    {
        $contents = $this->readLockedTextFile($this->lastPrunePath());

        if ($contents === null || ! ctype_digit(trim($contents))) {
            return null;
        }

        return (int) trim($contents);
    }

    protected function writeLastPrunedAt(int $timestamp): void
    {
        $this->files->put($this->lastPrunePath(), (string) $timestamp, lock: true);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function readJsonFile(string $file): ?array
    {
        $contents = $this->readLockedTextFile($file);

        if ($contents === null) {
            return null;
        }

        $decoded = json_decode($contents, true);

        return is_array($decoded) ? $decoded : null;
    }

    protected function readLockedTextFile(string $file): ?string
    {
        if ($this->files->missing($file)) {
            return null;
        }

        try {
            // Filesystem::get with a lock takes a shared (LOCK_SH) read lock.
            return $this->files->get($file, lock: true);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return iterable<int, string>
     */
    protected function jsonFiles(): iterable
    {
        if (! $this->files->isDirectory($this->path)) {
            return;
        }

        foreach ($this->files->files($this->path) as $file) {
            if ($file->getExtension() !== 'json' || $file->getFilename() === self::INDEX_FILE) {
                continue;
            }

            yield $file->getPathname();
        }
    }
}
