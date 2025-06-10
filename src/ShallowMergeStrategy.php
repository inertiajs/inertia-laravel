<?php

namespace Inertia;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;

class ShallowMergeStrategy implements MergeStrategy
{
    public function merge(array $original, string|array|Arrayable $key, mixed $value = null): array
    {
        if (is_array($key)) {
            $original = array_merge($original, $key);
        } elseif ($key instanceof Arrayable) {
            $original = array_merge($original, $key->toArray());
        } else {
            Arr::set($original, $key, $value);
        }

        return $original;
    }
}
