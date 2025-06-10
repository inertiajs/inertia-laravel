<?php

namespace Inertia;

use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;
use ReflectionFunction;

class DeepMergeStrategy implements MergeStrategy
{
    /**
     * Recursively merges multiple shared Inertia props within the current request.
     * This method ensures that overlapping keys between multiple sets of props
     * are merged deeply instead of overwritten, preserving nested structures.
     */
    public function merge(array $original, string|array|Arrayable $key, mixed $value = null): array
    {
        $mergedProps = $original;

        $newProps = match (true) {
            is_string($key) => [$key => $value],
            is_array($key) => $key,
            $key instanceof Arrayable => $value->toArray(),
        };

        foreach ($newProps as $key => $prop) {
            $propArray = $this->attemptArrayCast($prop);
            $mergedPropArray = $this->attemptArrayCast(Arr::get($mergedProps, $key));

            $shouldFlattenPropArray = is_int($key) && is_array($propArray);
            if ($shouldFlattenPropArray) {
                $mergedProps = $this->merge($propArray, $mergedProps);

                continue;
            }

            $shouldOverride = ! is_array($propArray) || ! is_array($mergedPropArray);
            if ($shouldOverride) {
                Arr::set($mergedProps, $key, $propArray);

                continue;
            }

            $shouldConcatenate = $this->isIndexedArray($propArray) && $this->isIndexedArray($mergedPropArray);
            if ($shouldConcatenate) {
                Arr::set($mergedProps, $key, array_merge($mergedPropArray, $propArray));

                continue;
            }

            Arr::set($mergedProps, $key, $this->merge($propArray, $mergedPropArray));
        }

        return $mergedProps;
    }

    protected function isIndexedArray(array $array): bool
    {
        return array_keys($array) === range(0, count($array) - 1);
    }

    protected function attemptArrayCast(mixed $value): mixed
    {
        if ($value instanceof Closure) {
            $reflection = new ReflectionFunction($value);

            if (! $reflection->getNumberOfRequiredParameters()) {
                $value = call_user_func($value);
            }
        }

        if ($value instanceof Arrayable) {
            return $value->toArray();
        }

        return $value;
    }
}
