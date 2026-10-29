# Changelog

## [Unreleased]

### Fixed

- The registry's typed getters (`rating()`, `reviews()`, `replies()`, `invitations()`,
  `writeUrl()`) check the gateway's `capabilities()` as well as its interface: Trustpilot without a
  business user was given by `replies()`, and its `reply()` then threw; the getter now throws the
  `NotSupportedException` naming what the gateway gives.
