---
title: Models
order: 2
---

# Models

All are `readonly`, in `Omnireview\Model`.

| Model | |
|---|---|
| `Place` | `id` (the platform's: a Google place ID, a Tripadvisor location ID, a Trustpilot business unit ID), `name`, `owner` (Google: the Business Profile location, `accounts/{a}/locations/{l}`, for the owner's side) |
| `Rating` | `platform`, `value`, `count`, `scale` (5 everywhere today), `url` (the place on the platform), `image` (Tripadvisor's bubbles, served from Tripadvisor), `fetchedAt`; `outOfFive()` |
| `Review` | `platform`, `id`, `rating`, `text`, `title`, `language`, `author`, `authorUrl`, `authorPhoto`, `publishedAt`, `url` (the review on the platform), `reply`, `scale`, `translated`, `image` |
| `Reply` | `text`, `at`: the establishment's answer |
| `Invitation` | `email`, `name`, `reference` (the order), `locale`, `sendAt`, `redirectUrl` |
| `Terms` | the platform's rules: `cacheFor` (seconds; 0 never; null "per its caching policy", not published), `maxReviews`, `attribution`, `authors`, `link`, `noIndex`, `rules` (the rest, in words), `source`; `allowsCaching($seconds)` |
| `Capabilities` | `rating`, `reviews`, `reply`, `invite`, `writeUrl` as configured; `gives()`, `toArray()` |

A review's text is the platform's, as its author wrote it: show it escaped, within quotation
marks, unaltered - shortened only with a link to the whole.
