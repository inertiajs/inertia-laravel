<?php

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Inertia\ShallowMergeStrategy;
use Inertia\Tests\TestCase;

class ShallowMergeStrategyTest extends TestCase
{
    public function test_it_can_merge_props_with_different_keys(): void
    {
        $original = [
            'can' => [
                'view',
            ],
        ];
        $input = [
            'user' => [
                'name' => 'John Doe',
            ],
        ];

        $result = App::make(ShallowMergeStrategy::class)->merge($original, $input);

        $this->assertEquals([
            'can' => [
                'view',
            ],
            'user' => [
                'name' => 'John Doe',
            ],
        ], $result);
    }

    public function test_it_performs_a_shallow_merge(): void
    {
        $original = [
            'can' => [
                'view',
            ],
        ];
        $input = [
            'can' => [
                'edit',
            ],
        ];

        $result = App::make(ShallowMergeStrategy::class)->merge($original, $input);

        $this->assertEquals([
            'can' => [
                'edit',
            ],
        ], $result);
    }

    public function test_it_can_handle_arrayables(): void
    {
        $original = [
            'can' => [
                'edit',
            ],
        ];
        $input = new Collection([
            'user' => [
                'name' => 'John Doe',
            ],
        ]);

        $result = App::make(ShallowMergeStrategy::class)->merge($original, $input);

        $this->assertEquals([
            'can' => [
                'edit',
            ],
            'user' => [
                'name' => 'John Doe',
            ],
        ], $result);
    }

    public function test_it_can_append_values_when_using_a_specific_key(): void
    {
        $original = [
            'user' => [
                'id' => 1,
            ],
        ];

        $result = App::make(ShallowMergeStrategy::class)->merge($original, 'user.name', 'John Doe');

        $this->assertEquals([
            'user' => [
                'id' => 1,
                'name' => 'John Doe',
            ],
        ], $result);
    }

    public function test_it_replaces_the_original_value_for_known_keys(): void
    {
        $original = [
            'can' => [
                'view',
            ],
        ];

        $result = App::make(ShallowMergeStrategy::class)->merge($original, 'can', ['edit']);

        $this->assertEquals([
            'can' => [
                'edit',
            ],
        ], $result);
    }
}
