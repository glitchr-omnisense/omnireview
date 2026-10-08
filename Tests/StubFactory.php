<?php

namespace Omnireview\Tests;

use Omnireview\Config;
use Omnireview\Exception\UnreachableException;
use Omnireview\GatewayFactory;
use Omnireview\GatewayInterface;
use Omnireview\Model\Capabilities;
use Omnireview\Model\Place;
use Omnireview\Model\Rating;
use Omnireview\Model\Review;
use Omnireview\Model\Terms;
use Omnireview\RatingInterface;
use Omnireview\ReviewsInterface;
use Omnireview\WriteUrlInterface;

/** A platform kept in memory, for the tests: it counts its calls; the place "down" does not answer. */
final class StubFactory extends GatewayFactory
{
    protected function populate(Config $c): void
    {
        $c->defaults(['omnireview.factory_name' => 'stub', 'omnireview.factory_title' => 'Stub', 'omnireview.required_options' => ['host'], 'cache_for' => 3600]);
    }

    protected function build(Config $c): GatewayInterface
    {
        return new StubGateway((string) $c['host'], null === $c['cache_for'] ? null : (int) $c['cache_for']);
    }
}

final class StubGateway implements RatingInterface, ReviewsInterface, WriteUrlInterface
{
    public int $calls = 0;

    public function __construct(public readonly string $host, private readonly ?int $cacheFor)
    {
    }

    public function getName(): string
    {
        return 'stub';
    }

    public function getTitle(): string
    {
        return 'Stub';
    }

    public function capabilities(): Capabilities
    {
        return new Capabilities(rating: true, reviews: true, writeUrl: true);
    }

    public function terms(): Terms
    {
        return new Terms(cacheFor: $this->cacheFor, maxReviews: 5, attribution: 'Stub Reviews');
    }

    public function rating(Place $place): Rating
    {
        ++$this->calls;
        if ('down' === $place->id) {
            throw new UnreachableException('stub', 'No answer.');
        }

        return new Rating('stub', 8.6, 42, 10, 'https://'.$this->host.'/'.$place->id);
    }

    public function reviews(Place $place, int $limit = 5, ?string $language = null): array
    {
        ++$this->calls;

        return [new Review('stub', 'r1', 5, 'Parfait <b>vraiment</b>', 'Super', 'fr', 'Camille R.', 'https://'.$this->host.'/u/camille', publishedAt: new \DateTimeImmutable('2026-09-30'), url: 'https://'.$this->host.'/r/1')];
    }

    public function writeUrl(Place $place): string
    {
        return 'https://'.$this->host.'/write/'.$place->id;
    }
}
