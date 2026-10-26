<?php

/**
 * Every gateway package: its slug (omnireview/<slug>, github.com/glitchr-omnisense/omnireview-<slug>),
 * its tests' namespace and its factory class.
 */
return [
    'google' => ['Omnireview\\Google\\Tests\\', 'Omnireview\\Google\\GoogleGatewayFactory'],
    'tripadvisor' => ['Omnireview\\Tripadvisor\\Tests\\', 'Omnireview\\Tripadvisor\\TripadvisorGatewayFactory'],
    'trustpilot' => ['Omnireview\\Trustpilot\\Tests\\', 'Omnireview\\Trustpilot\\TrustpilotGatewayFactory'],
];
