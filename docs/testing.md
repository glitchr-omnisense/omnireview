---
title: Testing
order: 60
---

# Testing

Testing an application that shows or answers reviews through omnireview, and what the family's own
tests cover. The core ships no fake gateway and no conformance suite: `Tests/StubFactory.php` is
the core's own test double, autoloaded only in its development (`autoload-dev`). An application
tests its code either against a real provider on a `MockHttpClient`, or with a gateway of its own.

## A provider on a MockHttpClient

Every factory takes its HTTP client in its constructor. Given Symfony's `MockHttpClient`, the
provider runs whole - its requests, its mapping, its errors - and nothing leaves the machine:

```php
<?php

use Omnireview\Exception\UnreachableException;
use Omnireview\Google\GoogleGatewayFactory;
use Omnireview\Model\Place;
use Omnireview\Registry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

/** The application's code: a one-line badge, or nothing when the platform is down. */
final class ReviewBadge
{
    public function __construct(private readonly Registry $registry)
    {
    }

    public function line(string $gateway, string $placeId): ?string
    {
        try {
            $rating = $this->registry->rating($gateway)->rating(new Place($placeId));
        } catch (UnreachableException) {
            return null;
        }

        return \sprintf('%.1f/%d (%d) on %s', $rating->value, $rating->scale, $rating->count, $this->registry->get($gateway)->terms()->attribution);
    }
}

final class ReviewBadgeTest extends TestCase
{
    private function registry(MockHttpClient $http): Registry
    {
        return new Registry([new GoogleGatewayFactory($http)], ['google' => ['factory' => 'google', 'options' => ['api_key' => 'test-key']]]);
    }

    public function testTheBadgeIsTheRatingCreditedToThePlatform(): void
    {
        $answer = new JsonMockResponse(['id' => 'ChIJ1', 'rating' => 4.6, 'userRatingCount' => 128, 'googleMapsUri' => 'https://maps.google.com/?cid=1']);
        $http = new MockHttpClient($answer);

        self::assertSame('4.6/5 (128) on Google Maps', (new ReviewBadge($this->registry($http)))->line('google', 'ChIJ1'));
        self::assertSame('https://places.googleapis.com/v1/places/ChIJ1', $answer->getRequestUrl());
        self::assertContains('X-Goog-Api-Key: test-key', $answer->getRequestOptions()['headers']);
    }

    public function testNothingWhenThePlatformIsDown(): void
    {
        $http = new MockHttpClient(new MockResponse('', ['http_code' => 503]));

        self::assertNull((new ReviewBadge($this->registry($http)))->line('google', 'ChIJ1'));
    }
}
```

```
$ vendor/bin/phpunit --testdox tests/
Review Badge
 ✔ The badge is the rating credited to the platform
 ✔ Nothing when the platform is down

OK (2 tests, 4 assertions)
```

(run on 2026-10-26 in the harness's image, PHPUnit 11.5.57, no network)

The answers each provider expects are in its `Tests/Fixtures` (Google's `place.json`,
Tripadvisor's `details.json`, Trustpilot's `business-unit.json`...), written from each platform's
reference; the harness's `docker/harness/config/recorded.php` is a `MockHttpClient` callback that
serves all of them by URL, for a test that needs every platform at once:

```php
$http = new MockHttpClient(require 'vendor/omnisense/omnireview/docker/harness/config/recorded.php');
```

A 503, a 429 or a transport error tests the "platform down" path (`UnreachableException`); a 401
or 403 the refused key (`InvalidKeyException`); a 404 with the platform's error body the unknown
place (`ProviderException`).

## A gateway of the application's own

For code that only needs models back, a class implementing the capability interfaces it needs, in
the test's namespace, given to the registry through a factory:

```php
use Omnireview\Config;
use Omnireview\GatewayFactory;
use Omnireview\GatewayInterface;
use Omnireview\Model\{Capabilities, Place, Rating, Terms};
use Omnireview\RatingInterface;

final class FixedRatingFactory extends GatewayFactory
{
    protected function populate(Config $c): void
    {
        $c->defaults(['omnireview.factory_name' => 'fixed', 'omnireview.factory_title' => 'Fixed', 'value' => 4.5]);
    }

    protected function build(Config $c): GatewayInterface
    {
        return new class((float) $c['value']) implements RatingInterface {
            public function __construct(private readonly float $value) {}
            public function getName(): string { return 'fixed'; }
            public function getTitle(): string { return 'Fixed'; }
            public function capabilities(): Capabilities { return new Capabilities(rating: true); }
            public function terms(): Terms { return new Terms(cacheFor: 3600, attribution: 'Fixed'); }
            public function rating(Place $place): Rating { return new Rating('fixed', $this->value, 10); }
        };
    }
}

$registry = new Registry([new FixedRatingFactory()], ['google' => ['factory' => 'fixed']]);
```

The core's own `Tests/StubFactory.php` is such a gateway (rating, reviews and write link from an
option `host`, a call counter, the place `down` raising `UnreachableException`); copy it rather
than depend on it.

## Twig and Symfony

- The Twig extension takes any `CacheInterface`: give it Symfony's `ArrayAdapter` and a call
  counter to test what is kept, as `Tests/Bridge/OmnireviewExtensionTest.php` does; with
  `null`, nothing is kept.
- In a Symfony functional test, the registry is the public service `Omnireview\Registry`; replace
  the HTTP client the factories receive by configuring `framework.http_client.mock_response_factory`
  in the test environment, or configure the gateway under the test environment with a factory of
  the application's own (autoconfigured, [Symfony](symfony.md#an-applications-own-platform)).
  Neither was run in an application for this page.

## The family's own tests

The workspace (`~/Sites/omnireview`, every package from its checkout) runs 19 tests, 129
assertions ([Verified, and not](verified.md)):

| Test | What it checks |
|---|---|
| `RegistryTest::testEachCapabilityIsAskedOfAGatewayThatGivesIt` | the typed getter gives the gateway; one it does not implement is a `NotSupportedException` with its message; a required option missing is an `InvalidConfigException` |
| `RegistryTest::testAGetterRefusesWhatTheGatewayDoesNotGiveAsConfigured` | a gateway that implements `ReplyInterface` but whose `capabilities()` say no reply: `replies()` is a `NotSupportedException` naming what it gives |
| `RegistryTest::testRatingsTermsAndCapabilities` | `outOfFive()` on a scale of 10, `allowsCaching()` for 86400, 0 and `null`, `gives()` |
| `OmnireviewBundleTest` (2) | gateways built from configuration and injected by argument name; the providers installed registered with the application's `http_client` |
| `OmnireviewExtensionTest` (3) | the widget's origin, date, links, escaped text and no JSON-LD; kept for an hour, never for 0 or `null`; a platform down shows nothing: no rating, no write link, a widget without one |
| `BareTest` (3) | `bare --json` in a process of its own: no framework class loaded, each platform's rating, reviews and links; loading the bundle does name the framework |
| `GoogleGatewayTest` (3), `TripadvisorGatewayTest` (3), `TrustpilotGatewayTest` (3) | each provider on `MockHttpClient` and its fixtures (their manuals) |
