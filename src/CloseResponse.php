<?php

namespace Inertia;

use Closure;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Support\Header;
use Inertia\Support\SessionKey;
use Symfony\Component\HttpFoundation\Response;

class CloseResponse implements Responsable
{
    use ResolvesUrl;

    /**
     * Create a new Inertia close response instance.
     */
    public function __construct(
        protected string $version = '',
        protected ?Closure $urlResolver = null,
    ) {}

    /**
     * Create an HTTP response that represents the object.
     *
     * @param  Request  $request
     * @return Response
     */
    public function toResponse($request)
    {
        // A close abandons any pending interstitial capture, so the mark must not outlive it.
        session()->pull(SessionKey::INTERSTITIAL);

        // The flash is left in the session: it carries no `flash` key, so the client's follow-up
        // refresh of the layer beneath is what consumes it.
        $page = [
            'component' => '',
            'props' => (object) [],
            'url' => $this->getUrl($request),
            'version' => $this->version,
            'close' => true,
        ];

        if ($request->header(Header::INERTIA)) {
            return new JsonResponse($page, 200, [Header::INERTIA => 'true']);
        }

        return Redirect::back();
    }
}
