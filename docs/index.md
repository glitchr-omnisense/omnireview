# omnisense/omnireview

Omnireview is one PHP contract for what customers say of an establishment on platforms it does not
run - its rating, its reviews, replying to a review as the establishment, inviting a customer to
leave one, the link to write one - whoever hosts them: Google, Tripadvisor, Trustpilot. The
application type-hints `Omnireview\Registry` (the configured platforms by name) or one of the
capability interfaces, and reads neutral models:

```php
$place = new Place('ChIJLU7jZClu5kcR4PcOOO6p3I0');                 // the platform's identifier, kept by the application

$rating = $registry->rating('google')->rating($place);
$rating->value; $rating->count; $rating->url;                      // 4.6, 128, the place on Google Maps

foreach ($registry->reviews('google')->reviews($place, 3) as $review) {
    $review->author; $review->publishedAt; $review->url;           // shown with every review
}
$registry->writeUrl('google')->writeUrl($place);                   // where a customer writes one
$registry->get('google')->terms();                                 // how long it may be kept, how it must be credited
```

Each platform is a provider package (`omnireview/google`, `omnireview/tripadvisor`,
`omnireview/trustpilot`) with a factory that builds a gateway from options. A gateway reads and
writes on its platform and keeps nothing; it declares the platform's **terms** (`Terms`) - how long
what it gives may be kept, how many reviews one call gives, what must be shown with it, whether a
page that shows it must stay out of search engines - read in each platform's documentation, with
the address they were read at. The core requires PHP 8.2 and `symfony/http-client-contracts`,
nothing of a framework; it ships a Symfony bundle and a Twig extension, whose components the
application brings.

## When to use it

- A site that shows its Google, Tripadvisor or Trustpilot rating and a few reviews, credited to
  the platform as the platform requires, and links to the platform to read them all and to write
  one.
- A back office that answers reviews as the establishment (Google Business Profile, Trustpilot) or
  invites customers after a purchase (Trustpilot), through one interface per action.
- Code that must work with whichever platform a customer of the application uses: the
  configuration names the platform, the code asks the registry for a capability.

It is not a review store: nothing is collected, kept or rewritten here, and no JSON-LD
`AggregateRating` is built from a third party's reviews (Google's review snippet guidelines
exclude them). It is not a way to pick which customers to invite: Google and Trustpilot forbid
soliciting only positive reviews.

## Providers

Three packages. "Gives" is what each gateway's `capabilities()` answers with the options named;
"Kept" is its `Terms::$cacheFor`; "Reviews" its `Terms::$maxReviews`. Read from the code at this
commit and printed by the [harness](harness.md)'s `bare` script.

| Package | Factory name | Gives | With the owner's access | Kept | Reviews per call | Pages not indexed |
|---|---|---|---|---|---|---|
| [`omnireview/google`](https://github.com/glitchr-omnisense/omnireview-google) | `google` | rating, reviews, write-url (Places API, `api_key`) | reply, and up to 50 reviews (Business Profile API: `client_id`, `client_secret`, `refresh_token`) | never (`0`): the place ID only | 5 declared; 50 from the Business Profile | no |
| [`omnireview/tripadvisor`](https://github.com/glitchr-omnisense/omnireview-tripadvisor) | `tripadvisor` | rating, reviews, write-url (Content API, `api_key`) | - | as Tripadvisor's Caching Policy allows (`null`: not public, so not kept) | 5 | yes |
| [`omnireview/trustpilot`](https://github.com/glitchr-omnisense/omnireview-trustpilot) | `trustpilot` | rating, reviews, write-url (`api_key`) | reply, invite (`api_secret`, `business_user_id`) | 24 hours (`86400`) | 100 | no |

No platform has been called live by these packages: no key was at hand (each provider's "Verified,
and not"). Google's link to write a review is the one address checked, on 2026-10-08.

## How this manual is organised

- [Installation](installation.md): requirements, a first script in plain PHP and its output.
- [Symfony and Twig](symfony.md): the bundle, its configuration tree, its services, the Twig
  functions and the widget.
- [Laravel](laravel.md): a service provider written by hand (there is no Laravel bridge).
- [Concepts and models](models.md): the contract interface by interface, every model and field.
- [Usage](gateways.md): rating, reviews, replying, inviting, the write link, the terms - per
  capability, with what each platform does.
- [Configuration reference](configuration.md): the registry, `Config`, every option of every
  factory, the bundle's keys.
- [Errors](errors.md): the exceptions, when each is thrown, what to catch.
- [Testing](testing.md): testing code that uses the family, the family's own tests.
- [The Docker harness](harness.md): bare PHP, the console, the tests.
- [Writing a provider](providers.md).
- [Verified, and not](verified.md).
