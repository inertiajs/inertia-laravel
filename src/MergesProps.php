<?php

namespace Inertia;


trait MergesProps
{
    protected $merge = false;

    public function merge()
    {
        $this->merge = true;

        return $this;
    }

    public function shouldMerge()
    {
        return $this->merge;
    }
}
