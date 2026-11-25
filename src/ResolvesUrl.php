<?php

namespace Inertia;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;

trait ResolvesUrl
{
    /**
     * Get the URL from the request while preserving the trailing slash.
     */
    protected function getUrl(Request $request): string
    {
        $urlResolver = $this->urlResolver ?? function (Request $request) {
            $url = Str::start(Str::after($request->fullUrl(), $request->getSchemeAndHttpHost()), '/');

            $rawUri = Str::before($request->getRequestUri(), '?');

            return Str::endsWith($rawUri, '/') ? $this->finishUrlWithTrailingSlash($url) : $url;
        };

        return App::call($urlResolver, ['request' => $request]);
    }

    /**
     * Strip the app's own origin from a URL, leaving a foreign one as it is.
     */
    protected function toRelativeUrl(Request $request, string $url): string
    {
        $origin = $request->getSchemeAndHttpHost();

        return Str::startsWith($url, $origin) ? Str::start(Str::after($url, $origin), '/') : $url;
    }

    /**
     * Ensure the URL has a trailing slash before the query string.
     */
    protected function finishUrlWithTrailingSlash(string $url): string
    {
        // Make sure the relative URL ends with a trailing slash and re-append the query string if it exists.
        $urlWithoutQueryWithTrailingSlash = Str::finish(Str::before($url, '?'), '/');

        return str_contains($url, '?')
            ? $urlWithoutQueryWithTrailingSlash.'?'.Str::after($url, '?')
            : $urlWithoutQueryWithTrailingSlash;
    }
}
