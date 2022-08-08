<?php

namespace Inertia;

use ArrayAccess;

class OnlyNode implements ArrayAccess
{
    public function __construct(
        /** @property self[] */
        protected array $nodes = [],
        protected $isLeaf = false)
    {
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

    public function offsetGet($offset): mixed
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

    public function isEmpty(): bool
    {
        return empty($this->nodes);
    }

    /** @return self[] */
    public function getNodes(): array
    {
        return $this->nodes;
    }
}
