<?php

namespace Inertia\DevTools\Http;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\DevTools\EntriesRepository;

class EntriesController
{
    public function __construct(protected EntriesRepository $repository) {}

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function index(Request $request): Collection
    {
        $component = $request->query('component');
        $include = $this->typeList($request->query('type'));
        $exclude = $this->typeList($request->query('exclude'));
        $offset = max(0, (int) $request->query('offset', 0));
        $limit = $request->query('limit');

        return collect($this->repository->all())
            ->when(is_string($component) && $component !== '', fn ($entries) => $entries->where('component', $component))
            ->when($include !== [], fn ($entries) => $entries->whereIn('requestType', $include))
            ->when($exclude !== [], fn ($entries) => $entries->whereNotIn('requestType', $exclude))
            ->when($offset > 0, fn ($entries) => $entries->slice($offset))
            ->when(is_numeric($limit), fn ($entries) => $entries->take(max(1, (int) $limit)))
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    public function show(string $id): array
    {
        if (! Str::isUlid($id)) {
            abort(404, 'Not found.');
        }

        return $this->repository->get($id) ?? abort(404, 'Not found.');
    }

    /**
     * Parse a comma-separated request-type query value into a list.
     *
     * @return array<int, string>
     */
    protected function typeList(mixed $value): array
    {
        if (! is_string($value) || $value === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $value))));
    }
}
