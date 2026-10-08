<?php

namespace Omnireview\Tests;

use Omnireview\Exception\InvalidConfigException;
use Omnireview\Exception\NotSupportedException;
use Omnireview\Model\Capabilities;
use Omnireview\Model\Rating;
use Omnireview\Model\Terms;
use Omnireview\Registry;
use PHPUnit\Framework\TestCase;

final class RegistryTest extends TestCase
{
    public function testEachCapabilityIsAskedOfAGatewayThatGivesIt(): void
    {
        $registry = new Registry([new StubFactory()], ['stub' => ['factory' => 'stub', 'options' => ['host' => 'reviews.example']], 'broken' => ['factory' => 'stub']]);

        self::assertSame($registry->get('stub'), $registry->rating('stub'));
        self::assertSame('https://reviews.example/write/p1', $registry->writeUrl('stub')->writeUrl(new \Omnireview\Model\Place('p1')));
        try {
            $registry->replies('stub');
            self::fail();
        } catch (NotSupportedException $e) {
            self::assertSame('The "stub" gateway (Stub) does not give replying to a review; it gives: rating, reviews, write-url.', $e->getMessage());
        }
        $this->expectException(InvalidConfigException::class);
        $registry->get('broken');
    }

    public function testRatingsTermsAndCapabilities(): void
    {
        self::assertSame(4.3, (new Rating('x', 8.6, 42, 10))->outOfFive());
        self::assertTrue((new Terms(cacheFor: 86400))->allowsCaching(3600));
        self::assertFalse((new Terms(cacheFor: 0))->allowsCaching(1));
        self::assertFalse((new Terms(cacheFor: null))->allowsCaching(1), 'unknown: not cached');
        self::assertSame(['rating', 'invite'], (new Capabilities(rating: true, invite: true))->toArray()['gives']);
    }
}
