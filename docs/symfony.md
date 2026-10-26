---
title: Symfony and Twig
order: 15
---

# Symfony and Twig

Omnireview runs without a framework ([Installation](installation.md)); in a Symfony application its
bundle does the wiring. The bundle lives in this package (`Omnireview\Bridge\Symfony`), not in
omnibridge/symfony. Its components - `symfony/config`, `symfony/dependency-injection`,
`symfony/http-kernel` (6.4, 7 or 8), and `twig/twig` and `symfony/cache-contracts` for the Twig
functions - are not required by `omnisense/omnireview`: the application has them, and nothing of
them is loaded outside Symfony.

## Registering the bundle

There is no Flex recipe: add the bundle by hand.

```php
// config/bundles.php
return [
    // ...
    Omnireview\Bridge\Symfony\OmnireviewBundle::class => ['all' => true],
];
```

## Configuration

```yaml
# config/packages/omnireview.yaml
omnireview:
    gateways:
        google:      { factory: google, options: { api_key: '%env(GOOGLE_PLACES_KEY)%', language: fr } }
        tripadvisor: { factory: tripadvisor, options: { api_key: '%env(TRIPADVISOR_KEY)%', language: fr, referer: 'https://www.example.org/' } }
        trustpilot:  { factory: trustpilot, options: { api_key: '%env(TRUSTPILOT_API_KEY)%' } }
    cache:
        pool: cache.app      # where the Twig functions keep what they show
        ttl: 3600            # at most; never longer than the platform allows
```

The whole tree, from `OmnireviewBundle::configure()`:

| Key | Type | Default | |
|---|---|---|---|
| `omnireview.gateways` | map, keyed by the gateway's name | `{}` | the platforms the application uses |
| `omnireview.gateways.<name>.factory` | string, required, not empty | - | a factory's name: `google`, `tripadvisor`, `trustpilot`, or an application's own factory's `getName()` |
| `omnireview.gateways.<name>.options` | any value (a map) | `[]` | handed as they are to the factory's `create()` ([Configuration](configuration.md)) |
| `omnireview.cache.pool` | string or `null` | `cache.app` | the service id of a Symfony cache (`Symfony\Contracts\Cache\CacheInterface`) the Twig functions keep what they show in; `null`, or an id that does not exist: nothing is kept |
| `omnireview.cache.ttl` | integer, at least 0 | `3600` | seconds at most; the platform's `Terms::$cacheFor` caps it |

The options are not validated when the container compiles - the tree accepts any value - but
when the gateway is built: a required option missing or empty is an `InvalidConfigException`
naming it, the first time the gateway is asked for.

## Services

| Service id | Class | Autowiring | |
|---|---|---|---|
| `Omnireview\Registry` | `Registry` | `Registry $registry` | every configured gateway by name. Public. |
| `omnireview.gateway.<name>` | `GatewayInterface` | `GatewayInterface $<name>`, `RatingInterface $<name>`, `ReviewsInterface $<name>`, `ReplyInterface $<name>`, `InviteInterface $<name>`, `WriteUrlInterface $<name>` | one gateway, built by `Registry::get('<name>')` the first time it is injected |
| `Omnireview\Google\GoogleGatewayFactory`, `...\Tripadvisor\TripadvisorGatewayFactory`, `...\Trustpilot\TrustpilotGatewayFactory` | the provider's factory | by its class | registered when its package is installed (the class exists); autowired, its `$http` argument the application's `http_client` service, or `null` when there is none; tagged `omnireview.gateway_factory` |
| `Omnireview\Bridge\Twig\OmnireviewExtension` | the Twig extension | - | registered when `twig/twig` is installed; tagged `twig.extension`; given the registry, the cache pool and the ttl |

```php
use Omnireview\RatingInterface;
use Omnireview\ReplyInterface;

final class ReviewsController
{
    public function __construct(
        private readonly RatingInterface $google,          // the "google" gateway
        private readonly ReplyInterface $trustpilot,       // the "trustpilot" gateway
    ) {
    }
}
```

