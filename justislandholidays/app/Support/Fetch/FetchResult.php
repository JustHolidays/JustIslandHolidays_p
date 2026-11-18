<?php

namespace App\Support\Fetch;

final class FetchResult
{
    /** @param list<string> $redirects */
    public function __construct(
        public readonly string $url,          // final address after redirects
        public readonly int $status,
        public readonly string $body,
        public readonly ?string $contentType,
        public readonly bool $truncated,
        public readonly array $redirects = [],
    ) {
    }

    public function ok(): bool
    {
        return $this->status >= 200 && $this->status < 400;
    }

    public function host(): string
    {
        return strtolower((string) parse_url($this->url, PHP_URL_HOST));
    }
}
