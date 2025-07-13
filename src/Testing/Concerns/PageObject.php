<?php

namespace Inertia\Testing\Concerns;

use Illuminate\Support\Arr;
use InvalidArgumentException;
use PHPUnit\Framework\Assert as PHPUnit;

trait PageObject
{
    /**
     * Assert that the component name matches the expected value.
     */
    public function component(?string $value = null, $shouldExist = null): self
    {
        PHPUnit::assertSame($value, $this->component, 'Unexpected Inertia page component.');

        if ($shouldExist || (is_null($shouldExist) && config('inertia.testing.ensure_pages_exist', true))) {
            try {
                app('inertia.testing.view-finder')->find($value);
            } catch (InvalidArgumentException $exception) {
                PHPUnit::fail(sprintf('Inertia page component file [%s] does not exist.', $value));
            }
        }

        return $this;
    }

    /**
     * Assert that the URL matches the expected value.
     */
    public function url(string $value): self
    {
        PHPUnit::assertSame($value, $this->url, 'Unexpected Inertia page url.');

        return $this;
    }

    /**
     * Assert that the asset version matches the expected value.
     */
    public function version(string $value): self
    {
        PHPUnit::assertSame($value, $this->version, 'Unexpected Inertia asset version.');

        return $this;
    }

    /**
     * Convert the Inertia page to an array.
     */
    public function toArray(): array
    {
        return [
            'component' => $this->component,
            'props' => $this->props,
            'url' => $this->url,
            'version' => $this->version,
            'encryptHistory' => $this->encryptHistory,
            'clearHistory' => $this->clearHistory,
        ];
    }

    /**
     * Get a specific prop from the page props.
     */
    protected function prop(?string $key = null)
    {
        return Arr::get($this->props, $key);
    }
}
