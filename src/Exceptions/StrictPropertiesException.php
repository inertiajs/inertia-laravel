<?php

namespace Inertia\Exceptions;

class StrictPropertiesException extends \Exception
{
    public static function for(string $key): self
    {
        return new static("Prop \"{$key}\" is shared without serialization rules.");
    }
}
