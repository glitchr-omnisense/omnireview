---
title: Writing a provider
order: 80
---

# Writing a provider

A provider is a package of its own, `omnireview/<platform>`, requiring `omnisense/omnireview` and
`symfony/http-client`, with:

- a **factory** extending `Omnireview\GatewayFactory`: `populate(Config)` sets its name, title,
  required options and the defaults of the others; `build(Config)` returns the gateway. It takes
  its HTTP client in its constructor (`?HttpClientInterface $http = null`, named `$http`: the
  Symfony bundle gives the application's `http_client` to an argument of that name);
- a **gateway** implementing the capability interfaces it gives, returning the models, its HTTP
  calls made through `Omnireview\Http\Answer::send()` (a transport error, a 5xx or a 429 become
  `UnreachableException`), a 401 or 403 an `InvalidKeyException`, another error a
  `ProviderException`;
- **`capabilities()`** true to the configuration: a capability that needs an access the options do
  not hold is `false`, and its method throws `NotSupportedException::operation()`;
- **`terms()`** read in the platform's documentation, with `source`: how long it may be kept
  (`0`, `null` when the platform's policy is not public), the reviews per call, the attribution,
  `noIndex`, the other rules in words;
- **tests** on `MockHttpClient` with the platform's answers in `Tests/Fixtures`.

## A whole provider

A platform whose JSON API gives a place's average, its reviews, and a page to write one:

```php
<?php

namespace Acme\Guestbook;

use Omnireview\Config;
use Omnireview\GatewayFactory;
use Omnireview\GatewayInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class GuestbookGatewayFactory extends GatewayFactory
{
    public function __construct(private readonly ?HttpClientInterface $http = null)
    {
    }

    protected function populate(Config $c): void
    {
        $c->defaults([
            'omnireview.factory_name' => 'guestbook',
            'omnireview.factory_title' => 'Guestbook',
            'omnireview.required_options' => ['token'],
            'base_url' => 'https://api.guestbook.example/v2/',
        ]);
    }

    protected function build(Config $c): GatewayInterface
    {
        return new GuestbookGateway($this->http ?? HttpClient::create(), (string) $c['token'], (string) $c->string('base_url'));
    }
}
```

```php
<?php

namespace Acme\Guestbook;

use Omnireview\Exception\InvalidKeyException;
use Omnireview\Exception\ProviderException;
use Omnireview\Http\Answer;
use Omnireview\Model\Capabilities;
use Omnireview\Model\Place;
use Omnireview\Model\Rating;
use Omnireview\Model\Review;
use Omnireview\Model\Terms;
use Omnireview\RatingInterface;
use Omnireview\ReviewsInterface;
use Omnireview\WriteUrlInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class GuestbookGateway implements RatingInterface, ReviewsInterface, WriteUrlInterface
{
    public function __construct(
        private readonly HttpClientInterface $http,
        #[\SensitiveParameter] private readonly string $token,
        private readonly string $baseUrl,
    ) {
    }

    public function getName(): string
    {
        return 'guestbook';
    }

    public function getTitle(): string
    {
        return 'Guestbook';
    }

    public function capabilities(): Capabilities
    {
        return new Capabilities(rating: true, reviews: true, writeUrl: true);
    }

    public function terms(): Terms
    {
        return new Terms(cacheFor: 3600, maxReviews: 20, attribution: 'Guestbook', source: 'https://guestbook.example/terms');
    }

    public function rating(Place $place): Rating
    {
        $data = $this->call('places/'.rawurlencode($place->id));

        return new Rating('guestbook', (float) ($data['average'] ?? 0), (int) ($data['count'] ?? 0), url: $data['url'] ?? null);
    }

    public function reviews(Place $place, int $limit = 5, ?string $language = null): array
    {
        $data = $this->call('places/'.rawurlencode($place->id).'/reviews', array_filter(['per_page' => max(1, min(20, $limit)), 'lang' => $language]));

        return array_slice(array_map(static fn (array $r) => new Review(
            'guestbook',
            (string) $r['id'],
            (float) $r['stars'],
            $r['body'] ?? null,
            author: $r['author'] ?? null,
            publishedAt: isset($r['date']) ? new \DateTimeImmutable($r['date']) : null,
            url: $r['url'] ?? null,
        ), $data['items'] ?? []), 0, $limit);
    }

    public function writeUrl(Place $place): string
    {
        return 'https://guestbook.example/write/'.rawurlencode($place->id);
    }

    /** @return array<string, mixed> */
    private function call(string $path, array $query = []): array
    {
        $answer = Answer::send($this->http, 'guestbook', 'GET', $this->baseUrl.$path, ['query' => $query, 'headers' => ['Authorization' => 'Bearer '.$this->token]]);
        if (\in_array($answer->status, [401, 403], true)) {
            throw new InvalidKeyException('guestbook', \sprintf('The token was refused (HTTP %d).', $answer->status), (string) $answer->status);
        }
        if ($answer->status >= 400) {
            throw new ProviderException('guestbook', \sprintf('HTTP %d: %s', $answer->status, $answer->json()['message'] ?? ''), (string) $answer->status);
        }

        return $answer->json();
    }
}
```

Used on a `MockHttpClient` that answers `p1` and a 404 otherwise:

```php
$registry = new Registry([new GuestbookGatewayFactory($http)], ['guestbook' => ['factory' => 'guestbook', 'options' => ['token' => 'secret']]]);
$rating = $registry->rating('guestbook')->rating(new Place('p1'));
echo $rating->value, ' from ', $rating->count, ', ', $rating->url, "\n";
foreach ($registry->reviews('guestbook')->reviews(new Place('p1'), 3) as $review) {
    echo '  ', $review->rating, ' by ', $review->author, ' on ', $review->publishedAt->format('Y-m-d'), "\n";
}
echo implode(', ', $registry->get('guestbook')->capabilities()->gives()), "\n";
// then: rating(new Place('nope')), and (new GuestbookGatewayFactory($http))->create([])
```

```
4.8 from 52, https://guestbook.example/p1
  5 by Camille R. on 2026-10-01
rating, reviews, write-url
Omnireview\Exception\ProviderException: [guestbook] HTTP 404: Unknown place. (404)
Omnireview\Exception\InvalidConfigException: The "guestbook" gateway needs: token.
```

(run on 2026-10-26 in the harness's image, no network)

## Registering it

- Plain PHP: give the registry an instance (`new Registry([new GuestbookGatewayFactory($http)],
  ...)`).
- Symfony: a service implementing `GatewayFactoryInterface` is autoconfigured with the tag
  `omnireview.gateway_factory`; with the application's autowiring, the class in `src/` is enough.
  The bundle registers on its own only the three factories it knows (`OmnireviewBundle::FACTORIES`).
- The harness: an entry in `docker/harness/plugins.php` (its slug, tests' namespace and factory),
  a block in `docker/harness/config/gateways.php` (its environment variables, example options and
  place) and its fixtures in `docker/harness/config/recorded.php`.

## What the existing providers do

Read their code before writing one: `GoogleGateway` (two APIs behind one gateway, an OAuth refresh
token kept until a minute before it lapses, `Place::$owner` selecting the owner's side),
`TripadvisorGateway` (a key in the query, one details call kept for the rating and the write
link), `TrustpilotGateway` (three hosts, a client-credentials token, a business user's header, a
`find()` of its own).
