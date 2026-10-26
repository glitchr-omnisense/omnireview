---
title: The Docker harness
order: 70
---

# The Docker harness

`docker/` runs this package with every `omnireview/*` gateway installed - from GitHub (branch 1.x),
or from the checkouts beside this one when `OMNIREVIEW_PLUGINS=../..` is set in `docker/.env`.

```sh
cd docker && cp .env.dist .env       # a platform's key and a place, when you have them
docker compose run --rm omnireview bare
```

| Command | |
|---|---|
| `gateways` | the gateways installed and configured, what each gives, its terms - nothing called |
| `bare` | plain PHP: the registry built by hand, each platform asked a rating, reviews, its write link; what PHP loaded |
| `bare --live` | the same, each platform whose key and place are set in `.env` asked for real |
| `bare --json` | the same, whole: every class and file loaded |
| `test` | every package's tests |

## Bare: no bundle, no container, no Twig

`docker/harness/bin/bare` requires the autoloader and nothing else. It builds the `Registry` from
the platform packages installed and asks each a place's rating, three reviews and the link to write
one - the platforms answering from their packages' recorded answers - then lists what PHP loaded,
and exits 1 if a class of a framework is among it:

```
$ docker compose run --rm omnireview bare
Omnireview in bare PHP: the registry built by hand, no bundle, no container, no Twig; the platforms' recorded answers.

  google      Google: gives rating reviews write-url; kept never, 5 reviews at most, credited to "Google Maps"
  tripadvisor Tripadvisor: gives rating reviews write-url; kept as its policy says, 5 reviews at most, credited to "Tripadvisor", pages not indexed
  trustpilot  Trustpilot: gives rating reviews write-url; kept 86400 s, 100 reviews at most, credited to "Trustpilot"

  google      4.6/5 from 128 reviews, https://maps.google.com/?cid=10223305813012340704
                5 by Camille R. on 2026-09-23, https://www.google.com/maps/reviews/data=1
                4 by Jordan K. on 2026-09-01, https://www.google.com/maps/reviews/data=2
                to write one: https://search.google.com/local/writereview?placeid=ChIJLU7jZClu5kcR4PcOOO6p3I0
  tripadvisor 4.5/5 from 312 reviews, https://www.tripadvisor.fr/Restaurant_Review-g187147-d1234567-Reviews-Maison_Erable-Paris.html
                5 by Camille R on 2026-09-20, https://www.tripadvisor.fr/ShowUserReviews-g187147-d1234567-r900001-Maison_Erable-Paris.html
                to write one: https://www.tripadvisor.fr/UserReview-g187147-d1234567-Maison_Erable-Paris.html
  trustpilot  4.4/5 from 469 reviews, https://fr.trustpilot.com/review/www.maison-erable.example
                5 by Camille R. on 2026-09-30, https://www.trustpilot.com/reviews/507f191e810c19729de860ea
                to write one: https://fr.trustpilot.com/evaluate/www.maison-erable.example

Loaded from Symfony: Symfony\Component\HttpClient, Symfony\Contracts\HttpClient, Symfony\Contracts\Service
Classes of a framework (DependencyInjection, Config, HttpKernel, HttpFoundation, a bundle or bridge, Doctrine, Twig): none
```

`Tests/BareTest.php` runs `bare --json` in a process of its own and checks the list - and, so that
the check is not blind, that loading the bundle does name the framework.

The image is `php:8.4-cli-alpine` with Composer and the `zip` extension
(`omnisense/omnireview-harness`); the harness's packages live in the `harness` volume of the
`omnireview-harness` project.

## How it is composed

`docker/entrypoint.sh` runs `docker/harness/setup.php`, which writes `/harness/composer.json` and
`/harness/phpunit.xml`: this core from its checkout (a path repository, `/omnireview/core`), each
gateway of `docker/harness/plugins.php` (`google`, `tripadvisor`, `trustpilot`: its tests'
namespace and its factory class) from its checkout under `/omnireview/plugins` when there is one
whose `composer.json` names it, else from `https://github.com/glitchr-omnisense/omnireview-<slug>.git`
(branch 1.x). Composer installs when `vendor/` is missing and updates when `composer.json` is newer
than its lock. Then the first argument picks the command:

| Argument | Runs |
|---|---|
| `test` | `vendor/bin/phpunit` with the rest of the arguments: one test suite per package |
| `bare` | `docker/harness/bin/bare` |
| `composer`, `sh`, `php` | that program, as it is |
| anything else (default `gateways`) | the console, `docker/harness/bin/omnireview` |

## Settings

`docker/.env` (copied from `.env.dist`, git-ignored):

| Variable | |
|---|---|
| `OMNIREVIEW_PLUGINS` | where the gateways' checkouts are (`../..` in the workspace); unset, they come from GitHub |
| `GOOGLE_PLACES_KEY`, `GOOGLE_PLACE_ID` | the Places API key and a place: `google` is configured with both |
| `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REFRESH_TOKEN`, `GOOGLE_LOCATION` | the owner's side: the OAuth client, the refresh token, `accounts/<a>/locations/<l>` |
| `TRIPADVISOR_KEY`, `TRIPADVISOR_LOCATION`, `TRIPADVISOR_REFERER` | the Content API key, a location ID, the `Referer` |
| `TRUSTPILOT_API_KEY`, `TRUSTPILOT_BUSINESS_UNIT` | the key and a business unit ID |
| `TRUSTPILOT_API_SECRET`, `TRUSTPILOT_BUSINESS_USER_ID` | replying and inviting |

`docker/harness/config/gateways.php` reads them: a gateway is configured from them only when every
variable of its `needs` is set, else with example options (`api_key: example`) and an example place.
`gateways` prints which; `bare` uses the example options with the recorded answers unless
`--live` is given and the variables are set. `OMNIREVIEW_AUTOLOAD` makes `bare` use another
autoloader (a workspace's `vendor/autoload.php`).

## The console

`docker compose run --rm omnireview gateways` builds the registry from the installed factories and
prints, nothing called: each gateway, whether it is installed, whether its settings come from
`.env` or the example (naming the variables missing), what it gives, how long it may be kept, the
reviews per call, and what it is credited to.

## Running against the workspace

Without the harness's volume, the image runs the workspace's own `vendor/` (every package
symlinked from its checkout), read-only and offline:

```sh
docker run --rm --network none --entrypoint php -v ~/Sites/omnireview:/w:ro -w /w \
    omnisense/omnireview-harness vendor/bin/phpunit --cache-directory /tmp/pu
```

This is how the examples of this manual were run on 2026-10-26 (19 tests, 129 assertions, green).
