<?php

namespace Omnireview\Exception;

/**
 * The platform answered with an error rather than what was asked: a place
 * it does not know, a request it could not read.
 */
class ProviderException extends \RuntimeException implements OmnireviewException
{
    public function __construct(
        public readonly string $provider,
        string $message,
        /** The provider's own error code, when it gave one */
        public readonly ?string $providerCode = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(\sprintf('[%s] %s', $provider, $message), 0, $previous);
    }
}
