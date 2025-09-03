<?php

namespace Inertia\Tests;

use Illuminate\Http\Request;
use Inertia\MergeProp;

class MergePropTest extends TestCase
{
    public function test_can_invoke_with_a_callback(): void
    {
        $mergeProp = new MergeProp(function () {
            return 'A merge prop value';
        });

        $this->assertSame('A merge prop value', $mergeProp());
    }

    public function test_can_invoke_with_a_non_callback(): void
    {
        $mergeProp = new MergeProp(['key' => 'value']);

        $this->assertSame(['key' => 'value'], $mergeProp());
    }

    public function test_can_resolve_bindings_when_invoked(): void
    {
        $mergeProp = new MergeProp(function (Request $request) {
            return $request;
        });

        $this->assertInstanceOf(Request::class, $mergeProp());
    }

    public function test_appends_by_default(): void
    {
        $mergeProp = new MergeProp([]);

        $this->assertTrue($mergeProp->shouldAppend());
        $this->assertFalse($mergeProp->hasNestedMergePaths());
        $this->assertSame([], $mergeProp->appendPaths());
        $this->assertSame([], $mergeProp->prependPaths());
        $this->assertSame([], $mergeProp->matchesOn());
    }

    public function test_prepends(): void
    {
        $mergeProp = (new MergeProp([]))->prepend();

        $this->assertFalse($mergeProp->shouldAppend());
        $this->assertFalse($mergeProp->hasNestedMergePaths());
        $this->assertSame([], $mergeProp->appendPaths());
        $this->assertSame([], $mergeProp->prependPaths());
        $this->assertSame([], $mergeProp->matchesOn());
    }

    public function test_appends_with_nested_merge_paths(): void
    {
        $mergeProp = (new MergeProp([]))->append('data');

        $this->assertTrue($mergeProp->shouldAppend());
        $this->assertTrue($mergeProp->hasNestedMergePaths());
        $this->assertSame(['data'], $mergeProp->appendPaths());
        $this->assertSame([], $mergeProp->prependPaths());
        $this->assertSame([], $mergeProp->matchesOn());
    }

    public function test_appends_with_nested_merge_paths_and_match_on(): void
    {
        $mergeProp = (new MergeProp([]))->append('data', 'id');

        $this->assertTrue($mergeProp->shouldAppend());
        $this->assertTrue($mergeProp->hasNestedMergePaths());
        $this->assertSame(['data'], $mergeProp->appendPaths());
        $this->assertSame([], $mergeProp->prependPaths());
        $this->assertSame(['data.id'], $mergeProp->matchesOn());
    }

    public function test_prepends_with_nested_merge_paths(): void
    {
        $mergeProp = (new MergeProp([]))->prepend('data');

        $this->assertTrue($mergeProp->shouldAppend());
        $this->assertTrue($mergeProp->hasNestedMergePaths());
        $this->assertSame([], $mergeProp->appendPaths());
        $this->assertSame(['data'], $mergeProp->prependPaths());
        $this->assertSame([], $mergeProp->matchesOn());
    }

    public function test_prepends_with_nested_merge_paths_and_match_on(): void
    {
        $mergeProp = (new MergeProp([]))->prepend('data', 'id');

        $this->assertTrue($mergeProp->shouldAppend());
        $this->assertTrue($mergeProp->hasNestedMergePaths());
        $this->assertSame([], $mergeProp->appendPaths());
        $this->assertSame(['data'], $mergeProp->prependPaths());
        $this->assertSame(['data.id'], $mergeProp->matchesOn());
    }

    public function test_append_with_nested_merge_paths_as_array(): void
    {
        $mergeProp = (new MergeProp([]))->append(['data', 'items']);

        $this->assertTrue($mergeProp->shouldAppend());
        $this->assertTrue($mergeProp->hasNestedMergePaths());
        $this->assertSame(['data', 'items'], $mergeProp->appendPaths());
        $this->assertSame([], $mergeProp->prependPaths());
        $this->assertSame([], $mergeProp->matchesOn());
    }

    public function test_append_with_nested_merge_paths_and_match_on_as_array(): void
    {
        $mergeProp = (new MergeProp([]))->append(['data' => 'id', 'items' => 'uid']);

        $this->assertTrue($mergeProp->shouldAppend());
        $this->assertTrue($mergeProp->hasNestedMergePaths());
        $this->assertSame(['data', 'items'], $mergeProp->appendPaths());
        $this->assertSame([], $mergeProp->prependPaths());
        $this->assertSame(['data.id', 'items.uid'], $mergeProp->matchesOn());
    }

    public function test_prepend_with_nested_merge_paths_as_array(): void
    {
        $mergeProp = (new MergeProp([]))->prepend(['data', 'items']);

        $this->assertTrue($mergeProp->shouldAppend());
        $this->assertTrue($mergeProp->hasNestedMergePaths());
        $this->assertSame([], $mergeProp->appendPaths());
        $this->assertSame(['data', 'items'], $mergeProp->prependPaths());
        $this->assertSame([], $mergeProp->matchesOn());
    }

    public function test_prepend_with_nested_merge_paths_and_match_on_as_array(): void
    {
        $mergeProp = (new MergeProp([]))->prepend(['data' => 'id', 'items' => 'uid']);

        $this->assertTrue($mergeProp->shouldAppend());
        $this->assertTrue($mergeProp->hasNestedMergePaths());
        $this->assertSame([], $mergeProp->appendPaths());
        $this->assertSame(['data', 'items'], $mergeProp->prependPaths());
        $this->assertSame(['data.id', 'items.uid'], $mergeProp->matchesOn());
    }

    public function test_mix_of_append_and_prepend_with_nested_merge_paths_and_match_on_as_array(): void
    {
        $mergeProp = (new MergeProp([]))
            ->append('data')
            ->append('users', 'id')
            ->append(['items' => 'uid', 'posts'])
            ->prepend('categories')
            ->prepend('companies', 'id')
            ->prepend(['tags' => 'name', 'comments']);

        $this->assertTrue($mergeProp->shouldAppend());
        $this->assertTrue($mergeProp->hasNestedMergePaths());
        $this->assertSame(['data', 'users', 'items', 'posts'], $mergeProp->appendPaths());
        $this->assertSame(['categories', 'companies', 'tags', 'comments'], $mergeProp->prependPaths());
        $this->assertSame(['users.id', 'items.uid', 'companies.id', 'tags.name'], $mergeProp->matchesOn());
    }
}
