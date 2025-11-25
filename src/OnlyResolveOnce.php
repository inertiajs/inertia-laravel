<?php

namespace Inertia;

interface OnlyResolveOnce
{
    public function once(): static;

    public function shouldResolveOnce(): bool;
}
