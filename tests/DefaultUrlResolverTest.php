<?php

declare(strict_types=1);

namespace Inertia\Tests;

use Illuminate\Http\Request;
use Inertia\DefaultUrlResolver;
use Inertia\Response;

class DefaultUrlResolverTest extends TestCase
{
    public function test_it_resolves_the_url(): void
    {
        $request = Request::create('/my/url?with=some/query/params');

        $urlResolver = new DefaultUrlResolver();

        $this->assertSame('/my/url?with=some/query/params', $urlResolver->resolve($request));
    }

    public function test_it_resolves_the_url_with_(): void
    {
        Request::setTrustedProxies(['1.2.3.4'], Request::HEADER_X_FORWARDED_PREFIX);

        $request = Request::create('/user/123', 'GET');
        $request->server->set('REMOTE_ADDR', '1.2.3.4');
        $request->headers->set('X_FORWARDED_PREFIX', '/sub/directory');

        $urlResolver = new DefaultUrlResolver();

        $url = $urlResolver->resolve($request);

        $this->assertSame('/sub/directory/user/123', $url);
    }

    public function test_the_url_doesnt_double_up(): void
    {
        $request = Request::create('/subpath/product/123', 'GET', [], [], [], [
            'SCRIPT_FILENAME' => '/project/public/index.php',
            'SCRIPT_NAME' => '/subpath/index.php',
        ]);
        $request->headers->add(['X-Inertia' => 'true']);

        $urlResolver = new DefaultUrlResolver();

        $url = $urlResolver->resolve($request);

        $this->assertSame('/subpath/product/123', $url);
    }

    public function test_the_query_string_of_the_page_url_is_not_urlencoded(): void
    {
        $request = Request::create('/product/123?q=hello/world', 'GET');
        $request->headers->add(['X-Inertia' => 'true']);

        $urlResolver = new DefaultUrlResolver();

        $url = $urlResolver->resolve($request);

        $this->assertSame('/product/123?q=hello/world', $url);
    }
}
