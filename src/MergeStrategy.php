<?php

namespace Inertia;

use Illuminate\Contracts\Support\Arrayable;

interface MergeStrategy
{
    public function merge(array $original, string|array|Arrayable $key, mixed $value = null): array;
}
