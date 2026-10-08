<?php

namespace Omnireview;

use Omnireview\Exception\InvalidConfigException;
use Omnireview\Exception\NotSupportedException;

/**
 * The application's gateways by name, each built once from its factory and
 * options:
 *
 *   new Registry([new GoogleGatewayFactory($http), new TrustpilotGatewayFactory($http)], [
 *       'google' => ['factory' => 'google', 'options' => ['api_key' => '...']],
 *       'trustpilot' => ['factory' => 'trustpilot', 'options' => ['api_key' => '...']],
 *   ]);
 *
 *   $registry->rating('google')->rating(new Place('ChIJ...'));
 */
final class Registry
{
    /** @var array<string, GatewayFactoryInterface> */
    private array $factories = [];

    /** @var array<string, GatewayInterface> */
    private array $gateways = [];

    /**
     * @param iterable<GatewayFactoryInterface>                                      $factories
     * @param array<string, array{factory: string, options?: array<string, mixed>}> $config
     */
    public function __construct(iterable $factories, private readonly array $config)
    {
        foreach ($factories as $factory) {
            $this->factories[$factory->getName()] = $factory;
        }
    }

    public function get(string $name): GatewayInterface
    {
        return $this->gateways[$name] ??= $this->factory($name)->create($this->config[$name]['options'] ?? []);
    }

    public function has(string $name): bool
    {
        return isset($this->config[$name]);
    }

    /** The gateway, as what gives its rating. */
    public function rating(string $name): RatingInterface
    {
        $gateway = $this->get($name);

        return $gateway instanceof RatingInterface ? $gateway : throw NotSupportedException::question($gateway, $name, 'its rating');
    }

    /** The gateway, as what gives its reviews. */
    public function reviews(string $name): ReviewsInterface
    {
        $gateway = $this->get($name);

        return $gateway instanceof ReviewsInterface ? $gateway : throw NotSupportedException::question($gateway, $name, 'its reviews');
    }

    /** The gateway, as what gives replying to a review. */
    public function replies(string $name): ReplyInterface
    {
        $gateway = $this->get($name);

        return $gateway instanceof ReplyInterface ? $gateway : throw NotSupportedException::question($gateway, $name, 'replying to a review');
    }

    /** The gateway, as what gives inviting a customer to review. */
    public function invitations(string $name): InviteInterface
    {
        $gateway = $this->get($name);

        return $gateway instanceof InviteInterface ? $gateway : throw NotSupportedException::question($gateway, $name, 'inviting a customer to review');
    }

    /** The gateway, as what gives the address to leave a review. */
    public function writeUrl(string $name): WriteUrlInterface
    {
        $gateway = $this->get($name);

        return $gateway instanceof WriteUrlInterface ? $gateway : throw NotSupportedException::question($gateway, $name, 'the address to leave a review');
    }

    /** @return array<string, mixed> the options a gateway is configured with */
    public function options(string $name): array
    {
        return $this->config[$name]['options'] ?? [];
    }

    /**
     * A gateway built afresh, its configured options with $overrides over
     * them - a key typed in a back office, say. Not kept: get() still gives
     * the configured one.
     *
     * @param array<string, mixed> $overrides
     */
    public function create(string $name, array $overrides = []): GatewayInterface
    {
        return $this->factory($name)->create(array_replace($this->config[$name]['options'] ?? [], $overrides));
    }

    /** @return list<string> the configured gateways' names */
    public function names(): array
    {
        return array_keys($this->config);
    }

    /** @return array<string, GatewayInterface> */
    public function all(): array
    {
        $all = [];
        foreach (array_keys($this->config) as $name) {
            $all[$name] = $this->get($name);
        }

        return $all;
    }

    /** @return string[] the factories installed: what `factory:` may name */
    public function factories(): array
    {
        return array_keys($this->factories);
    }

    private function factory(string $name): GatewayFactoryInterface
    {
        $gateway = $this->config[$name] ?? throw new InvalidConfigException(\sprintf('No "%s" gateway; configured: %s.', $name, implode(', ', array_keys($this->config)) ?: 'none'));

        return $this->factories[$gateway['factory']] ?? throw new InvalidConfigException(\sprintf('No "%s" factory for the "%s" gateway; installed: %s.', $gateway['factory'], $name, implode(', ', array_keys($this->factories)) ?: 'none'));
    }
}
