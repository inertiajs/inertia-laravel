<?php

namespace Inertia;

enum SessionKey: string
{
    /*
     * Session key for clearing the Inertia history.
     */
    case ClearHistory = 'inertia.clear_history';

    /**
     * Session key for flash data.
     */
    case FlashData = 'inertia.flash_data';
}
