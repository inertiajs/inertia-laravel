<?php

namespace Inertia;

use stdClass;

trait EncodesBigIntegers
{
    /**
     * Determine whether integers outside JavaScript's safe range should be wrapped.
     */
    protected function shouldEncodeBigIntegers(): bool
    {
        return config()->boolean('inertia.preserve_big_integers', false);
    }

    /**
     * Wrap integers outside JavaScript's safe integer range as a marker so the
     * frontend can revive them as native BigInt values without losing
     * precision when the JSON response is parsed.
     *
     * @link https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/BigInt#use_within_json
     */
    protected function encodeBigIntegers(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(fn ($nested) => $this->encodeBigIntegers($nested), $value);
        }

        // Objects the props resolver left alone (empty or numerically keyed) are
        // cast back so they keep serializing as a JSON object, not an array.
        if ($value instanceof stdClass) {
            return (object) array_map(fn ($nested) => $this->encodeBigIntegers($nested), get_object_vars($value));
        }

        if (is_int($value) && ($value > 9007199254740991 || $value < -9007199254740991)) {
            return ['$bigint' => (string) $value];
        }

        return $value;
    }
}
