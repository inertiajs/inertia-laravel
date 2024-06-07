<?php

namespace Inertia\Tests\Stubs;

use Illuminate\Contracts\Support\Arrayable;

/**
 * @implements Arrayable<array-key, mixed>
 */
class AsArrayable implements Arrayable {
    /** @var array */
    protected $data = [];

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public static function make(array $data): self
    {
        return new self($data);
    }

    /**
     * Get the instance as an array.
     */
    public function toArray(): array
    {
        return $this->data;
    }
}
