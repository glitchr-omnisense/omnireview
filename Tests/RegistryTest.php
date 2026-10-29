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

    /** Trustpilot without a business user implements ReplyInterface, but does not reply as configured: its capabilities say so. */
    public function testAGetterRefusesWhatTheGatewayDoesNotGiveAsConfigured(): void
    {
        $gateway = new class implements \Omnireview\RatingInterface, \Omnireview\ReplyInterface {
            public function getName(): string
            {
                return 'trustpilot';
            }

            public function getTitle(): string
            {
                return 'Trustpilot';
            }

            public function capabilities(): Capabilities
            {
                return new Capabilities(rating: true, reply: false);
            }

            public function terms(): Terms
            {
                return new Terms();
            }

            public function rating(\Omnireview\Model\Place $place): Rating
            {
                return new Rating('trustpilot', 4.5, 10);
            }

            public function reply(\Omnireview\Model\Place $place, \Omnireview\Model\Review $review, string $text): \Omnireview\Model\Reply
            {
                throw NotSupportedException::operation('trustpilot', 'reply');
            }
        };
        $factory = new class($gateway) implements \Omnireview\GatewayFactoryInterface {
            public function __construct(private readonly \Omnireview\GatewayInterface $gateway)
            {
            }

            public function getName(): string
            {
                return 'trustpilot';
            }

            public function create(array $options = []): \Omnireview\GatewayInterface
            {
                return $this->gateway;
            }
        };
        $registry = new Registry([$factory], ['trustpilot' => ['factory' => 'trustpilot']]);

        self::assertSame($gateway, $registry->rating('trustpilot'));
        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage('The "trustpilot" gateway (Trustpilot) does not give replying to a review; it gives: rating.');
        $registry->replies('trustpilot');
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
