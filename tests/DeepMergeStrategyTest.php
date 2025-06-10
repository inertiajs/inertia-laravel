<?php

namespace Inertia\Tests;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Inertia\DeepMergeStrategy;
use Inertia\Tests\Stubs\FakeResource;

class DeepMergeStrategyTest extends TestCase
{
    public function test_it_merges_props(): void
    {
        $original = [
            'can' => ['view'],
        ];
        $input = [
            'can' => ['edit'],
        ];

        $result = App::make(DeepMergeStrategy::class)->merge($original, $input);

        $this->assertEquals([
            'can' => [
                'view',
                'edit',
            ],
        ], $result);
    }

    public function test_it_merges_props_by_key(): void
    {
        $original = [
            'can' => ['view'],
        ];

        $result = App::make(DeepMergeStrategy::class)->merge($original, 'can', ['edit']);

        $this->assertEquals([
            'can' => [
                'view',
                'edit',
            ],
        ], $result);
    }

    public function test_it_adds_props_with_different_keys_without_merging(): void
    {
        $original = ['pages' => ['user.show']];
        $input = ['users' => ['John Doe']];

        $result = App::make(DeepMergeStrategy::class)->merge($original, $input);

        $this->assertEquals([
            'users' => ['John Doe'],
            'pages' => ['user.show'],
        ], $result);
    }

    public function test_it_overrides_existing_values(): void
    {
        $original = ['page' => 'user.show'];
        $input = ['page' => 'user.index'];

        $result = App::make(DeepMergeStrategy::class)->merge($original, $input);

        $this->assertEquals([
            'page' => 'user.index',
        ], $result);
    }

    public function test_it_flattens_nested_props_when_the_root_key_is_not_associative(): void
    {
        $original = [
            'user' => [
                'id' => 1,
            ],
        ];
        $input = [
            'user' => [
                'name' => 'John Doe',
                'hobbies' => ['tennis', 'chess'],
            ],
            [
                'user' => [
                    'gender' => 'male',
                    'age' => 40,
                ],
            ],
        ];

        $result = App::make(DeepMergeStrategy::class)->merge($original, $input);

        $this->assertEquals([
            'user' => [
                'id' => 1,
                'name' => 'John Doe',
                'gender' => 'male',
                'age' => 40,
                'hobbies' => ['tennis', 'chess'],
            ],
        ], $result);
    }

    public function test_it_can_merge_arrayables(): void
    {
        $original = ['user' => new Collection(['id' => 1])];
        $input = ['user' => new Collection(['name' => 'John Doe'])];

        $result = App::make(DeepMergeStrategy::class)->merge($original, $input);

        $this->assertEquals([
            'user' => [
                'id' => 1,
                'name' => 'John Doe',
            ],
        ], $result);
    }

    public function test_it_can_merge_callables(): void
    {
        $original = [
            'user' => fn (): array => [
                'id' => 1,
            ],
        ];
        $input = [
            'user' => fn (): array => [
                'name' => 'John Doe',
            ],
        ];

        $result = App::make(DeepMergeStrategy::class)->merge($original, $input);

        $this->assertEquals([
            'user' => [
                'id' => 1,
                'name' => 'John Doe',
            ],
        ], $result);
    }

    public function test_it_overrides_values_with_unmergeable_types(): void
    {
        $original = [
            'resource' => new FakeResource(['Original']),
            'scalar' => 'Original',
            'uncallable' => fn (string $value): string => 'Original',
        ];
        $input = [
            'resource' => new FakeResource(['Replacement']),
            'scalar' => 'Replacement',
            'uncallable' => fn (string $value): string => 'Replacement',
        ];

        $result = App::make(DeepMergeStrategy::class)->merge($original, $input);

        $this->assertEquals($input, $result);
    }

    public function test_it_deep_merges_arrayables_and_arrays(): void
    {
        $original = [
            'auth' => [
                'user' => new Collection([
                    'id' => 1,
                    'can' => new Collection(['delete_profile']),
                ]),
            ],
        ];
        $input = [
            'auth.user' => new Collection([
                'name' => 'John Doe',
            ]),
            'auth.user.can' => [
                'edit_profile',
            ],
        ];

        $result = App::make(DeepMergeStrategy::class)->merge($original, $input);

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
        $original = [
            'can' => [
                'edit_profile' => false,
                'delete_profile' => false,
            ],
        ];
        $input = [
            [
                'can' => [
                    'manage_profiles' => true,
                ],
            ],
        ];

        $result = App::make(DeepMergeStrategy::class)->merge($original, $input);

        $this->assertEquals([
            'can' => [
                'edit_profile' => false,
                'delete_profile' => false,
                'manage_profiles' => true,
            ],
        ], $result);
    }
}
