<?php

namespace Omnireview\Bridge\Twig;

use Omnireview\Exception\UnreachableException;
use Omnireview\Model\Place;
use Omnireview\Model\Rating;
use Omnireview\Model\Review;
use Omnireview\Model\Terms;
use Omnireview\Registry;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Ratings and reviews in a template, always with their origin, date and
 * link - never as JSON-LD:
 *
 *     {{ omnireview_widget('google', place_id) }}                        {# @Omnireview/reviews.html.twig: override it to dress it #}
 *     {% set rating = omnireview_rating('trustpilot', business_unit_id) %}
 *     {% for review in omnireview_reviews('tripadvisor', location_id, 3) %}...{% endfor %}
 *     <a href="{{ omnireview_write_url('google', place_id) }}">Leave a review</a>
 *
 * Kept in the application's cache (symfony/cache-contracts) no longer than
 * the platform allows (Terms::$cacheFor): not at all for Google and
 * Tripadvisor, a day for Trustpilot. A platform that does not answer shows
 * nothing rather than an error.
 */
final class OmnireviewExtension extends AbstractExtension
{
    public function __construct(
        private readonly Registry $registry,
        private readonly ?CacheInterface $cache = null,
        private readonly int $ttl = 3600,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('omnireview_rating', $this->rating(...)),
            new TwigFunction('omnireview_reviews', $this->reviews(...)),
            new TwigFunction('omnireview_terms', $this->terms(...)),
            new TwigFunction('omnireview_write_url', $this->writeUrl(...)),
            new TwigFunction('omnireview_widget', $this->widget(...), ['needs_environment' => true, 'is_safe' => ['html']]),
        ];
    }

    public function rating(string $gateway, Place|string $place): ?Rating
    {
        $place = \is_string($place) ? new Place($place) : $place;

        return $this->kept($gateway, 'rating:'.$place->id, fn () => $this->registry->rating($gateway)->rating($place));
    }

    /** @return list<Review> */
    public function reviews(string $gateway, Place|string $place, int $limit = 5, ?string $language = null): array
    {
        $place = \is_string($place) ? new Place($place) : $place;

        return $this->kept($gateway, 'reviews:'.$place->id.':'.$limit.':'.$language, fn () => $this->registry->reviews($gateway)->reviews($place, $limit, $language)) ?? [];
    }

    public function terms(string $gateway): Terms
    {
        return $this->registry->get($gateway)->terms();
    }

    /** null when the platform, which Tripadvisor and Trustpilot ask for the link, does not answer. */
    public function writeUrl(string $gateway, Place|string $place): ?string
    {
        $place = \is_string($place) ? new Place($place) : $place;
        try {
            return $this->registry->writeUrl($gateway)->writeUrl($place);
        } catch (UnreachableException) {
            return null;
        }
    }

    public function widget(Environment $twig, string $gateway, Place|string $place, int $limit = 3, ?string $language = null): string
    {
        $capabilities = $this->registry->get($gateway)->capabilities();

        return $twig->render('@Omnireview/reviews.html.twig', [
            'gateway' => $this->registry->get($gateway),
            'terms' => $this->terms($gateway),
            'rating' => $capabilities->rating ? $this->rating($gateway, $place) : null,
            'reviews' => $capabilities->reviews ? $this->reviews($gateway, $place, $limit, $language) : [],
            'write_url' => $capabilities->writeUrl ? $this->writeUrl($gateway, $place) : null,
        ]);
    }

    /**
     * @template T
     *
     * @param \Closure(): T $fetch
     *
     * @return T|null
     */
    private function kept(string $gateway, string $key, \Closure $fetch): mixed
    {
        $allowed = $this->registry->get($gateway)->terms()->cacheFor;
        $ttl = null === $allowed ? 0 : min($this->ttl, $allowed);
        try {
            if (null === $this->cache || $ttl <= 0) {
                return $fetch();
            }

            return $this->cache->get('omnireview.'.hash('xxh128', $gateway.'|'.$key), static function (ItemInterface $item) use ($fetch, $ttl) {
                $item->expiresAfter($ttl);

                return $fetch();
            });
        } catch (UnreachableException) {
            return null;
        }
    }
}
