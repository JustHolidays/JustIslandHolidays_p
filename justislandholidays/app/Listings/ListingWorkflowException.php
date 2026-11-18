<?php

namespace App\Listings;

use RuntimeException;

/** A status change that is not allowed. The message is plain English, safe to show to the user. */
class ListingWorkflowException extends RuntimeException
{
    /** @param list<string> $problems */
    public function __construct(string $message, public readonly array $problems = [])
    {
        parent::__construct($message);
    }
}
