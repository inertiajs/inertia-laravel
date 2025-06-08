<?php

namespace Inertia;

use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;
use ReflectionFunction;

trait DeepMergesSharedProps
{
    /**
     * Recursively merges multiple shared Inertia props within the current request.
     * This method ensures that overlapping keys between multiple sets of props
     * are merged deeply instead of overwritten, preserving nested structures.
     */
    protected function deepMergeSharedProps(array $props, array $sharedProps = []): array
    {
        foreach ($props as $key => $prop) {
            $propArray = $this->attemptArrayCast($prop);
            $sharedPropArray = $this->attemptArrayCast(Arr::get($sharedProps, $key));

            $shouldFlattenPropArray = is_int($key) && is_array($propArray);
            if ($shouldFlattenPropArray) {
                $sharedProps = $this->deepMergeSharedProps($propArray, $sharedProps);

                continue;
            }

            $shouldOverride = ! is_array($propArray) || ! is_array($sharedPropArray);
            if ($shouldOverride) {
                Arr::set($sharedProps, $key, $propArray);

                continue;
            }

            $shouldConcatenate = $this->isIndexedArray($propArray) && $this->isIndexedArray($sharedPropArray);
            if ($shouldConcatenate) {
                Arr::set($sharedProps, $key, array_merge($sharedPropArray, $propArray));

                continue;
            }

            Arr::set($sharedProps, $key, $this->deepMergeSharedProps($propArray, $sharedPropArray));
        }

        return $sharedProps;
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
