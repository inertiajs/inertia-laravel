<?php

namespace Inertia\Ssr;

enum SsrErrorType: string
{
    case BrowserApi = 'browser-api';
    case ComponentResolution = 'component-resolution';
    case Render = 'render';
    case Connection = 'connection';
    case Unknown = 'unknown';

    /**
     * Create an instance from a string value, defaulting to Unknown.
     */
    public static function fromString(?string $value): self
    {
        if ($value === null) {
            return self::Unknown;
        }

        return self::tryFrom($value) ?? self::Unknown;
    }
}
