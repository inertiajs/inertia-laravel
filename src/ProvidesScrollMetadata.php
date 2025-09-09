<?php

namespace Inertia;

interface ProvidesScrollMetadata
{
    /**
     * Get the page name parameter.
     */
    public function getPageName(): string;

    /**
     * Get the previous page identifier.
     */
    public function getPreviousPage(): int|string|null;

    /**
     * Get the next page identifier.
     */
    public function getNextPage(): int|string|null;

    /**
     * Get the current page identifier.
     */
    public function getCurrentPage(): int|string|null;
}
