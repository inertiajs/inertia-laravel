<?php

namespace Inertia;

use BackedEnum;
use JsonSerializable;
use ReflectionClass;
use SplObjectStorage;
use stdClass;
use UnitEnum;

trait PreservesBigIntegers
{
    /**
     * The key a big integer is transported under.
     *
     * @link https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/BigInt#use_within_json
     */
    protected const BIG_INTEGER_KEY = '$bigint';

    /**
     * The largest integer JavaScript represents without losing precision.
     */
    protected const MAX_SAFE_INTEGER = 9007199254740991;

    /**
     * Indicates if integers outside JavaScript's safe range should be preserved.
     */
    protected bool $preserveBigIntegers = false;

    /**
     * Wrap big integers only when this response opted in.
     */
    protected function encodeBigIntegersWhenEnabled(mixed $value): mixed
    {
        return $this->preserveBigIntegers ? $this->encodeBigIntegers($value) : $value;
    }

    /**
     * Wrap integers outside JavaScript's safe integer range as a marker so the
     * frontend can revive them as native BigInt values without losing
     * precision when the JSON response is parsed.
     *
     * @param  SplObjectStorage<object, mixed>|null  $seen
     */
    protected function encodeBigIntegers(mixed $value, ?SplObjectStorage $seen = null): mixed
    {
        if (is_int($value)) {
            return $value > static::MAX_SAFE_INTEGER || $value < -static::MAX_SAFE_INTEGER
                ? [static::BIG_INTEGER_KEY => (string) $value]
                : $value;
        }

        if (is_array($value)) {
            return $this->encodeBigIntegersInArray($value, $seen);
        }

        if (! is_object($value)) {
            return $value;
        }

        $seen ??= new SplObjectStorage;

        // Only ancestors are tracked, so a self-reference stops here and is left
        // for json_encode to report, while a value shared by two branches is
        // still encoded in both.
        if ($seen->offsetExists($value)) {
            return $value;
        }

        $seen->offsetSet($value);

        $encoded = $this->encodeBigIntegersInObject($value, $seen);

        $seen->offsetUnset($value);

        return $encoded;
    }

    /**
     * Wrap the big integers held by the given array.
     *
     * @param  array<array-key, mixed>  $value
     * @param  SplObjectStorage<object, mixed>|null  $seen
     * @return array<array-key, mixed>
     */
    protected function encodeBigIntegersInArray(array $value, ?SplObjectStorage $seen): array
    {
        foreach ($value as $key => $nested) {
            $value[$key] = $this->encodeBigIntegers($nested, $seen);
        }

        return $value;
    }

    /**
     * Wrap the big integers held by the given object, unwrapping it the same
     * way json_encode would so the shape it emits is unchanged. Objects are
     * cast back so they keep serializing as a JSON object, not an array.
     *
     * @param  SplObjectStorage<object, mixed>  $seen
     */
    protected function encodeBigIntegersInObject(object $value, SplObjectStorage $seen): mixed
    {
        if ($value instanceof BackedEnum) {
            return $this->encodeBigIntegers($value->value, $seen);
        }

        // A pure enum has no JSON representation. Walking its properties would
        // invent one, so it is left for json_encode to reject as before.
        if ($value instanceof UnitEnum) {
            return $value;
        }

        if ($value instanceof JsonSerializable) {
            return $this->encodeBigIntegers($value->jsonSerialize(), $seen);
        }

        // An internal class such as DateTime, or a class extending one, serializes
        // through its own handler, so walking its public properties would change
        // the shape rather than just the integers.
        if (! $this->hasPlainJsonRepresentation($value)) {
            return $value;
        }

        return (object) $this->encodeBigIntegersInArray(get_object_vars($value), $seen);
    }

    /**
     * Determine if the object's public properties are what json_encode emits.
     */
    protected function hasPlainJsonRepresentation(object $value): bool
    {
        if ($value instanceof stdClass) {
            return true;
        }

        foreach ([$value::class, ...class_parents($value)] as $class) {
            if ((new ReflectionClass($class))->isInternal()) {
                return false;
            }
        }

        return true;
    }
}
