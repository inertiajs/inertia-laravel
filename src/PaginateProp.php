<?php

namespace Inertia;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Facades\App;

/**
 * Represents a paginated property that can be merged during partial reloads.
 *
 * This class provides functionality for handling pagination data with merge capabilities,
 * allowing paginated content to be appended or prepended during client-side navigation.
 */
class PaginateProp implements Mergeable
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
    protected $meta;

    /**
     * Create a new merge property instance. Merge properties are combined
     * with existing client-side data during partial reloads instead of
     * completely replacing the property value.
     *
     * @param  mixed  $value
     */
    public function __construct($value, string $wrapper = 'data', ?callable $meta = null)
    {
        $this->value = $value;
        $this->meta = $meta;
        $this->wrapper = $wrapper;
    }

    /**
     * Set the merge strategy for the paginated data.
     *
     * @param  bool  $append  Whether to append (true) or prepend (false) the data
     */
    public function setMergeStrategy(bool $append): self
    {
        $append ? $this->append($this->wrapper) : $this->prepend($this->wrapper);

        return $this;
    }

    /**
     * Get the pagination meta information.
     *
     * @return mixed
     */
    public function meta()
    {
        $paginator = $this();

        $meta = $this->meta ? call_user_func($this->meta, $paginator) : PaginatorMeta::from($paginator);

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
