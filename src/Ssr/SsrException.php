<?php

namespace Inertia\Ssr;

use Exception;

class SsrException extends Exception
{
    /**
     * Create a new SSR exception from a render failure event.
     */
    public static function fromEvent(SsrRenderFailed $event): self
    {
        $message = sprintf(
            'SSR render failed for component [%s]: %s',
            $event->component(),
            $event->error
        );

        if ($event->sourceLocation) {
            $message .= sprintf(' at %s', $event->sourceLocation);
        }

        $exception = new self($message);
        $exception->event = $event;

        return $exception;
    }

    /**
     * The SSR render failed event containing error details.
     */
    public ?SsrRenderFailed $event = null;

    /**
     * Get the component that failed to render.
     */
    public function component(): ?string
    {
        return $this->event?->component();
    }

    /**
     * Get the error type.
     */
    public function type(): ?SsrErrorType
    {
        return $this->event?->type;
    }

    /**
     * Get the hint for fixing the error.
     */
    public function hint(): ?string
    {
        return $this->event?->hint;
    }

    /**
     * Get the source location where the error occurred.
     */
    public function sourceLocation(): ?string
    {
        return $this->event?->sourceLocation;
    }
}
