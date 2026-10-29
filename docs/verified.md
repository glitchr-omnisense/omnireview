---
title: Verified, and not
order: 90
---

# Verified, and not

## What the tests prove

Run on 2026-10-26 with the harness's image against the workspace (the core and its three providers
from their checkouts), PHP 8.4.27, PHPUnit 11.5.57, no network: 19 tests, 129 assertions, green.

- The core: the registry's typed getters and their messages, a required option refused
  (`RegistryTest`); `outOfFive()`, `allowsCaching()`, `gives()`.
- The Symfony bundle: gateways built from configuration and injected by their argument's name;
  the installed providers' factories registered with the application's `http_client`
  (`OmnireviewBundleTest`, on a `ContainerBuilder`, not a kernel).
- The Twig extension: the widget's origin, date, author link, escaped text, review and write
  links, no JSON-LD; the cache used for the platform's allowance and not for `0` or `null`; a
  platform down shows no rating (`OmnireviewExtensionTest`).
- Plain PHP (`BareTest`, `bin/bare`): the registry built by hand, every provider asked a rating,
  reviews and a write link on recorded answers, no class of a framework loaded.
- Each provider on `MockHttpClient` and fixtures written from its platform's reference: requests
  (URLs, headers, field masks, query, bodies, token grants), the mapping to the models, refused
  keys and unknown places (each provider's manual).

Every example of this manual marked as run was run that day with its output pasted: the first
script, the Twig widget, an application test, a provider of its own.

## What is not verified

- **No platform was called live**: no Google Places key, no Business Profile access, no
  Tripadvisor Content API key, no Trustpilot application. Every recorded answer was written from
  the platform's published reference, not captured. The one address checked is Google's link to
  write a review, which answered 200 on 2026-10-08 (as its README states).
- The Symfony bundle in a real kernel with TwigBundle (the template override, a cache pool), and
  the Laravel service provider of [Laravel](laravel.md): written from the code.
- Tripadvisor's Caching Policy is not public: nothing is kept until it is read.
- Whether Trustpilot's Data Solutions API answers with a plain API key: Trustpilot's plans decide.
- The known defects found while writing this manual: the typed getters did not check
  `capabilities()` ([Usage](gateways.md#asking-for-a-capability)), fixed with its test since;
  `omnireview_write_url()` and the
  widget not guarded against a platform down (fixed with its test since), the template override directory losing to the
  bundle's ([Symfony and Twig](symfony.md#dressing-it)), Trustpilot's `locale` said to apply to
  invitations ([Configuration](configuration.md#trustpilot)).

## Responsibility

`omnisense/omnireview` and its `omnireview/*` providers are independent, unofficial clients: none
is affiliated with, endorsed by or sponsored by Google, Tripadvisor or Trustpilot. Each platform
keeps its own terms - keys, display and attribution rules, caching limits, quotas - named with a
link in each provider's README. The MIT licence covers this code only and grants no right to any
service or to its data. Whoever installs and runs these packages is responsible for complying with
those terms and for the use made of what they return.
