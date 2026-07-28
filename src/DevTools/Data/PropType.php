<?php

namespace Inertia\DevTools\Data;

enum PropType: string
{
    case Always = 'always';
    case Defer = 'defer';
    case Optional = 'optional';
    case Merge = 'merge';
    case Scroll = 'scroll';
    case Once = 'once';
}
