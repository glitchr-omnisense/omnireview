---
title: Gateways
order: 3
---

# Gateways

## The contract

`GatewayInterface` says who it is (`getName()`, `getTitle()`), what it gives as configured
(`capabilities()`) and on which terms (`terms()`). What it gives is a set of interfaces it
implements:

| Interface | | |
|---|---|---|
| `RatingInterface` | `rating(Place): Rating` | the average and the count |
| `ReviewsInterface` | `reviews(Place, int $limit = 5, ?string $language = null): list<Review>` | the most recent or most relevant few, as the platform gives them |
| `ReplyInterface` | `reply(Place, Review, string $text): Reply` | as the establishment: its own access |
| `InviteInterface` | `invite(Place, Invitation): ?string` | a customer asked to review, by the platform's e-mail |
| `WriteUrlInterface` | `writeUrl(Place): string` | where a customer writes one |

A gateway may implement an interface and still not give it as configured (a reply without the
owner's access): it then throws `NotSupportedException`, and `capabilities()` says so. The
registry's typed getters (`$registry->replies('google')`) check it first.

## What each gives

| | Google | Tripadvisor | Trustpilot |
|---|---|---|---|
| Rating | Places API (New); the owner's Business Profile | Content API, `/details` | Data Solutions, `/business-units/{id}` |
| Reviews | 5 (Places), up to 50 (Business Profile) | 5 | up to 100 |
| Reply | Business Profile, the owner's refresh token | - (not in the Content API) | business user's token |
| Invite | - (Google has none: share `writeUrl()`) | - | Invitations API |
| Write URL | `search.google.com/local/writereview?placeid=` | `write_review` of `/details` | `evaluateUrl` of `/web-links` |
| Kept | never, the place ID only | per its Caching Policy (not public): not kept here | 24 hours at most |
| Pages not indexed | no | **yes** | no |

Each package's documentation has its options, its sources, its terms in full and what was verified
- **nothing yet against a live platform: no keys** (see each "Verified, and not").

## Writing one

A package of its own, `omnireview/<platform>`, with:

- a factory extending `Omnireview\GatewayFactory`: `populate(Config)` sets the name, the title, the
  required options and their defaults; `build(Config)` returns the gateway;
- a gateway implementing `GatewayInterface` and the interfaces it gives, returning the models, its
  HTTP calls through `Omnireview\Http\Answer::send()` (a transport error, a 5xx or a 429 become
  `UnreachableException`);
- `terms()` read in the platform's documentation, with `source`;
- tests on `MockHttpClient` with the platform's answers in `Tests/Fixtures`.

Register it with the bundle as a service implementing `GatewayFactoryInterface` (autoconfigured),
and in `docker/harness/plugins.php` for the harness.
