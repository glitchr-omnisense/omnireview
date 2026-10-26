---
title: Installation
order: 10
---

# Installation

```sh
composer require omnisense/omnireview omnireview/google
composer require omnireview/tripadvisor        # each platform the application shows
composer require omnireview/trustpilot
```

## Requirements

- PHP 8.2 or later.
- `omnisense/omnireview` requires `symfony/http-client-contracts` (^3.0) and nothing else: no
  framework, no HTTP client of its own. It replaces `omniscient/omnireview` and
  `glitchr/omnireview`: a `composer.json` that still requires an old name resolves to this package.
- Each provider requires `omnisense/omnireview` and `symfony/http-client` (6.4, 7 or 8). Its
  factory takes an `HttpClientInterface` (the application's, a `MockHttpClient` in a test) and
  makes its own with `HttpClient::create()` when given none.
- The Symfony bundle needs `symfony/config`, `symfony/dependency-injection` and
  `symfony/http-kernel`; the Twig functions `twig/twig`, and `symfony/cache-contracts` to keep what
  they show. None is required by the package: the application has them ([Symfony and
  Twig](symfony.md)).

## A first script

A whole script, in plain PHP - no framework, no container. So that it runs without keys, the HTTP
client answers from the providers' recorded answers (the harness's `config/recorded.php`, a
`MockHttpClient` callback serving each provider's `Tests/Fixtures`); with keys, give the factories
`Symfony\Component\HttpClient\HttpClient::create()` instead.

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Omnireview\Exception\NotSupportedException;
use Omnireview\Google\GoogleGatewayFactory;
use Omnireview\Model\Place;
use Omnireview\Registry;
use Omnireview\Trustpilot\TrustpilotGatewayFactory;
use Symfony\Component\HttpClient\MockHttpClient;

// The platforms' recorded answers, as the harness serves them: nothing leaves the machine.
$http = new MockHttpClient(require __DIR__.'/vendor/omnisense/omnireview/docker/harness/config/recorded.php');

$registry = new Registry([new GoogleGatewayFactory($http), new TrustpilotGatewayFactory($http)], [
    'google' => ['factory' => 'google', 'options' => ['api_key' => getenv('GOOGLE_PLACES_KEY') ?: 'example', 'language' => 'fr']],
    'trustpilot' => ['factory' => 'trustpilot', 'options' => ['api_key' => getenv('TRUSTPILOT_API_KEY') ?: 'example']],
]);

$place = new Place('ChIJLU7jZClu5kcR4PcOOO6p3I0');           // a Google place ID: the one thing Google lets you keep
$rating = $registry->rating('google')->rating($place);
printf("%s/%d from %d reviews - %s\n", $rating->value, $rating->scale, $rating->count, $rating->url);
foreach ($registry->reviews('google')->reviews($place, 3) as $review) {
    printf("  %s by %s on %s%s: %s\n", $review->rating, $review->author, $review->publishedAt?->format('Y-m-d'), $review->translated ? ' (translated by Google)' : '', $review->text);
}
echo 'to write one: ', $registry->writeUrl('google')->writeUrl($place), "\n";

$terms = $registry->get('google')->terms();
printf("kept: %s; credited to %s; read at %s\n", 0 === $terms->cacheFor ? 'never' : $terms->cacheFor.' s', $terms->attribution, $terms->source);

$unit = $registry->get('trustpilot')->find('www.maison-erable.example');   // TrustpilotGateway's own: once, then keep the ID
echo 'trustpilot business unit: ', $unit->id, ' (', $unit->name, ")\n";
echo 'trustpilot gives: ', implode(', ', $registry->get('trustpilot')->capabilities()->gives()), "\n";

try {
    $registry->invitations('google');                          // Google has no invitation API
} catch (NotSupportedException $e) {
    echo $e->getMessage(), "\n";
}
```

```
$ php first.php
4.6/5 from 128 reviews - https://maps.google.com/?cid=10223305813012340704
  5 by Camille R. on 2026-09-23: Un accueil chaleureux, une cuisine remarquable.
  4 by Jordan K. on 2026-09-01 (translated by Google): Très bon, un peu d'attente.
to write one: https://search.google.com/local/writereview?placeid=ChIJLU7jZClu5kcR4PcOOO6p3I0
kept: never; credited to Google Maps; read at https://developers.google.com/maps/documentation/places/web-service/policies
trustpilot business unit: 46d6a890000064000500e0c3 (Maison Érable)
trustpilot gives: rating, reviews, write-url
The "google" gateway (Google) does not give inviting a customer to review; it gives: rating, reviews, write-url.
```

(run on 2026-10-26 with the harness's image, PHP 8.4, the workspace's packages mounted read-only
and no network)

The pieces:

- a **factory** per provider package, which takes the application's HTTP client;
- the **registry**, built from the factories and each gateway's options by name; its `rating()`,
  `reviews()`, `replies()`, `invitations()` and `writeUrl()` give the gateway typed by what is
  asked of it, or throw `NotSupportedException` when it does not implement that interface;
- the **gateway** it gives, built once, the first time it is asked for
  ([Concepts and models](models.md)).

`find()` is not part of the contract: it is `TrustpilotGateway`'s own method (a business unit
looked up by its domain), reached here through `get()`.

No class of a framework is loaded on the way: `Tests/BareTest.php` checks it in a process of its
own, and so does `docker compose run --rm omnireview bare` ([harness](harness.md)).

## What the application does around a gateway

1. **Find** the place once - a Google place ID, a Tripadvisor location ID, a Trustpilot business
   unit (`find()` by domain) - and keep that ID: every platform allows it.
2. **Show** the rating and a few reviews with their origin, date and link - the Twig widget does,
   or the application's own template, keeping to `terms()`. Keep them no longer than
   `terms()->cacheFor`.
3. **Link** to the platform to read them all and to write one (`writeUrl()`).
4. As the establishment, with its own access: **reply** (`ReplyInterface`), **invite** a customer
   after a purchase (`InviteInterface`) - where the platform allows it.

## In a framework

- Symfony: `Omnireview\Bridge\Symfony\OmnireviewBundle`, in this package, builds the registry from
  `config/packages/omnireview.yaml` and registers the Twig functions ([Symfony and Twig](symfony.md)).
- Laravel: no bridge; a service provider of the application builds the registry
  ([Laravel](laravel.md)).
