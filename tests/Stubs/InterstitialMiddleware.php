<?php

namespace Inertia\Tests\Stubs;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A middleware that answers with a redirect carrying the interstitial mark: any middleware
 * returning a redirect can chain the macro.
 */
class InterstitialMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        return redirect('/users')->interstitial(); /** @phpstan-ignore method.notFound */
    }
}
