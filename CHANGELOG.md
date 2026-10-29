# Changelog

## [Unreleased]

### Fixed

- The registry's typed getters (`rating()`, `reviews()`, `replies()`, `invitations()`,
  `writeUrl()`) check the gateway's `capabilities()` as well as its interface: Trustpilot without a
  business user was given by `replies()`, and its `reply()` then threw; the getter now throws the
  `NotSupportedException` naming what the gateway gives.
- `omnireview_write_url()` gives `null`, and `omnireview_widget()` no write link, when the platform
  does not answer (`UnreachableException`): Tripadvisor and Trustpilot fetch the link, and their
  `HTTP 503` reached the template, where the extension shows nothing of a platform down.
  `OmnireviewExtension::writeUrl()` returns `?string`.
- An application's `templates/bundles/OmnireviewBundle/reviews.html.twig` overrides the widget:
  the bundle prepended its templates as a `twig.paths` entry, which TwigBundle registers before the
  bundles' override directories, so the bundle's own template kept winning. The bundle's path is
  now `Bridge/Twig` (its `templates/` found by TwigBundle after the override) and nothing is
  prepended to `twig`.
