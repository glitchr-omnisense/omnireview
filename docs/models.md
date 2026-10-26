---
title: Concepts and models
order: 20
---

# Concepts and models

## The contract

A **gateway** is one platform, configured. It implements `Omnireview\GatewayInterface` and the
capability interfaces it gives; each capability interface extends `GatewayInterface`.

| Interface | Method | |
|---|---|---|
| `GatewayInterface` | `getName(): string` | the factory's name: `google`, `tripadvisor`, `trustpilot` |
| | `getTitle(): string` | the platform's name for people: `Google` |
| | `capabilities(): Capabilities` | what it gives, as configured |
| | `terms(): Terms` | how what it gives may be shown and kept |
| `RatingInterface` | `rating(Place $place): Rating` | the establishment's score, its count, its page; `@throws UnreachableException` when the platform did not answer, `ProviderException` when it does not know the place |
| `ReviewsInterface` | `reviews(Place $place, int $limit = 5, ?string $language = null): list<Review>` | reviews in the platform's order, at most `$limit` (the platform may give fewer: `Terms::$maxReviews`); `$language` where the platform filters or translates |
| `ReplyInterface` | `reply(Place $place, Review $review, string $text): Reply` | answering a review as the establishment: the owner's access needed |
| `InviteInterface` | `invite(Place $place, Invitation $invitation): ?string` | a customer invited by the platform's own e-mail; the platform's reference for the invitation, when it gives one |
| `WriteUrlInterface` | `writeUrl(Place $place): string` | the address where a customer writes a review |

A gateway may implement an interface and not give it as configured - Google's and Trustpilot's
gateways implement `ReplyInterface` whether or not the owner's access is set: `capabilities()`
then says `reply: false` and `reply()` throws `NotSupportedException`. A gateway keeps nothing
between requests beyond an OAuth token (and Tripadvisor's location details, for its own
lifetime): what to show, and for how long, is the application's, within the `Terms`.

A **factory** (`GatewayFactoryInterface`) builds a gateway from options:

| Method | |
|---|---|
| `getName(): string` | the name gateways are configured with |
| `create(array $options = []): GatewayInterface` | a gateway; `InvalidConfigException` when an option is missing |

