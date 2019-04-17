# Inertia.js Laravel Adapter

> **Note:** This project is in the very early stages of development and IS NOT yet intended for public consumption. If you submit an issue, I do not guarantee a response. Please do not submit pull requests without first consulting me on Twitter ([@reinink](https://twitter.com/reinink)).

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

The `@inertia` directive is simply a helper for creating our base `div`. It includes two data attributes: `component` (the page component name) and `props` (the page data). Here's what it looks like:

~~~blade
<div id="app" data-component="{{ $component }}" data-props="{{ json_encode((object) $props) }}"></div>
~~~

If you'd like to use a different root view, you can change it using `Inertia::setRootView()`:

~~~php
Inertia\Inertia::setRootView('name');
~~~

## Setup JavaScript adapter

Next, you'll need to setup an Inertia JavaScript adapter, such as [inertia-vue](https://github.com/inertiajs/inertia-vue). Be sure to follow the getting started instructions for the the adapter you choose.

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

## Sharing data

To share data with all your components, use `Inertia::share($data)`. This can be done both synchronously and lazily:

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

Next, add the `CheckInertiaVersion` middleware to your web middleware group, found in the `/app/Http/Kernel.php`:

~~~php
protected $middlewareGroups = [
    'web' => [
        \Inertia\CheckInertiaVersion::class,
        // ...
    ]
];
~~~

Finally, make sure you have [versioning](https://laravel.com/docs/mix#versioning-and-cache-busting) setup in your `webpack.mix.js` to enable asset cache busting.
