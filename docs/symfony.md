---
title: Symfony and Twig
order: 4
---

# Symfony and Twig

Omnireview runs without a framework ([installation](installation.md)); in a Symfony application its
bundle does the wiring. Its components - `symfony/config`, `symfony/dependency-injection`,
`symfony/http-kernel`, and `twig/twig`, `symfony/cache-contracts` for the Twig functions - are not
required by `omniscient/omnireview`: the application has them, and nothing of them is loaded outside
Symfony.

Register `Omnireview\Bridge\Symfony\OmnireviewBundle` (no Flex recipe):

```php
// config/bundles.php
return [
    // ...
    Omnireview\Bridge\Symfony\OmnireviewBundle::class => ['all' => true],
];
```

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

`omnireview.gateways.<name>: {factory, options}`: the factories of the platform packages installed
are registered (the application's `http_client` given to them), each gateway is a service
`omnireview.gateway.<name>`, injectable by its name under each interface:

```php
public function __construct(private RatingInterface $google, private ReplyInterface $trustpilot) {}
```

Nothing is built, nor checked, when the container compiles: a key missing shows on first use.

## Twig

| Function | |
|---|---|
| `omnireview_widget(gateway, place, limit = 3, language = null)` | `@Omnireview/reviews.html.twig`: the rating, the reviews, the link to write one |
| `omnireview_rating(gateway, place)` | a `Rating`, or null when the platform does not answer |
| `omnireview_reviews(gateway, place, limit = 5, language = null)` | `list<Review>`, empty when it does not answer |
| `omnireview_write_url(gateway, place)` | |
| `omnireview_terms(gateway)` | the platform's `Terms` |

`place` is the platform's ID, or a `Place`.

The widget shows each rating with the platform's name linked to its page, each review with its
author (linked, photo when given), its date (`<time datetime>`), its text within quotation marks
and escaped, the establishment's reply, and a link to the review on the platform. There is **no
JSON-LD**: reviews collected by a third party are not marked up as the site's own
`AggregateRating`. Override `templates/bundles/Omnireview/reviews.html.twig` to dress it; keep
what `terms` requires.

When `terms.noIndex` is set (Tripadvisor), the widget carries `data-nosnippet`, which only keeps
the widget out of snippets: **the page itself** must carry
`<meta name="robots" content="noindex">` - the application's layout decides that, test
`omnireview_terms('tripadvisor').noIndex`.

What the functions fetch is kept in the cache `pool` for `min(ttl, terms.cacheFor)` seconds: not at
all for Google (cacheFor 0) nor Tripadvisor (null: its Caching Policy is not public), a day at most
for Trustpilot. Without a pool, nothing is kept.
