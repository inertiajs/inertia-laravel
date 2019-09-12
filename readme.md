# Inertia.js Laravel Adapter

To use [Inertia.js](https://github.com/inertiajs/inertia) you need both a server-side adapter (like this one) as well as a client-side adapter, such as [inertia-vue](https://github.com/inertiajs/inertia-vue). Be sure to also follow the installation instructions for the client-side adapter you choose. This documentation will only cover the Laravel adapter setup.

## Installation

Install using Composer:

~~~sh
composer require inertiajs/inertia-laravel
~~~

## Setup root template

The first step to using Inertia is creating a root template. We recommend using `app.blade.php`. This template should include your assets, as well as the `@inertia` directive.

~~~blade
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <link href="{{ mix('/css/app.css') }}" rel="stylesheet">
    <script src="{{ mix('/js/app.js') }}" defer></script>
</head>
<body>

@inertia

</body>
</html>
~~~

The `@inertia` directive is simply a helper for creating our base `div`. It includes a `data-page` attribute which contains the inital page information. Here's what that looks like.

~~~blade
<div id="app" data-page="{{ json_encode($page) }}"></div>
~~~

If you'd like to use a different root view, you can change it using `Inertia::setRootView()`.

~~~php
Inertia\Inertia::setRootView('name');
~~~

## Making Inertia responses

To make an Inertia response, use `Inertia::render()`. This function takes two arguments, the component name, and the component data (props).

~~~php
use Inertia\Inertia;

class EventsController extends Controller
{
    public function show(Event $event)
    {
        return Inertia::render('Event', [
            'event' => $event->only('id', 'title', 'start_date', 'description'),
        ]);
    }
}
~~~

Alternatively, you can use the `with()` method to include component data (props):

~~~php
use Inertia\Inertia;

class EventsController extends Controller
{
    public function show(Event $event)
    {
        return Inertia::render('Event')
            ->with('event', $event->only('id', 'title', 'start_date', 'description'));
    }
}
~~~

## Following redirects

When making a non-GET Inertia request, via `<inertia-link>` or manually, be sure to still respond with a proper Inertia response. For example, if you're creating a new user, have your "store" endpoint return a redirect back to a standard GET endpoint, such as your user index page. Inertia will automatically follow this redirect and update the page accordingly. Here's a simplified example.

~~~php
class UsersController extends Controller
{
    public function index()
    {
        return Inertia::render('Users/Index', ['users' => User::all()]);
    }

    public function store()
    {
        User::create(
            Request::validate([
                'first_name' => ['required', 'max:50'],
                'last_name' => ['required', 'max:50'],
                'email' => ['required', 'max:50', 'email'],
            ])
        );

        return Redirect::route('users');
    }
}
~~~

Note, when redirecting after a `PUT`, `PATCH` or `DELETE` request you must use a `303` response code, otherwise the subsequent request will not be treated as a `GET` request. A `303` redirect is the same as a `302` except that the follow-up request is explicitly changed to a `GET` request.

## Sharing data

To share data with all your components, use `Inertia::share($key, $data)`. This can be done both synchronously and lazily.

~~~php
// Synchronously
Inertia::share('app.name', Config::get('app.name'));

// Lazily
Inertia::share('auth.user', function () {
    if (Auth::user()) {
        return [
            'id' => Auth::user()->id,
            'first_name' => Auth::user()->first_name,
            'last_name' => Auth::user()->last_name,
        ];
    }
});

// Multiple values
Inertia::share([
    // Synchronously
    'app' => [
        'name' => Config::get('app.name')
    ],
    // Lazily
    'auth' => function () {
        return [
            'user' => Auth::user() ? [
                'id' => Auth::user()->id,
                'first_name' => Auth::user()->first_name,
                'last_name' => Auth::user()->last_name,
            ] : null
        ];
    }
]);
~~~

You can also get shared data using the same method `Inertia::share($key)`. If the key is not found, `null` is returned.

## Accessing data in root template

There are situations where you may want to access your prop data in your root Blade template. For example, you may want to add a meta description tag, Twitter card meta tags, or Facebook Open Graph meta tags. These props are available via the `$page` variable.

~~~blade
<meta name="twitter:title" content="{{ $page['props']['event']->title }}">
~~~

Sometimes you may even want to provide data that will not be sent to your JavaScript component. You can do this using the `withViewData()` method.

~~~php
return Inertia::render('Event', ['event' => $event])->withViewData(['meta' => $event->meta]);
~~~

You can then access this variable like a regular Blade variable.

~~~blade
<meta name="description" content="{{ $meta }}">
~~~

## Asset versioning

One common challenge with single-page apps is refreshing site assets when they've been changed. Inertia makes this easy by optionally tracking the current version of your site assets. In the event that an asset changes, Inertia will automatically make a hard page visit instead of a normal ajax visit on the next request.

To enable automatic asset refreshing, first call the `Inertia::version($version)` method with your current asset version. We recommend putting this in a service provider.

~~~php
Inertia::version($version);
~~~

If you're using Laravel Mix, you can use the `mix-manifest.json` for this. Here's an example of that using lazy evaluation.

~~~php
Inertia::version(function () {
    return md5_file(public_path('mix-manifest.json'));
});
~~~

Finally, make sure you have [versioning](https://laravel.com/docs/mix#versioning-and-cache-busting) setup in your `webpack.mix.js` to enable asset cache busting.
