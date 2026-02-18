<?php

namespace Inertia;

enum SessionKey: string
{
    /*
     * Session key for clearing the Inertia history.
     */
    case ClearHistory = 'inertia.clear_history';

    /**
     * Session key for retaining the URL fragment across redirects.
     */
    case RetainFragment = 'inertia.retain_fragment';

    /**
     * Session key for flash data.
     */
    case FlashData = 'inertia.flash_data';
}
