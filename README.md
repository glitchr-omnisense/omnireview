# glitchr/omnireview

One contract for **what customers say of an establishment elsewhere** - its rating, its reviews,
replying as the establishment, inviting a customer to leave one, the link to write one - whoever
hosts them: Google, Tripadvisor, Trustpilot.

```php
$google = $registry->rating('google');                         // a gateway that gives a rating
$rating = $google->rating(new Place('ChIJLU7jZClu5kcR4PcOOO6p3I0'));
$rating->value; $rating->count; $rating->url;                  // 4.6, 128, the place on Google Maps

foreach ($registry->reviews('google')->reviews($place, 3) as $review) {
    $review->author; $review->publishedAt; $review->url;      // always shown with them
}
$registry->get('google')->terms();                             // how long it may be kept, how it must be credited
```

This package holds the contract (`GatewayInterface` and the capabilities it may give:
`RatingInterface`, `ReviewsInterface`, `ReplyInterface`, `InviteInterface`, `WriteUrlInterface`;
`GatewayFactory`, `Registry`), the models (`Place`, `Rating`, `Review`, `Reply`, `Invitation`,
`Terms`, `Capabilities`), a bridge for Symfony and one for Twig. It needs no framework: PHP and
`symfony/http-client-contracts`. Each platform is a package of its own:

| Package | Gives | Kept | Reviews per call |
|---|---|---|---|
| [`omnireview/google`](https://github.com/glitchr-studio/omnireview-google) | rating, reviews, write-url; reply with the owner's access | never (the place ID only) | 5 (Places), 50 (Business Profile, the owner) |
| [`omnireview/tripadvisor`](https://github.com/glitchr-studio/omnireview-tripadvisor) | rating, reviews, write-url | per Tripadvisor's Caching Policy, pages not indexed | 5 |
| [`omnireview/trustpilot`](https://github.com/glitchr-studio/omnireview-trustpilot) | rating, reviews, write-url; reply and invite with a business user | 24 hours | 100 |

## Shown as theirs, never as the site's own

Each gateway declares its platform's **terms** (`Terms`): how long what it gives may be kept, how
many reviews one call gives, what must be shown with it (the platform's name or logo, each
author, a link back), whether pages that show it must stay out of search engines - read in each
platform's documentation, with the address they were read at (`Terms::$source`).

The Twig bridge keeps to them: a rating and its reviews are always shown with their **origin**,
their **date** and their **link**, kept in the cache no longer than the platform allows - and
**never as JSON-LD**: reviews collected by a third party are not the site's to mark up as its own
`AggregateRating` (see Google's review snippet guidelines).

## Documentation

- [Installation and a first rating](docs/installation.md)
- [Models](docs/models.md)
- [Gateways: the contract, what each gives, writing one](docs/gateways.md)
- [Symfony and Twig](docs/symfony.md)
- [The Docker harness: bare PHP, the console](docs/harness.md)

## Plain PHP

```sh
composer require glitchr/omnireview omnireview/google
```

```php
$registry = new Registry([new GoogleGatewayFactory()], [
    'google' => ['factory' => 'google', 'options' => ['api_key' => getenv('GOOGLE_PLACES_KEY'), 'language' => 'fr']],
]);
```

No bundle, no container: a factory per platform package, the registry built by hand.

## Symfony

`Omnireview\Bridge\Symfony\OmnireviewBundle` does that wiring, and registers the Twig functions
([docs/symfony.md](docs/symfony.md)); its components are not required by this package.

```yaml
omnireview:
    gateways:
        google: { factory: google, options: { api_key: '%env(GOOGLE_PLACES_KEY)%', language: fr } }
```

```twig
{{ omnireview_widget('google', place_id) }}
```

## Docker

```sh
cd docker && cp .env.dist .env
docker compose run --rm omnireview gateways
docker compose run --rm omnireview bare        # plain PHP: no bundle, no container, and what PHP loaded
docker compose run --rm omnireview test
```

License: MIT since 2026-10-09; earlier versions remain published under LGPL-3.0-or-later.
