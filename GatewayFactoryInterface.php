<?php

namespace Omnireview;

/** Builds a gateway from its options (an API key, an account, a locale...). */
interface GatewayFactoryInterface
{
    /** The name gateways are configured with: "google", "tripadvisor", "trustpilot"... */
    public function getName(): string;

    /** @param array<string, mixed> $options */
    public function create(array $options = []): GatewayInterface;
}
