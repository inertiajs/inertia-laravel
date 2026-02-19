<?php

namespace Inertia;

class ResolvedProps
{
    /**
     * Create a new resolved props instance.
     *
     * @param  array<string, mixed>  $props
     * @param  array<string, array<int, string>>  $deferredProps
     * @param  array<int, string>  $mergeProps
     * @param  array<int, string>  $prependProps
     * @param  array<int, string>  $deepMergeProps
     * @param  array<int, string>  $matchPropsOn
     * @param  array<string, array<string, mixed>>  $scrollProps
     * @param  array<string, array<string, mixed>>  $onceProps
     */
    public function __construct(
        public readonly array $props,
        protected readonly array $deferredProps = [],
        protected readonly array $mergeProps = [],
        protected readonly array $prependProps = [],
        protected readonly array $deepMergeProps = [],
        protected readonly array $matchPropsOn = [],
        protected readonly array $scrollProps = [],
        protected readonly array $onceProps = [],
    ) {
        //
    }

    /**
     * Get the non-empty metadata arrays for the page response.
     *
     * @return array<string, mixed>
     */
    public function metadata(): array
    {
        return array_filter([
            'mergeProps' => $this->mergeProps,
            'prependProps' => $this->prependProps,
            'deepMergeProps' => $this->deepMergeProps,
            'matchPropsOn' => $this->matchPropsOn,
            'deferredProps' => $this->deferredProps,
            'scrollProps' => $this->scrollProps,
            'onceProps' => $this->onceProps,
        ], fn ($value) => count($value) > 0);
    }
}