`Omnireview\GatewayFactory` is the abstract base every provider extends ([Writing a
provider](providers.md)); `Omnireview\Registry` holds the gateways by name
([Configuration](configuration.md#the-registry)).

## Models

All are `final readonly` classes in `Omnireview\Model`, built with named arguments.

### Place

The establishment on one platform, as the application keeps it (every platform allows keeping its
identifier).

| Property | Type | Default | |
|---|---|---|---|
| `id` | string | required | the platform's identifier: a Google place ID (`ChIJ...`), a Tripadvisor location ID (`1234567`), a Trustpilot business unit ID |
| `name` | ?string | `null` | the establishment's name; Google uses it in the Maps search link of its owner's side |
| `owner` | ?string | `null` | a second identifier the owner's side needs: Google Business Profile's `accounts/<a>/locations/<l>` |

### Rating

| Property | Type | Default | |
|---|---|---|---|
| `platform` | string | required | the factory's name |
| `value` | float | required | the score, on the platform's scale |
| `count` | int | required | the reviews (or ratings) it rests on |
| `scale` | int | `5` | the scale's top (5 on every platform today) |
| `url` | ?string | `null` | the establishment's page on the platform: link it |
| `image` | ?string | `null` | the platform's own picture of the score (Tripadvisor's bubbles), shown from its address |
| `fetchedAt` | DateTimeImmutable | now | when it was read |

`outOfFive(): float` - the score on 5, rounded to a tenth (`8.6` on 10 is `4.3`).

### Review

| Property | Type | Default | |
|---|---|---|---|
| `platform` | string | required | the factory's name |
| `id` | string | required | the platform's identifier: what a reply needs |
| `rating` | float | required | on the platform's scale |
| `text` | ?string | `null` | as its author wrote it, or the platform's translation |
| `title` | ?string | `null` | Tripadvisor's and Trustpilot's |
| `language` | ?string | `null` | the text's language code |
| `author` | ?string | `null` | as the platform names them |
| `authorUrl` | ?string | `null` | their profile on the platform |
| `authorPhoto` | ?string | `null` | their picture's address |
| `publishedAt` | ?DateTimeImmutable | `null` | |
| `url` | ?string | `null` | the review on the platform, or the establishment's page when the platform gives no review an address of its own: link it |
| `reply` | ?Reply | `null` | the establishment's answer |
| `scale` | int | `5` | |
| `translated` | bool | `false` | the text is the platform's translation |
| `image` | ?string | `null` | the platform's picture of this review's score |

A review's text is the platform's: show it escaped, within quotation marks, unaltered - shortened
only with a link to the whole.

### Reply

| Property | Type | Default | |
|---|---|---|---|
| `text` | string | required | the establishment's answer |
| `at` | ?DateTimeImmutable | `null` | when it was published or last changed |

### Invitation

A customer to invite.

| Property | Type | Default | |
|---|---|---|---|
| `email` | string | required | |
| `name` | string | required | |
| `reference` | ?string | `null` | what they bought: an order number |
| `locale` | string | `fr-FR` | the invitation's language |
| `sendAt` | ?DateTimeImmutable | `null` | when the platform sends it; `null`: not sent to the platform, which then sends at its own default time |
| `redirectUrl` | ?string | `null` | where the customer lands after writing their review |

### Terms

A platform's rules for what it gives, declared by its package from the platform's documentation.

| Property | Type | Default | |
|---|---|---|---|
| `cacheFor` | ?int | `0` | seconds what it gives may be kept before asking again: `0` never, `null` "as the platform's caching policy allows" (not published, so treated as never) |
| `maxReviews` | ?int | `null` | the reviews one call gives at most |
| `attribution` | string | `''` | what must be shown with it: the platform's name or logo |
| `authors` | bool | `true` | each review shown with its author as the platform names them |
| `link` | bool | `true` | a link back to the platform shown with it |
| `noIndex` | bool | `false` | pages that show it are kept out of search engines |
| `rules` | list of strings | `[]` | the other rules, in words |
| `source` | ?string | `null` | where they are written |

`allowsCaching(int $seconds): bool` - whether it may be kept `$seconds`: `false` when `cacheFor`
is `null`, else `$seconds <= cacheFor`.

What each provider declares:

| | `cacheFor` | `maxReviews` | `attribution` | `noIndex` | `source` |
|---|---|---|---|---|---|
| Google | `0` | `5` | `Google Maps` | `false` | developers.google.com/maps/documentation/places/web-service/policies |
| Tripadvisor | `null` | `5` | `Tripadvisor` | `true` | tripadvisor-content-api.readme.io/reference/display-requirements |
| Trustpilot | `86400` | `100` | `Trustpilot` | `false` | developers.trustpilot.com/ds-caching-best-practices/ |

Every provider declares `authors: true` and `link: true`; their `rules` are listed in their
manuals.

### Capabilities

What a gateway gives, as configured.

| Property | Type | Default |
|---|---|---|
| `rating` | bool | `false` |
| `reviews` | bool | `false` |
| `reply` | bool | `false` |
| `invite` | bool | `false` |
| `writeUrl` | bool | `false` |

- `gives(): list<string>` - the names of those that are true, in this order: `rating`, `reviews`,
  `reply`, `invite`, `write-url`.
- `toArray(): array` - `['gives' => gives()]` and the five booleans.

## Over HTTP: Answer

`Omnireview\Http\Answer` is the one place the providers call their platform through:

```php
Answer::send(HttpClientInterface $http, string $provider, string $method, string $url, array $options = []): Answer
```

It sends the request (the HTTP client's options: `query`, `headers`, `json`, `body`...; a
`timeout` of 10 seconds unless one is given) and reads the whole answer. No connection, a timeout,
any transport error, an HTTP status of 500 or more, or 429 is an `UnreachableException` (its
`providerCode` the status for an HTTP one); anything else comes back as an `Answer`:

| Member | |
|---|---|
| `status` | int: the HTTP status |
| `headers` | `array<string, list<string>>`, names in lower case |
| `body` | string: the whole body |
| `header(string $name): ?string` | the first value of a header, by any case |
| `json(): array` | the body as a JSON object; `ProviderException` (`HTTP <status>, not a JSON object: ...`) otherwise |

What a 4xx means is the provider's to say (a refused key, an unknown place).
