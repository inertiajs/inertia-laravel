<?php

namespace Inertia\Testing;

use Illuminate\Support\Arr;
use PHPUnit\Framework\Assert as PHPUnit;
use Illuminate\Foundation\Testing\TestResponse as BaseResponse;

class TestResponse extends BaseResponse
{
	public function assertHasProp($prop)
	{
		PHPUnit::assertTrue(Arr::has($this->props(), $prop));

		return $this;
	}

	public function assertPropValue($prop, $value)
	{
		$this->assertHasProp($prop);

		if (is_callable($value)) {
			$value($this->props($prop));
		} else {
			PHPUnit::assertEquals($this->props($prop), $value);
		}

		return $this;
	}

	public function assertPropCount($prop, $count)
	{
		$this->assertHasProp($prop);

		PHPUnit::assertCount($count, $this->props($prop));

		return $this;
	}

	public function assertComponent($component)
	{
		PHPUnit::assertEquals($component, $this->original['page']['component']);

		return $this;
	}

	protected function props($key = null)
	{
		$props = json_decode(json_encode($this->original['page']['props']), JSON_OBJECT_AS_ARRAY);

		if ($key) {
			return Arr::get($props, $key);
		}

		return $props;
	}
}