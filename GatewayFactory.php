<?php

namespace Omnireview;

/**
 * The Omnibus way, at its simplest: a gateway's factory fills a Config - its
 * name ("omnireview.factory_name"), its title ("omnireview.factory_title"),
 * the options it needs ("omnireview.required_options") and the defaults of
 * the others - then builds the gateway from it.
 *
 * Nothing here calls anything: a factory whose provider is reached over HTTP
 * takes its client in its own constructor - the application's, a
 * MockHttpClient in a test.
 */
abstract class GatewayFactory implements GatewayFactoryInterface
{
    public function getName(): string
    {
        return $this->createConfig()['omnireview.factory_name'];
    }

    public function create(array $options = []): GatewayInterface
    {
        $config = $this->createConfig($options);
        $config->validateNotEmpty($config->get('omnireview.required_options', []));

        return $this->build($config);
    }

    /** @param array<string, mixed> $options */
    public function createConfig(array $options = []): Config
    {
        $config = new Config($options);
        $this->populate($config);

        return $config;
    }

    /** The factory's name and title, its required options, the defaults of the others. */
    abstract protected function populate(Config $c): void;

    /** The gateway, from a Config that holds everything it needs. */
    abstract protected function build(Config $c): GatewayInterface;
}
