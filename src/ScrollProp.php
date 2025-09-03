<?php

namespace Inertia;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Inertia\Support\Header;

/**
 * Represents a paginated property that can be merged during partial reloads.
 *
 * This class provides functionality for handling pagination data with merge capabilities,
 * allowing paginated content to be appended or prepended during client-side navigation.
 */
class ScrollProp implements Mergeable
{
    use MergesProps;

    /**
     * The property value.
     *
     * Merged with existing client-side data during partial reloads.
     *
     * @var mixed
     */
    protected $value;

    /**
     * The resolved property value.
     *
     * @var mixed
     */
    protected $resolved;

    /**
     * The wrapper key for the data array.
     *
     * @var string
     */
    protected $wrapper;

    /**
     * The callback to generate pagination meta information.
     *
     * @var callable|null
     */
    protected $metaResolver;

    /**
     * Create a new merge property instance. Merge properties are combined
     * with existing client-side data during partial reloads instead of
     * completely replacing the property value.
     *
     * @param  mixed  $value
     * @param  callable|array{string, mixed}|null  $meta
     */
    public function __construct($value, string $wrapper = 'data', callable|array|null $meta = null)
    {
        $this->value = $value;
        $this->metaResolver = is_array($meta) ? fn () => $meta : $meta;
        $this->wrapper = $wrapper;
    }

    /**
     * Set the merge strategy for the paginated data.
     */
    public function setMergeStrategy(?Request $request = null): self
    {
        $request ??= request();

        return $request->header(Header::SCROLL_DIRECTION) !== 'up'
            ? $this->append($this->wrapper)
            : $this->prepend($this->wrapper);
    }

    /**
     * Get the pagination meta information.
     *
     * @return mixed
     */
    public function meta()
    {
        $paginator = $this();

        $meta = $this->metaResolver
            ? call_user_func($this->metaResolver, $paginator)
            : PaginatorMeta::from($paginator);

        return $meta instanceof Arrayable ? $meta->toArray() : $meta;
    }

    /**
     * Resolve the property value.
     *
     * @return mixed
     */
    public function __invoke()
    {
        if (isset($this->resolved)) {
            return $this->resolved;
        }

        return $this->resolved = is_callable($this->value) ? App::call($this->value) : $this->value;
    }
}
