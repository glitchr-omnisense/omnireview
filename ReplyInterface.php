<?php

namespace Omnireview;

use Omnireview\Model\Place;
use Omnireview\Model\Reply;
use Omnireview\Model\Review;

/** Answering a review as the establishment: the owner's access to the platform needed. */
interface ReplyInterface extends GatewayInterface
{
    /** The reply, as published. */
    public function reply(Place $place, Review $review, string $text): Reply;
}
