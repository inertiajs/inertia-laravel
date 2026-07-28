<?php

namespace Inertia\Tests\DevTools;

use Illuminate\Http\Request;
use Inertia\DevTools\Data\PropType;
use Inertia\DevTools\DevToolsHeader;
use Inertia\DevTools\PropClassifier;
use Inertia\Inertia;
use Inertia\Support\Header;
use Inertia\Tests\TestCase;

/**
 * Per-branch matrix for PropClassifier. Each test exercises exactly one classification
 * branch and asserts the normalized array matches the wire values the extension renders
 * (see packages/devtools-extension/src/types.ts PropType and PropMeta).
 */
class PropClassifierTest extends TestCase
{
    /**
     * @param  array<string, string>  $headers
     * @return array{inertiaType: ?PropType, deferGroup: ?string, reset: bool, once: bool, mergeDirection: ?string, deepMerge: bool}
     */
    private function classify(string $path, mixed $prop, array $headers = []): array
    {
        $request = Request::create('/x', 'GET');

        foreach ($headers as $key => $value) {
            $request->headers->set($key, $value);
        }

        return (new PropClassifier)->classifyResolved($path, $prop, $request);
    }

    public function test_always_prop_classifies_as_always(): void
    {
        $result = $this->classify('user', Inertia::always('value'));

        $this->assertSame(PropType::Always, $result['inertiaType']);
        $this->assertNull($result['deferGroup']);
        $this->assertNull($result['mergeDirection']);
        $this->assertFalse($result['deepMerge']);
        $this->assertFalse($result['once']);
        $this->assertFalse($result['reset']);
    }

    public function test_optional_prop_classifies_as_optional(): void
    {
        $result = $this->classify('bio', Inertia::optional(fn () => 'value'));

        $this->assertSame(PropType::Optional, $result['inertiaType']);
        $this->assertNull($result['deferGroup']);
        $this->assertNull($result['mergeDirection']);
        $this->assertFalse($result['deepMerge']);
    }

    public function test_merge_prop_classifies_as_merge(): void
    {
        $result = $this->classify('items', Inertia::merge(['a']));

        $this->assertSame(PropType::Merge, $result['inertiaType']);
    }

    public function test_scroll_prop_classifies_as_scroll(): void
    {
        $result = $this->classify('feed', Inertia::scroll(['a']));

        $this->assertSame(PropType::Scroll, $result['inertiaType']);
    }

    public function test_once_prop_classifies_as_once_and_flags_once(): void
    {
        $result = $this->classify('config', Inertia::once(fn () => 'value'));

        $this->assertSame(PropType::Once, $result['inertiaType']);
        $this->assertTrue($result['once']);
    }

    public function test_plain_value_has_no_inertia_type(): void
    {
        $result = $this->classify('name', 'Alice');

        $this->assertNull($result['inertiaType']);
        $this->assertNull($result['deferGroup']);
        $this->assertNull($result['mergeDirection']);
        $this->assertFalse($result['deepMerge']);
        $this->assertFalse($result['once']);
    }

    public function test_defer_prop_outside_a_deferred_request_reads_as_regular(): void
    {
        $result = $this->classify('stats', Inertia::defer(fn () => 'value', 'groupA'));

        $this->assertNull($result['inertiaType']);
        $this->assertNull($result['deferGroup']);
    }

    public function test_defer_prop_in_a_deferred_request_classifies_as_defer_with_group(): void
    {
        $result = $this->classify('stats', Inertia::defer(fn () => 'value', 'groupA'), [
            DevToolsHeader::DEVTOOLS_DEFERRED => '1',
        ]);

        $this->assertSame(PropType::Defer, $result['inertiaType']);
        $this->assertSame('groupA', $result['deferGroup']);
    }

    public function test_defer_prop_in_a_deferred_request_falls_back_to_the_default_group(): void
    {
        $result = $this->classify('stats', Inertia::defer(fn () => 'value'), [
            DevToolsHeader::DEVTOOLS_DEFERRED => '1',
        ]);

        $this->assertSame(PropType::Defer, $result['inertiaType']);
        $this->assertSame('default', $result['deferGroup']);
    }

    public function test_scroll_prop_does_not_carry_a_defer_group_unless_deferred(): void
    {
        $result = $this->classify('feed', Inertia::scroll(['a']));

        $this->assertNull($result['deferGroup']);
    }

    public function test_deferrable_non_defer_prop_carries_its_group_when_deferred(): void
    {
        $result = $this->classify('feed', Inertia::scroll(['a'])->defer('scrollGroup'), [
            DevToolsHeader::DEVTOOLS_DEFERRED => '1',
        ]);

        $this->assertSame('scrollGroup', $result['deferGroup']);
        $this->assertSame(PropType::Scroll, $result['inertiaType']);
    }

    public function test_once_flag_tracks_onceable_state_independent_of_type(): void
    {
        $mergeOnce = $this->classify('items', Inertia::merge(['a'])->once());
        $this->assertSame(PropType::Merge, $mergeOnce['inertiaType']);
        $this->assertTrue($mergeOnce['once']);

        $mergePlain = $this->classify('items', Inertia::merge(['a']));
        $this->assertFalse($mergePlain['once']);
    }

