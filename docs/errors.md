---
title: Errors
order: 50
---

# Errors

Every exception of the family implements `Omnireview\Exception\OmnireviewException` (an interface
extending `\Throwable`):

| Exception | Extends | Thrown when |
|---|---|---|
| `InvalidConfigException` | `\LogicException` | a gateway not configured, a factory not installed, a required option missing or empty, Google with neither its key nor the owner's access |
| `NotSupportedException` | `\LogicException` | the gateway does not give that: the registry's getter for an interface the gateway does not implement, or a `reply()`/`invite()` without the owner's or business user's access |
| `ProviderException` | `\RuntimeException` | the platform answered with an error: a place it does not know, a request it refused, a body that is not JSON, a write link it does not give |
| `InvalidKeyException` | `ProviderException` | the platform refused the key, the token or the account (HTTP 401 or 403; a refresh token or client credentials refused; Google's Places without an `api_key`) |
| `UnreachableException` | `ProviderException` | the platform did not answer: no connection, a timeout, an HTTP status of 500 or more, or 429 |

`ProviderException` and its two children keep what the platform said:

| Property | |
|---|---|
| `provider` | the factory's name: `google`, `tripadvisor`, `trustpilot` |
| `providerCode` | the platform's own code, when it gave one: Google's `status` (`NOT_FOUND`, `PERMISSION_DENIED`), Tripadvisor's `error.code` or the HTTP status, Trustpilot's HTTP status, the HTTP status of an `UnreachableException` |
| `getMessage()` | `[<provider>] <message>`, the message naming the HTTP status and the platform's own words |
| `getPrevious()` | the HTTP client's exception, for an `UnreachableException` without an answer |

The messages, by thrower:

| Where | Message |
|---|---|
| `Registry::get()` | `No "<name>" gateway; configured: <names>.` / `No "<factory>" factory for the "<name>" gateway; installed: <factories>.` |
| `Config::validateNotEmpty()` | `The "<factory>" gateway needs: <options>.` |
| `NotSupportedException::question()` | `The "<name>" gateway (<title>) does not give <what>; it gives: <capabilities>.` |
| `NotSupportedException::operation()` | `The "<name>" gateway does not <operation>: <why>.` |
| `Answer::send()` | `[<provider>] No answer: <transport error>` / `[<provider>] HTTP <status>: <first 200 characters of the body>` |
| `Answer::json()` | `[<provider>] HTTP <status>, not a JSON object: <body>` |

What to catch:

```php
use Omnireview\Exception\InvalidKeyException;
use Omnireview\Exception\ProviderException;
use Omnireview\Exception\UnreachableException;

try {
    $rating = $registry->rating('trustpilot')->rating($place);
} catch (UnreachableException) {
    $rating = null;                                     // show nothing: the platform is down or asked to slow down
} catch (InvalidKeyException $e) {
    $logger->critical($e->getMessage());                // the configuration is wrong
    $rating = null;
} catch (ProviderException $e) {
    $logger->error($e->getMessage(), ['code' => $e->providerCode]);   // an unknown place
    $rating = null;
}
```

`InvalidConfigException` and `NotSupportedException` are programming errors (a `LogicException`):
test `capabilities()` and `Registry::has()` rather than catch them.

## What is not an exception

- A platform that gives fewer reviews than asked, or none: a shorter list.
- A field the platform leaves out: `null` (or `0` for a score or a count) in the model.
- A date the platform writes in a form PHP cannot read: `publishedAt` `null`.
- In Twig, `UnreachableException` from `omnireview_rating()` and `omnireview_reviews()`: `null` and
  `[]` ([Symfony and Twig](symfony.md#a-platform-that-does-not-answer)).

## Outside the family

The HTTP client's own exceptions never reach the application: `Answer::send()` makes them
`UnreachableException`s. PHP's `TypeError` comes from injecting, in Symfony, a gateway under an
interface it does not implement ([Symfony](symfony.md#services)).
