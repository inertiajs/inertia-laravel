<?php

namespace Inertia\Tests\Stubs;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FakeResource extends JsonResource
{
    /**
     * The data that will be used.
     *
     * @var array<string, mixed>
     */
    private array $data;

    /**
     * The "data" wrapper that should be applied.
     *
     * @var string|null
     */
    public static $wrap = null;

    /**
     * @param  array<string, mixed>  $resource
     */
    public function __construct(array $resource)
    {
        parent::__construct(null);
        $this->data = $resource;
    }

    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return $this->data;
    }
}
