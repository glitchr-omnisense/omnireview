---
title: The Docker harness
order: 5
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

The image is `php:8.4-cli-alpine` with Composer; the harness's packages live in the `harness`
volume of the `omnireview-harness` project.
