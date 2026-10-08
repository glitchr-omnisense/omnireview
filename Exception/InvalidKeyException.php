<?php

namespace Omnireview\Exception;

/**
 * The platform refused the site's key, token or account: the configuration
 * is wrong, or the access was never granted (a Business Profile's owner).
 */
final class InvalidKeyException extends ProviderException
{
}
