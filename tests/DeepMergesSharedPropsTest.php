<?php

namespace Inertia\Tests;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Inertia\DeepMergesSharedProps;
use Inertia\Tests\Stubs\FakeResource;

class DeepMergesSharedPropsTest extends TestCase
{
    protected object $sharedPropsDeepMerger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sharedPropsDeepMerger = new class
        {
            use DeepMergesSharedProps;

            public function handle(array $props, array $sharedProps = []): array
            {
                return $this->deepMergeSharedProps($props, $sharedProps);
            }
        };
    }

    public function test_it_merges_props(): void
    {
        $props = ['auth.user.can' => ['edit']];
        $sharedProps = [];
        Arr::set($sharedProps, 'auth.user.can', ['view']);

        $result = $this->sharedPropsDeepMerger->handle($props, $sharedProps);

        $this->assertEquals([
            'auth' => [
                'user' => [
                    'can' => [
                        'view',
                        'edit',
                    ],
                ],
            ],
        ], $result);
    }

    public function test_it_adds_props_with_different_keys_without_merging(): void
    {
        $props = ['user' => ['John Doe']];
        $sharedProps = ['page' => ['user.show']];

        $result = $this->sharedPropsDeepMerger->handle($props, $sharedProps);

        $this->assertEquals([
            'user' => ['John Doe'],
            'page' => ['user.show'],
        ], $result);
    }

    public function test_it_overrides_existing_values(): void
    {
        $props = ['page' => 'user.index'];
        $sharedProps = ['page' => 'user.show'];

        $result = $this->sharedPropsDeepMerger->handle($props, $sharedProps);

        $this->assertEquals(['page' => 'user.index'], $result);
    }

    public function test_it_flattens_nested_props_when_the_root_key_is_not_associative(): void
    {
        $result = $this->sharedPropsDeepMerger->handle([
            'user' => 'John Doe',
            ['gender' => 'male'],
            [['age' => 40]],
            'hobbies' => ['tennis', 'chess'],
        ], ['id' => 1]);

        $this->assertEquals([
            'id' => 1,
            'user' => 'John Doe',
            'gender' => 'male',
            'age' => 40,
            'hobbies' => ['tennis', 'chess'],
        ], $result);
    }

    public function test_it_can_handle_an_arrayable(): void
    {
        $arrayable = new Collection([
            'auth.user' => 'John Doe',
        ]);

        $result = $this->sharedPropsDeepMerger->handle([$arrayable]);

        $this->assertEquals([
            'auth' => [
                'user' => 'John Doe',
            ],
        ], $result);
    }

    public function test_it_can_merge_arrayables(): void
    {
        $result = $this->sharedPropsDeepMerger->handle(
            [
                'auth.user' => new Collection(['name' => 'John Doe']),
            ],
            [
                'auth' => [
                    'user' => new Collection(['id' => 1]),
                ],
            ],
        );

        $this->assertEquals([
            'auth' => [
                'user' => [
                    'id' => 1,
                    'name' => 'John Doe',
                ],
            ],
        ], $result);
    }

    public function test_it_can_merge_callables(): void
    {
        $result = $this->sharedPropsDeepMerger->handle(
            [
                'auth' => fn (): array => [
                    'user' => [
                        'name' => 'John Doe',
                    ],
                ],
            ],
            [
                'auth' => fn (): array => [
                    'user' => [
                        'id' => 1,
                    ],
                ],
            ],
        );

        $this->assertEquals([
            'auth' => [
                'user' => [
                    'id' => 1,
                    'name' => 'John Doe',
                ],
            ],
        ], $result);
    }

    public function test_it_overrides_values_with_unmergeable_types(): void
    {
        $props = [
            'resource' => new FakeResource(['Replacement']),
            'scalar' => 'Replacement',
            'uncallable' => fn (string $value): string => 'Replacement',
        ];
        $sharedProps = [
            'resource' => new FakeResource(['Original']),
            'scalar' => 'Original',
            'uncallable' => fn (string $value): string => 'Original',
        ];

        $result = $this->sharedPropsDeepMerger->handle($props, $sharedProps);

        $this->assertEquals($props, $result);
    }

    public function test_it_deep_merges_arrayables_and_arrays(): void
    {
        $result = $this->sharedPropsDeepMerger->handle([
            new Collection([
                'auth.user' => [
                    'name' => 'John Doe',
                ],
            ]),
            [
                'auth.user.can' => [
                    'edit_profile',
                ],
            ],
        ], [
            'auth' => [
                'user' => new Collection([
                    'id' => 1,
                    'can' => new Collection(['delete_profile']),
                ]),
            ],
        ]);

        $this->assertEquals([
            'auth' => [
                'user' => [
                    'name' => 'John Doe',
                    'id' => 1,
                    'can' => [
                        'delete_profile',
                        'edit_profile',
                    ],
                ],
            ],
        ], $result);
    }

    public function test_it_flattens_and_merges_nested_props(): void
    {
        $result = $this->sharedPropsDeepMerger->handle([
            [
                'auth.user.can.manage_profiles' => true,
            ],
        ], [
            'auth' => [
                'user' => [
                    'can' => [
                        'edit_profile' => false,
                        'delete_profile' => false,
                    ],
                ],
            ],
        ]);

        $this->assertEquals([
            'auth' => [
                'user' => [
                    'can' => [
                        'edit_profile' => false,
                        'delete_profile' => false,
                        'manage_profiles' => true,
                    ],
                ],
            ],
        ], $result);
    }
}
