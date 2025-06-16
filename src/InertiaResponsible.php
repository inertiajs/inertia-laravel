<?php

namespace Inertia;

interface InertiaResponsible
{
    public function toInertiaResponse(Prop $prop): mixed;
}
