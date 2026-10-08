<?php

namespace Omnireview;

use Omnireview\Model\Place;

/** Where a customer leaves a review: a link to give them - a button, an e-mail, a receipt. */
interface WriteUrlInterface extends GatewayInterface
{
    public function writeUrl(Place $place): string;
}
