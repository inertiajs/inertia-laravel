<?php

namespace Inertia;

use ArrayAccess;

class OnlyNode implements ArrayAccess
{
    /** @var static[] */
    protected array $nodes = [];
    protected bool $isLeaf = false;

    public function __construct(array $nodes = [], bool $isLeaf = false)
    {
        $this->nodes = $nodes;
        $this->isLeaf = $isLeaf;
    }

    public function isLeaf(): bool
    {
        return $this->isLeaf;
    }

    public function setLeaf(bool $leaf = true)
    {
        $this->isLeaf = $leaf;
    }

    public function offsetExists($offset): bool
    {
        return isset($this->nodes[$offset]);
    }

    public function offsetGet($offset): static
    {
        return $this->nodes[$offset];
    }

    public function offsetSet($offset, $value): void
    {
        $this->nodes[$offset] = $value;
    }

    public function offsetUnset($offset): void
    {
        unset($this->nodes[$offset]);
    }
}