    public function test_deferred_once_prop_flags_once(): void
    {
        $result = $this->classify('stats', Inertia::defer(fn () => 'value')->once(), [
            DevToolsHeader::DEVTOOLS_DEFERRED => '1',
        ]);

        $this->assertSame(PropType::Defer, $result['inertiaType']);
        $this->assertTrue($result['once']);
    }

    public function test_root_append_merge_direction(): void
    {
        $result = $this->classify('items', Inertia::merge(['a']));

        $this->assertSame('append', $result['mergeDirection']);
    }

    public function test_root_prepend_merge_direction(): void
    {
        $result = $this->classify('items', Inertia::merge(['a'])->prepend());

        $this->assertSame('prepend', $result['mergeDirection']);
    }

    public function test_nested_append_merge_direction(): void
    {
        $result = $this->classify('items', Inertia::merge(['a'])->append('rows'));

        $this->assertSame('append', $result['mergeDirection']);
    }

    public function test_nested_prepend_merge_direction(): void
    {
        $result = $this->classify('items', Inertia::merge(['a'])->prepend('rows'));

        $this->assertSame('prepend', $result['mergeDirection']);
    }

    public function test_mixed_nested_direction_reads_as_append(): void
    {
        $result = $this->classify('items', Inertia::merge(['a'])->append('rows')->prepend('cols'));

        $this->assertSame('append', $result['mergeDirection']);
    }

    public function test_non_mergeable_prop_has_no_merge_direction(): void
    {
        $result = $this->classify('bio', Inertia::optional(fn () => 'value'));

        $this->assertNull($result['mergeDirection']);
    }

    public function test_scroll_prop_default_direction_is_append(): void
    {
        $result = $this->classify('feed', Inertia::scroll(['a']));

        $this->assertSame('append', $result['mergeDirection']);
    }

    public function test_scroll_prop_prepend_intent_direction(): void
    {
        $result = $this->classify('feed', Inertia::scroll(['a'])->prepend('data'));

        $this->assertSame('prepend', $result['mergeDirection']);
    }

    public function test_deep_merge_flag_for_deep_merge(): void
    {
        $result = $this->classify('items', Inertia::merge(['a'])->deepMerge());

        $this->assertTrue($result['deepMerge']);
        $this->assertSame('append', $result['mergeDirection']);
    }

    public function test_deep_merge_flag_survives_prepend(): void
    {
        $result = $this->classify('items', Inertia::merge(['a'])->deepMerge()->prepend());

        $this->assertTrue($result['deepMerge']);
        $this->assertSame('prepend', $result['mergeDirection']);
    }

    public function test_match_on_implies_deep_merge(): void
    {
        $result = $this->classify('items', Inertia::merge([['id' => 1]])->matchOn('id'));

        $this->assertTrue($result['deepMerge']);
        $this->assertSame('append', $result['mergeDirection']);
    }

    public function test_plain_merge_is_not_a_deep_merge(): void
    {
        $result = $this->classify('items', Inertia::merge(['a']));

        $this->assertFalse($result['deepMerge']);
    }

    public function test_non_mergeable_prop_is_not_a_deep_merge(): void
    {
        $result = $this->classify('name', 'Alice');

        $this->assertFalse($result['deepMerge']);
    }

    public function test_reset_flag_is_set_when_the_path_is_in_the_reset_header(): void
    {
        $result = $this->classify('items', 'value', [Header::RESET => 'items']);

        $this->assertTrue($result['reset']);
    }

    public function test_reset_flag_is_false_when_the_path_is_absent_from_the_reset_header(): void
    {
        $result = $this->classify('items', 'value', [Header::RESET => 'other']);

        $this->assertFalse($result['reset']);
    }

    public function test_reset_header_is_parsed_as_a_comma_list(): void
    {
        $present = $this->classify('items', 'value', [Header::RESET => 'first,items,last']);
        $this->assertTrue($present['reset']);

        $absent = $this->classify('middle', 'value', [Header::RESET => 'first,items,last']);
        $this->assertFalse($absent['reset']);
    }

    public function test_empty_reset_header_never_marks_a_prop_reset(): void
    {
        $result = $this->classify('items', 'value', [Header::RESET => '']);

        $this->assertFalse($result['reset']);
    }

    public function test_classifier_reads_the_expected_header_names(): void
    {
        $this->assertSame('X-Inertia-Devtools-Deferred', DevToolsHeader::DEVTOOLS_DEFERRED);
        $this->assertSame('X-Inertia-Reset', Header::RESET);
    }

    public function test_prop_type_wire_values_match_the_extension_contract(): void
    {
        $this->assertSame('always', PropType::Always->value);
        $this->assertSame('defer', PropType::Defer->value);
        $this->assertSame('optional', PropType::Optional->value);
        $this->assertSame('merge', PropType::Merge->value);
        $this->assertSame('scroll', PropType::Scroll->value);
        $this->assertSame('once', PropType::Once->value);
    }
}
