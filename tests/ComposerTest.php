<?php

namespace Inertia\Tests;

use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Inertia\ComposerBag;
use Inertia\Inertia;
use Inertia\ResponseFactory;
use Inertia\Tests\Stubs\ExampleMiddleware;

class ComposerTest extends TestCase
{
    public function test_can_use_class_based_composers_for_a_component()
    {
        Inertia::composer('User/Profile', UserComposer::class);

        $this->assertEquals(
            [UserComposer::class],
            app(ComposerBag::class)->get('User/Profile')
        );

        Route::middleware([StartSession::class, ExampleMiddleware::class])->get('/', function () {
            return Inertia::render('User/Profile', ['user' => 'John Doe']);
        });

        $response = $this->withoutExceptionHandling()->get('/', ['X-Inertia' => 'true']);

        $response->assertSuccessful();
        $response->assertJson([
            'component' => 'User/Profile',
            'props' => array_merge(['user' => 'John Doe'], ['list' => UserComposer::$data]),
        ]);
    }

    public function test_can_use_closure_based_composer_for_a_component()
    {
        $post = [
            'title' => 'Composer from callback',
            'description' => 'This is just a test. Please disregard.',
        ];

        Inertia::composer('User/Profile', $callback = function ($inertia) use ($post) {
            $inertia->with(['post' => $post]);
        });

        $this->assertEquals(
            [$callback],
            app(ComposerBag::class)->get('User/Profile')
        );

        Route::middleware([StartSession::class, ExampleMiddleware::class])->get('/', function () {
            return Inertia::render('User/Profile', ['user' => 'John Doe']);
        });

        $response = $this->withoutExceptionHandling()->get('/', ['X-Inertia' => 'true']);

        $response->assertSuccessful();
        $response->assertJson([
            'component' => 'User/Profile',
            'props' => array_merge(['user' => 'John Doe'], ['post' => $post]),
        ]);
    }

    public function test_can_use_multiple_composer_for_a_component()
    {
        $post = [
            'title' => 'Composer from callback',
            'description' => 'This is just a test. Please disregard.',
        ];

        Inertia::composer('User/Profile', UserComposer::class);
        Inertia::composer('User/Profile', $callback = function (ResponseFactory $inertia) use ($post) {
            $inertia->with(['post' => $post]);
        });

        $this->assertEquals(
            [UserComposer::class, $callback],
            app(ComposerBag::class)->get('User/Profile')
        );

        Route::middleware([StartSession::class, ExampleMiddleware::class])->get('/', function () {
            return Inertia::render('User/Profile', ['user' => 'John Doe']);
        });

        $response = $this->withoutExceptionHandling()->get('/', ['X-Inertia' => 'true']);

        $response->assertSuccessful();
        $response->assertJson([
            'component' => 'User/Profile',
            'props' => array_merge(
                ['user' => 'John Doe'],
                ['list' => UserComposer::$data],
                ['post' => $post]
            ),
        ]);
    }
}

class UserComposer
{
    public static $data = ['foo' => 'bar', 'baz' => 'buzz'];

    public function compose(ResponseFactory $inertia)
    {
        $inertia->with('list', static::$data);
    }
}
