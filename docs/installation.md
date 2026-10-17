---
title: Installation
order: 1
---

# Installation and a first rating

```sh
composer require omniscient/omnireview omnireview/google
composer require omnireview/tripadvisor
composer require omnireview/trustpilot
```

PHP 8.2 or later.

Omnireview needs no framework. The core requires PHP and `symfony/http-client-contracts`; each
platform package requires `symfony/http-client` (a library) and takes the application's client
when it is given one. It runs the same in plain PHP, in a worker, in Laravel or Slim, and in
Symfony, where a bundle does the wiring ([Symfony and Twig](symfony.md)).

## Plain PHP

```php
<?php // reviews.php

require __DIR__.'/vendor/autoload.php';

use Omnireview\Google\GoogleGatewayFactory;
use Omnireview\Model\Place;
use Omnireview\Registry;
use Omnireview\Trustpilot\TrustpilotGatewayFactory;

$registry = new Registry([new GoogleGatewayFactory(), new TrustpilotGatewayFactory()], [
    'google' => ['factory' => 'google', 'options' => ['api_key' => getenv('GOOGLE_PLACES_KEY'), 'language' => 'fr']],
    'trustpilot' => ['factory' => 'trustpilot', 'options' => ['api_key' => getenv('TRUSTPILOT_API_KEY')]],
]);

$place = new Place(getenv('GOOGLE_PLACE_ID'));               // a place ID: the one thing Google lets you keep
$rating = $registry->rating('google')->rating($place);
printf("%s/%d from %d reviews - %s\n", $rating->value, $rating->scale, $rating->count, $rating->url);
foreach ($registry->reviews('google')->reviews($place, 3) as $review) {
    printf("  %s by %s on %s: %s\n", $review->rating, $review->author, $review->publishedAt?->format('Y-m-d'), $review->url);
}
echo 'to write one: ', $registry->writeUrl('google')->writeUrl($place), "\n";

$terms = $registry->get('google')->terms();
printf("kept: %s; credited to %s; read at %s\n", 0 === $terms->cacheFor ? 'never' : $terms->cacheFor.' s', $terms->attribution, $terms->source);

$unit = $registry->get('trustpilot')->find('www.maison-erable.example');   // once, then keep its ID
```

The same calls through the harness's bare script, the platforms answering from their packages'
recorded answers ([harness](harness.md)):

```
google      4.6/5 from 128 reviews, https://maps.google.com/?cid=10223305813012340704
              5 by Camille R. on 2026-09-23, https://www.google.com/maps/reviews/data=1
              to write one: https://search.google.com/local/writereview?placeid=ChIJLU7jZClu5kcR4PcOOO6p3I0
```

That is all there is to it:

- a **factory** per platform package, which takes the application's HTTP client when given one;
- the **registry**, built by hand from the factories and the gateways' options, by name; its
  `rating()`, `reviews()`, `replies()`, `invitations()` and `writeUrl()` give a gateway typed by
  what is asked of it, or throw `NotSupportedException`;
- the **gateways** it gives ([gateways](gateways.md)).

No class of a framework is loaded on the way - a test of this package checks it in a process of
its own (`Tests/BareTest.php`), and so does `docker compose run --rm omnireview bare`.

## What the application does around a gateway

1. **Find** the place once - a Google place ID, a Tripadvisor location ID, a Trustpilot business
   unit (`find()` by domain) - and keep that ID: every platform allows it.
2. **Show** the rating and a few reviews with their origin, date and link - the Twig widget does,
   or your own template, keeping to `terms()`. Keep them no longer than `terms()->cacheFor`.
3. **Link** to the platform to read them all and to write one (`writeUrl()`).
4. As the establishment, with its own access: **reply** (`ReplyInterface`), **invite** a
   customer after a purchase (`InviteInterface`) - where the platform allows it.

What it does not do: no review is collected, stored nor rewritten here; no JSON-LD
`AggregateRating` is built from them; nothing picks the happy customers to invite - invite them
all alike (Google and Trustpilot both forbid soliciting only positive reviews).

## Errors

| Exception | When |
|---|---|
| `NotSupportedException` | the gateway does not give that, or not as configured (a reply without the owner's access): `capabilities()` says so beforehand |
| `InvalidConfigException` | a gateway not configured, a factory not installed, an option missing |
| `InvalidKeyException` | the platform refused the application's key |
| `UnreachableException` | the platform did not answer, or asked to slow down (429); the Twig functions then show nothing |
| `ProviderException` | the platform refused the request (the two above extend it) |

All implement `Omnireview\Exception\OmnireviewException`.