The argument's name selects the gateway (Symfony's named autowiring aliases,
`registerAliasForArgument()`). A gateway name that is not a valid PHP variable name is still
reachable by its service id or by the registry.

> [!NOTE]
> An alias is registered under each of the six interfaces for every gateway, whatever it
> implements: `ReplyInterface $tripadvisor` compiles, and fails with PHP's `TypeError` when the
> service is built (Tripadvisor's gateway implements no `ReplyInterface`). Ask the registry
> (`$registry->replies('tripadvisor')`) for a `NotSupportedException` that names what it gives.

Nothing is built, nor checked, when the container compiles: a key missing shows on first use. Keys
typed in a back office rather than set in the environment: `$registry->create('trustpilot',
['api_secret' => $stored])`, a gateway built afresh with those options over the configured ones and
not kept by the registry.

## An application's own platform

A class of the application that implements `Omnireview\GatewayFactoryInterface` (usually by
extending `Omnireview\GatewayFactory`) is autoconfigured: tagged `omnireview.gateway_factory`, it
joins the registry, and its `getName()` can be named as a `factory` ([Writing a
provider](providers.md)):

```yaml
omnireview:
    gateways:
        guestbook:
            factory: guestbook     # App\Reviews\GuestbookGatewayFactory::getName()
```

The bundle registers no command, event, route or controller.

## Twig

| Function | Returns | |
|---|---|---|
| `omnireview_widget(gateway, place, limit = 3, language = null)` | HTML (safe) | `@Omnireview/reviews.html.twig` rendered: the rating, the reviews, the link to write one - each only when `capabilities()` gives it |
| `omnireview_rating(gateway, place)` | `Rating` or `null` | `null` when the platform does not answer (`UnreachableException`) |
| `omnireview_reviews(gateway, place, limit = 5, language = null)` | `list<Review>` | empty when the platform does not answer |
| `omnireview_write_url(gateway, place)` | string | the address to write a review |
| `omnireview_terms(gateway)` | `Terms` | the platform's rules; nothing called |

`gateway` is the configured name; `place` the platform's identifier (a string, made a `Place`) or
a `Place` (needed for Google's owner side, which reads `Place::$owner`).

```twig
{{ omnireview_widget('google', restaurant.googlePlaceId) }}

{% set rating = omnireview_rating('trustpilot', '46d6a890000064000500e0c3') %}
{% if rating %}{{ rating.value }}/{{ rating.scale }} ({{ rating.count }}){% endif %}

<a href="{{ omnireview_write_url('google', restaurant.googlePlaceId) }}">Leave a review on Google</a>
```

The widget for a Trustpilot business unit, one review, on the recorded answers:

```
<section class="omnireview omnireview--trustpilot">
    <header class="omnireview__origin">                        <strong class="omnireview__score">4.4</strong><span class="omnireview__scale">/5</span>
            <span class="omnireview__count">469</span>        <a class="omnireview__platform" href="https://fr.trustpilot.com/review/www.maison-erable.example" rel="noopener" target="_blank">Trustpilot</a>    </header>
            <article class="omnireview__review">
            <p class="omnireview__author">
                                Camille R.                <time datetime="2026-09-30T12:00:00+00:00">30/09/2026</time>                <span class="omnireview__stars">5/5</span>            </p>
            <p class="omnireview__title">Parfait</p>            <blockquote class="omnireview__text">“Livraison rapide, produit conforme.”</blockquote>            <p class="omnireview__reply">Merci Camille !</p>            <a class="omnireview__link" href="https://www.trustpilot.com/reviews/507f191e810c19729de860ea" rel="noopener" target="_blank">Trustpilot</a>        </article>
        <a class="omnireview__write" href="https://fr.trustpilot.com/evaluate/www.maison-erable.example" rel="noopener" target="_blank">Trustpilot</a></section>
```

(run on 2026-10-26 in the harness's image: `OmnireviewExtension` on a Twig `Environment` with
`@Omnireview` pointing at `Bridge/Twig/templates`, an `ArrayAdapter` cache, the providers'
recorded answers)

### What the widget shows

- A `<section class="omnireview omnireview--<factory name>">`, with `data-nosnippet` when
  `terms.noIndex` is set (Tripadvisor).
- The rating: the platform's own picture of the score when it gives one (`Rating::$image`,
  Tripadvisor's bubbles, from Tripadvisor's address), the score with one decimal, the scale, the
  count; then `terms.attribution` linked to `Rating::$url` (plain text without a rating or a URL).
- Each review: the author's photo (32 x 32) and name (linked to `authorUrl`, `rel="noopener
  nofollow"`), its date (`<time datetime>` in ISO 8601, shown `d/m/Y`), the platform's picture of
  its score or `rating/scale`, its title, its text escaped within quotation marks, the
  establishment's reply, and `terms.attribution` linked to the review on the platform.
- The link to write one, labelled with the gateway's title.

There is **no JSON-LD**: reviews collected by a third party are not marked up as the site's own
`AggregateRating` (`Tests/Bridge/OmnireviewExtensionTest.php` checks it).

`data-nosnippet` keeps the widget out of search snippets only: for Tripadvisor, **the page
itself** must carry `<meta name="robots" content="noindex">` - the application's layout decides
that, testing `omnireview_terms('tripadvisor').noIndex`.

### Dressing it

The bundle adds `Bridge/Twig/templates` to Twig's `@Omnireview` namespace (a `twig.paths` entry
prepended to the application's configuration, when TwigBundle is there).

> [!WARNING]
> Known defect: Symfony's bundle override directory, `templates/bundles/OmnireviewBundle/`, is
> added to `@Omnireview` after the bundle's own path (TwigBundle registers the configured `paths`
> first, the bundles' override directories next), so `@Omnireview/reviews.html.twig` keeps
> resolving to the bundle's template, where an override placed there is expected to win. Read
> from TwigBundle's `TwigExtension::load()`, not run in an application.

Until then, an application that dresses the widget writes its own template from the other four
functions - keeping what `omnireview_terms(gateway)` requires: `attribution` shown and linked,
each author when `authors`, a link back when `link`:

```twig
{% set terms = omnireview_terms('google') %}
{% set rating = omnireview_rating('google', place_id) %}
<aside class="reviews">
    {% if rating %}<a href="{{ rating.url }}">{{ rating.value|number_format(1) }}/{{ rating.scale }} - {{ rating.count }} reviews on {{ terms.attribution }}</a>{% endif %}
    {% for review in omnireview_reviews('google', place_id, 3) %}
        <blockquote>“{{ review.text }}” - {{ review.author }}, <time datetime="{{ review.publishedAt|date('c') }}">{{ review.publishedAt|date('d/m/Y') }}</time></blockquote>
    {% endfor %}
</aside>
```

### Caching

`omnireview_rating()` and `omnireview_reviews()` keep what they fetch in the cache `pool` for
`min(ttl, Terms::$cacheFor)` seconds, under a key made of the gateway's name, the place, and for
reviews the limit and language: not at all for Google (`cacheFor` 0) nor Tripadvisor (`null`: its
Caching Policy is not public), at most `ttl` (3600 by default, a day at the very most) for
Trustpilot. Without a pool nothing is kept. `omnireview_write_url()` and `omnireview_terms()` are
not cached; `TripadvisorGateway` keeps its location details for its own lifetime, so the rating and
the write link of a Tripadvisor widget are one call.

### A platform that does not answer

`omnireview_rating()` gives `null` and `omnireview_reviews()` an empty list when the platform
raises `UnreachableException` (no connection, a timeout, a 5xx, a 429). Any other failure - a key
refused (`InvalidKeyException`), a place unknown (`ProviderException`), a gateway not configured -
reaches the template as an error.

> [!WARNING]
> Known defect: `omnireview_write_url()`, and so `omnireview_widget()`, are not guarded: on a
> platform whose write link is fetched (Tripadvisor's `/details`, Trustpilot's `/web-links`) an
> `UnreachableException` reaches the template, where the extension's docblock says a platform that
> does not answer shows nothing. Seen in the harness's image with Trustpilot answering 503:
> `omnireview_rating()` gave `null`, `omnireview_write_url()` raised
> `[trustpilot] HTTP 503: Service Unavailable`.
