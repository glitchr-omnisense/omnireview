---
title: Configuration reference
order: 40
---

# Configuration reference

Every option below is read from the factories' code at this commit (`populate()` and what `build()`
reads). Options reach a factory through `create($options)`, a `Registry`'s `options`, or the
bundle's `omnireview.gateways.<name>.options` ([Symfony](symfony.md#configuration)).

## The registry

```php
new Registry(iterable $factories, array $config);
// $config: ['google' => ['factory' => 'google', 'options' => ['api_key' => '...']], ...]
```

| Method | |
|---|---|
| `get(string $name): GatewayInterface` | the gateway, built on the first call and kept |
| `has(string $name): bool` | whether it is configured |
| `rating()`, `reviews()`, `replies()`, `invitations()`, `writeUrl()` | the gateway as that interface, or `NotSupportedException` ([Usage](gateways.md#asking-for-a-capability)) |
| `options(string $name): array` | the options it is configured with |
| `create(string $name, array $overrides = []): GatewayInterface` | a gateway built afresh, its options with `$overrides` over them (`array_replace`), not kept |
| `names(): list<string>` | the configured gateways' names |
| `all(): array<string, GatewayInterface>` | every configured gateway, built |
| `factories(): list<string>` | the factories installed: what `factory` may name |

A later factory of the same name replaces an earlier one. A name not configured is an
`InvalidConfigException` (`No "x" gateway; configured: google, trustpilot.`), so is a factory not
installed (`No "yelp" factory for the "x" gateway; installed: google, trustpilot.`).

## How options are read

`Omnireview\Config` (an `ArrayObject`) holds the options given, with the factory's defaults under
them (`defaults()` sets only the keys not given). Before `build()`, `validateNotEmpty()` refuses
each key of `omnireview.required_options` that is missing, `''` or `[]`. The readers:

| Reader | |
|---|---|
| `get($key, $default = null)` | the value as given |
| `string($key): ?string` | the value as a string; `null` when it is `null` or `''` - so an empty environment variable is "not set" |
| `bool($key): bool` | a boolean, or `"1"`, `"true"`, `"yes"`, `"on"` (any case) as true |
| `list($key): list<string>` | a list, or a comma-separated string split and trimmed |

The three keys every factory sets for itself:

| Key | |
|---|---|
| `omnireview.factory_name` | the factory's name (`getName()` reads it) |
| `omnireview.factory_title` | its title |
| `omnireview.required_options` | the options that must not be empty |

## google

`new GoogleGatewayFactory(?Symfony\Contracts\HttpClient\HttpClientInterface $http = null)`
(`HttpClient::create()` when `null`).

| Option | Type | Default | Required | |
|---|---|---|---|---|
| `api_key` | string | `null` | one side or the other | the Places API (New) key: anyone's place |
| `language` | string | `null` | no | the reviews' language (`languageCode` of the Places API), unless a call gives its own |
| `client_id` | string | `null` | for the owner's side | an OAuth 2.0 client |
| `client_secret` | string | `null` | for the owner's side | |
| `refresh_token` | string | `null` | for the owner's side | granted once by the owner, scope `business.manage` |

`api_key`, or all three of the owner's, or both; neither is an `InvalidConfigException` (`The
"google" gateway needs an api_key (Places), or the owner's client_id, client_secret and
refresh_token (Business Profile).`). `reply` is given only with the owner's three.

## tripadvisor

`new TripadvisorGatewayFactory(?HttpClientInterface $http = null)`.

| Option | Type | Default | Required | |
|---|---|---|---|---|
| `api_key` | string | - | yes | the Content API's partner key |
| `language` | string | `en` | no | the content's language (`fr`, `de`, `ja`...) |
| `referer` | string | `null` | no | the `Referer` header sent: for a key restricted to domains |

## trustpilot

`new TrustpilotGatewayFactory(?HttpClientInterface $http = null)`.

| Option | Type | Default | Required | |
|---|---|---|---|---|
| `api_key` | string | - | yes | the application's key: the TrustScore, the reviews, the links |
| `locale` | string | `fr-FR` | no | the locale of the profile's links (`/web-links`); an invitation is sent in its own `Invitation::$locale`, never this option |
| `api_secret` | string | `null` | for reply and invite | the application's secret: the business user's token |
| `business_user_id` | string | `null` | for reply and invite | the business user who replies and invites |
| `template_id` | string | `null` | no | the invitation e-mail's template |
| `reply_to` | string | `null` | no | the invitation's reply-to address |
| `sender_name` | string | `null` | no | the invitation's sender name |
| `sender_email` | string | `null` | no | the invitation's sender address |

`reply` and `invite` are given only with `api_secret` and `business_user_id`.

## The bundle

`omnireview.gateways` and `omnireview.cache` ([Symfony and Twig](symfony.md#configuration)). The
Twig extension's constructor, for a hand-built Twig environment:

```php
new OmnireviewExtension(Registry $registry, ?Symfony\Contracts\Cache\CacheInterface $cache = null, int $ttl = 3600);
```
