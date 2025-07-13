<?php

namespace Inertia\Testing\Concerns;

trait Debugging
{
    abstract protected function prop(?string $key = null);

    /**
     * Dump the value of a property for debugging.
     */
    public function dump(?string $prop = null): self
    {
        dump($this->prop($prop));

        return $this;
    }

    /**
     * Dump the value of a property and die.
     */
    public function dd(?string $prop = null): void
    {
        dd($this->prop($prop));
    }
}
