<?php

namespace Omnireview\Exception;

use Omnireview\GatewayInterface;

/**
 * The gateway does not do that: no reply on Tripadvisor's Content API, no
 * invitation without a business account. Its Capabilities say so
 * beforehand.
 */
final class NotSupportedException extends \LogicException implements OmnireviewException
{
    public static function question(GatewayInterface $gateway, string $name, string $question): self
    {
        return new self(\sprintf('The "%s" gateway (%s) does not give %s; it gives: %s.', $name, $gateway->getTitle(), $question, implode(', ', $gateway->capabilities()->gives()) ?: 'nothing'));
    }

    public static function operation(string $gateway, string $operation, string $why = ''): self
    {
        return new self(\sprintf('The "%s" gateway does not %s%s.', $gateway, $operation, '' !== $why ? ': '.$why : ''));
    }
}
