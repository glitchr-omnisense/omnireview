<?php

namespace Omnireview\Tests\Bridge;

use Omnireview\Bridge\Symfony\OmnireviewBundle;
use Omnireview\GatewayInterface;
use Omnireview\Model\Place;
use Omnireview\RatingInterface;
use Omnireview\Registry;
use Omnireview\Tests\StubFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpClient\MockHttpClient;

final class OmnireviewBundleTest extends TestCase
{
    public function testTheGatewaysConfiguredAreBuiltAndInjectableByName(): void
    {
        $container = new ContainerBuilder();
        $container->register('http_client', MockHttpClient::class);
        $container->register(StubFactory::class)->setAutoconfigured(true);
        $container->register(Restaurant::class)->setAutowired(true)->setPublic(true);
        $container->registerExtension((new OmnireviewBundle())->getContainerExtension());
        $container->loadFromExtension('omnireview', ['gateways' => [
            'platform' => ['factory' => 'stub', 'options' => ['host' => 'reviews.example']],
            'other' => ['factory' => 'stub', 'options' => ['host' => 'other.example']],
        ]]);
        $container->compile();

        $registry = $container->get(Registry::class);
        self::assertSame(['platform', 'other'], array_keys($registry->all()));
        $restaurant = $container->get(Restaurant::class);
        self::assertSame('https://reviews.example/p1', $restaurant->platform->rating(new Place('p1'))->url);
        self::assertSame('https://other.example/p1', $restaurant->other->rating(new Place('p1'))->url);
    }

    public function testThePackagesInstalledAreRegisteredWithTheApplicationsClient(): void
    {
        if (!class_exists(\Omnireview\Google\GoogleGatewayFactory::class)) {
            self::markTestSkipped('omnireview/google is not installed.');
        }
        $container = new ContainerBuilder();
        $container->register('http_client', MockHttpClient::class);
        $container->registerExtension((new OmnireviewBundle())->getContainerExtension());
        $container->loadFromExtension('omnireview', ['gateways' => ['platform' => ['factory' => 'google', 'options' => ['api_key' => 'k']]]]);
        $container->compile();

        $registry = $container->get(Registry::class);
        self::assertContains('google', $registry->factories());
        self::assertSame(0, $registry->get('platform')->terms()->cacheFor);
    }
}

final class Restaurant
{
    public function __construct(public readonly RatingInterface $platform, public readonly RatingInterface $other)
    {
    }
}
