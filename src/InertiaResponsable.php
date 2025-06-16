<?php

namespace Inertia;

interface InertiaResponsable
{
    public function toInertiaResponse(Prop $prop): mixed;
}
