<?php

namespace Inertia;

interface ProvidesInertiaProp
{
    public function toInertiaProp(PropContext $prop): mixed;
}
