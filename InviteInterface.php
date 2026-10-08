<?php

namespace Omnireview;

use Omnireview\Model\Invitation;
use Omnireview\Model\Place;

/** Inviting a customer to leave a review, by the platform's own e-mail. */
interface InviteInterface extends GatewayInterface
{
    /** @return string|null the platform's reference for the invitation, when it gives one */
    public function invite(Place $place, Invitation $invitation): ?string;
}
