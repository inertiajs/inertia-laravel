<?php

namespace Inertia\Testing\Concerns;

use Illuminate\Support\Arr;
use Inertia\Testing\Facades\InertiaTesting;
use PHPUnit\Framework\Assert as PHPUnit;

trait PageObject
{
    public function component(string $value = null, $shouldExist = null): self
    {
        PHPUnit::assertSame(
            InertiaTesting::resolveComponentName($value),
            $this->component,
            'Unexpected Inertia page component.'
        );

        if ($shouldExist || (is_null($shouldExist) && config('inertia.testing.ensure_pages_exist', true))) {
            InertiaTesting::findComponent($value);
        }

        return $this;
    }

    protected function prop(string $key = null)
    {
        return Arr::get($this->props, $key);
    }

    public function url(string $value): self
    {
        PHPUnit::assertSame($value, $this->url, 'Unexpected Inertia page url.');

        return $this;
    }

    public function version($value): self
    {
        PHPUnit::assertSame($value, $this->version, 'Unexpected Inertia asset version.');

        return $this;
    }

    public function toArray(): array
    {
        return [
            'component' => $this->component,
            'props' => $this->props,
            'url' => $this->url,
            'version' => $this->version,
        ];
    }
}
