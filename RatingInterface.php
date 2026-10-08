<?php

namespace Omnireview;

use Omnireview\Model\Place;
use Omnireview\Model\Rating;

/** The establishment's rating on the platform: a score, a number of reviews, the page they are on. */
interface RatingInterface extends GatewayInterface
{
    /**
     * @throws Exception\UnreachableException when the platform did not answer
     * @throws Exception\ProviderException    when it does not know the place
     */
    public function rating(Place $place): Rating;
}
