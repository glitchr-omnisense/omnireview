<?php

namespace Omnireview;

use Omnireview\Model\Capabilities;
use Omnireview\Model\Terms;

/**
 * One platform where customers rate and review an establishment, configured:
 * Google, Tripadvisor, Trustpilot. It gives what its interfaces say -
 * RatingInterface, ReviewsInterface, ReplyInterface, InviteInterface,
 * WriteUrlInterface -, its Capabilities list them, and its Terms say how
 * what it gives may be shown and kept.
 *
 * A gateway reads and writes on the platform. It keeps nothing: the
 * application decides what to show, for how long - within the Terms.
 */
interface GatewayInterface
{
    /** The factory's name: "google", "tripadvisor", "trustpilot"... */
    public function getName(): string;

    public function getTitle(): string;

    /** What it gives, and on what terms. */
    public function capabilities(): Capabilities;

    /** How what it gives may be shown and kept, as the platform's rules say. */
    public function terms(): Terms;
}
