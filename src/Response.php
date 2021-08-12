<?php

declare(strict_types=1);

namespace App;


/**
 * Class to hold the properties of an HTTP response and decode the next Uri in the Link header
 */
class Response
{
    protected $statusCode;
    protected $headers;
    protected $body;

    public function __construct(int $statusCode, array $headers, string $body)
    {
        $this->statusCode = $statusCode;
        $this->headers = $headers;
        $this->body = $body;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getNextLink(): ?string
    {
        $linkHeader = $this->headers['link'] ?? null;
        if (!$linkHeader) {
            return null;
        }

        foreach (explode(', ', $linkHeader) as $link) {
            $matches = array ();

            if (preg_match('#<(?P<url>.*)>; rel="next"#', $link, $matches)) {
                return $matches['url'];
            }
        }

        return null;
    }
}
