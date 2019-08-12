<?php

namespace Inertia\Testing;

use Inertia\Testing\TestResponse;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
	/**
	 * Create the test response instance from the given response.
	 *
	 * @param  \Illuminate\Http\Response $response
	 *
	 * @return \Illuminate\Foundation\Testing\TestResponse
	 */
	protected function createTestResponse($response)
	{
	    return TestResponse::fromBaseResponse($response);
	}
}