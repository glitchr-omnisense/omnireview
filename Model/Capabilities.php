<?php

namespace Omnireview\Model;

/** What a gateway gives: the interfaces it implements, as configured (a reply needs the owner's access). */
final readonly class Capabilities
{
    public function __construct(
        public bool $rating = false,
        public bool $reviews = false,
        public bool $reply = false,
        public bool $invite = false,
        public bool $writeUrl = false,
    ) {
    }

    /** @return list<string> */
    public function gives(): array
    {
        return array_keys(array_filter(['rating' => $this->rating, 'reviews' => $this->reviews, 'reply' => $this->reply, 'invite' => $this->invite, 'write-url' => $this->writeUrl]));
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['gives' => $this->gives()] + get_object_vars($this);
    }
}
