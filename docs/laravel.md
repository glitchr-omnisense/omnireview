---
title: Laravel
order: 16
---

# Laravel

omnibridge/laravel, the bridge that hosts omnibase in Laravel, has no Omnireview provider. None is
needed to use the gateways: the registry is plain PHP, and a service provider of the application
builds it once, from a config file. The Twig functions and the widget are Twig's; a Blade view
reads the models directly.

```php
// config/omnireview.php
return [
    'gateways' => [
        'google' => ['factory' => 'google', 'options' => [
            'api_key' => env('GOOGLE_PLACES_KEY'),
            'language' => 'fr',
        ]],
        'trustpilot' => ['factory' => 'trustpilot', 'options' => [
            'api_key' => env('TRUSTPILOT_API_KEY'),
            'api_secret' => env('TRUSTPILOT_API_SECRET'),
            'business_user_id' => env('TRUSTPILOT_BUSINESS_USER_ID'),
        ]],
    ],
];
```

```php
// app/Providers/OmnireviewServiceProvider.php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Omnireview\Google\GoogleGatewayFactory;
use Omnireview\Registry;
use Omnireview\Trustpilot\TrustpilotGatewayFactory;
use Symfony\Component\HttpClient\HttpClient;

final class OmnireviewServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Registry::class, function (): Registry {
            $http = HttpClient::create();

            return new Registry([
                new GoogleGatewayFactory($http),
                new TrustpilotGatewayFactory($http),
            ], config('omnireview.gateways'));
        });
    }
}
```

Register the provider (`bootstrap/providers.php` on Laravel 11 and later), then inject the
registry:

```php
use Omnireview\Model\Place;
use Omnireview\Registry;

final class ReviewsController
{
    public function show(Registry $registry)
    {
        $place = new Place(config('services.google.place_id'));

        return view('reviews', [
            'rating' => $registry->rating('google')->rating($place),
            'reviews' => $registry->reviews('google')->reviews($place, 3),
            'terms' => $registry->get('google')->terms(),
        ]);
    }
}
```

The HTTP client is `symfony/http-client`'s (every provider requires it; Laravel's own HTTP client
is Guzzle's, which the factories do not take). List in the provider every factory the application
uses: a hand-built registry knows only the factories it is given. Keep what is shown no longer
than `Terms::$cacheFor` (`Cache::remember()` with that many seconds, and nothing when it is `0` or
`null`), and show each rating and review with its origin, author, date and link, as the Twig
widget does ([Symfony and Twig](symfony.md#what-the-widget-shows)).

This page was written from the registry's API; it was not run in a Laravel application.
