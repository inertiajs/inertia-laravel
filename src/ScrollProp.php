<?php

namespace Inertia;

use Illuminate\Http\Request;
use Inertia\Support\Header;

/**
 * Represents a paginated property that can be merged during partial reloads.
 *
 * This class provides functionality for handling pagination data with merge capabilities,
 * allowing paginated content to be appended or prepended during client-side navigation.
 *
 * @template T
 */
class ScrollProp implements Deferrable, Mergeable
{
    use DefersProps, MergesProps, ResolvesCallables;

    /**
     * The property value.
     *
     * Merged with existing client-side data during partial reloads.
     *
     * @var T
     */
    protected $value;

    /**
     * The resolved property value.
     *
     * @var T
     */
    protected $resolved;

    /**
     * The wrapper key for the data array.
     *
     * @var string
     */
    protected $wrapper;

    /**
     * The scroll metadata provider.
     *
     * @var ProvidesScrollMetadata|callable(T): \Inertia\ProvidesScrollMetadata|null
     */
    protected $metadata;

    /**
     * Create a new merge property instance. Merge properties are combined
     * with existing client-side data during partial reloads instead of
     * completely replacing the property value.
     *
     * @param  T  $value
     * @param  ProvidesScrollMetadata|callable(T): \Inertia\ProvidesScrollMetadata|null  $metadata
     */
    public function __construct(mixed $value, string $wrapper = 'data', ProvidesScrollMetadata|callable|null $metadata = null)
    {
        $this->merge = true;
        $this->value = $value;
        $this->wrapper = $wrapper;
        $this->metadata = $metadata;
    }

    /**
     * Configure the merge strategy based on the infinite scroll merge intent header.
     *
     * The frontend InfiniteScroll component sends its merge intent directly,
     * eliminating the need for direction-based logic on the backend.
     */
    public function configureMergeIntent(?Request $request = null): static
    {
        $request ??= request();

        return $request->header(Header::INFINITE_SCROLL_MERGE_INTENT) === 'prepend'
            ? $this->prepend($this->wrapper)
            : $this->append($this->wrapper);
    }

    /**
     * Resolve the scroll metadata provider.
     */
    protected function resolveMetadataProvider(): ProvidesScrollMetadata
    {
        if ($this->metadata instanceof ProvidesScrollMetadata) {
            return $this->metadata;
        }

        $value = $this();

        if (is_null($this->metadata)) {
            return ScrollMetadata::fromPaginator($value);
        }

        return call_user_func($this->metadata, $value);
    }

    /**
     * Get the pagination meta information.
     *
     * @return array{pageName: string, previousPage: int|string|null, nextPage: int|string|null, currentPage: int|string|null}
     */
    public function metadata(): array
    {
        $metadataProvider = $this->resolveMetadataProvider();

        return [
            'pageName' => $metadataProvider->getPageName(),
            'previousPage' => $metadataProvider->getPreviousPage(),
            'nextPage' => $metadataProvider->getNextPage(),
            'currentPage' => $metadataProvider->getCurrentPage(),
        ];
    }

    /**
     * Resolve the property value.
     *
     * @return T
     */
    public function __invoke()
    {
        if (isset($this->resolved)) {
            return $this->resolved;
        }

        return $this->resolved = $this->resolveCallable($this->value);
    }
}
