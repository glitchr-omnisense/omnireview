<?php

namespace Omnireview\Exception;

/**
 * The platform did not answer: no connection, a timeout, a server error, a
 * quota exceeded. Show nothing rather than something stale beyond what the
 * platform allows.
 */
final class UnreachableException extends ProviderException
{
}
