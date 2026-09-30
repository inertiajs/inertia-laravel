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
    public const MARKER = '$bigint';

    /**
     * The digits a marker must hold to be decoded. Mirrors the client.
     */
    protected const INTEGER_PATTERN = '/^(0|-?[1-9]\d*)$/';

    /**
     * The largest integer JavaScript represents without losing precision.
     */
    protected const MAX_SAFE_INTEGER = 9007199254740991;

    /**
     * Whether integers outside JavaScript's safe range should be preserved.
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
                ? [static::MARKER => (string) $value]
                : $value;
        }

        if (is_array($value)) {
            return $this->encodeBigIntegersInArray($value, $seen);
        }

        if (! is_object($value)) {
            return $value;
        }

        $seen ??= new SplObjectStorage;

        // A self-referencing value would otherwise recurse until the stack is
        // exhausted, where json_encode reports it cleanly instead. Only the
        // ancestors are tracked, so a value shared by two branches is still
        // encoded in both.
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

        // An internal class such as DateTime serializes through its own handler,
        // so its public properties are not what json_encode emits and walking
        // them would change the shape rather than just the integers.
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
        static $plain = [];

        $class = $value::class;

        if (! isset($plain[$class])) {
            $plain[$class] = $value instanceof stdClass || ! (new ReflectionClass($class))->isInternal();
        }

        return $plain[$class];
    }

    /**
     * Recursively decode `{"$bigint": "<value>"}` markers in the request data
     * back into integers so controllers and validation receive the original
     * value the frontend sent as a native BigInt.
     *
     * @param  array<array-key, mixed>  $input
     * @return array<array-key, mixed>
     */
    protected function decodeBigIntegers(array $input): array
    {
        foreach ($input as $key => $value) {
            if (! is_array($value)) {
                continue;
            }

            $input[$key] = $this->isBigIntegerMarker($value)
                ? $this->decodeBigInteger($value[static::MARKER])
                : $this->decodeBigIntegers($value);
        }

        return $input;
    }

    /**
     * Determine if the given array is a big integer marker.
     *
     * @param  array<array-key, mixed>  $value
     */
    protected function isBigIntegerMarker(array $value): bool
    {
        return count($value) === 1
            && isset($value[static::MARKER])
            && is_string($value[static::MARKER])
            && preg_match(static::INTEGER_PATTERN, $value[static::MARKER]) === 1;
    }

    /**
     * Decode a big integer marker's digits. Values within PHP's integer range
     * become a native integer; anything larger is kept as a string since PHP
     * cannot represent it as an integer without losing precision.
     */
    protected function decodeBigInteger(string $digits): int|string
    {
        $asInteger = (int) $digits;

        return (string) $asInteger === $digits ? $asInteger : $digits;
    }
}
