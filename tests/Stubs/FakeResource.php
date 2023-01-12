<?php

namespace Inertia\Tests\Stubs;

use Illuminate\Http\Resources\Json\JsonResource;

class FakeResource extends JsonResource
{
    /**
     * The data that will be used.
     *
     * @var array
     */
    private $data;

    /**
     * The "data" wrapper that should be applied.
     *
     * @var string|null
     */
    public static $wrap = null;

    public function __construct(array $resource)
    {
        parent::__construct(null);
        $this->data = $resource;
    }

    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     */
    public function toArray($request): array
    {
        return $this->data;
    }
}
