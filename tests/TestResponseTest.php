<?php

use Illuminate\Http\Response;
use Inertia\Testing\TestResponse;
use PHPUnit\Framework\TestCase;

class TestResponseTest extends TestCase
{
    public function test_assert_has_prop()
    {
        $response = $this->makeMockResponse([
        	'prop' => 'value'
        ]);

        $response->assertHasProp('prop');
    }

    public function test_assert_prop_value()
    {
        $response = $this->makeMockResponse([
        	'prop' => 'value'
        ]);

        $response->assertPropValue('prop', 'value');
    }

    public function test_assert_prop_count()
    {
        $response = $this->makeMockResponse([
        	'prop' => [1, 2, 3],
        ]);

        $response->assertPropCount('prop', 3);
    }

    private function makeMockResponse($content)
    {
    	$baseResponse = tap(new Response, function ($response) use ($content) {
            $response->setContent([
            	'page' => [
            		'props' => $content
            	]
            ]);
        });

        return TestResponse::fromBaseResponse($baseResponse);
    }
}