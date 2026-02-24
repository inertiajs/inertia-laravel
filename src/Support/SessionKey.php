<?php

namespace Inertia\Support;

class SessionKey
{
    /**
     * Session key for clearing the Inertia history.
     */
    public const CLEAR_HISTORY = 'inertia.clear_history';

    /**
     * Session key for flash data.
     */
    public const FLASH_DATA = 'inertia.flash_data';

    /**
     * Session key for preserving the URL fragment.
     */
    public const PRESERVE_FRAGMENT = 'inertia.preserve_fragment';
}
