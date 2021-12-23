<?php

namespace Inertia\Tests\Stubs;

use Illuminate\Http\Resources\Json\JsonResource;

class FakeResource extends JsonResource
{
    private array $data;

    public static $wrap = null;

    public function __construct(array $resource)
    {
        parent::__construct(null);
        $this->data = $resource;
    }

    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
       return $this->data;
    }
}
