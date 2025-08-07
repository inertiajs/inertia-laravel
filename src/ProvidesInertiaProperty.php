<?php

namespace Inertia;

interface ProvidesInertiaProperty
{
    public function toInertiaProp(PropContext $prop): mixed;
}
