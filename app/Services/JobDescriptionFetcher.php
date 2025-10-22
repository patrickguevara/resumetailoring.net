<?php

namespace App\Services;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class JobDescriptionFetcher
{
    /**
     * Fetch a job description from a remote URL, closely matching the behaviour of the
     * reference Python script.
     *
     * @throws RuntimeException
     */
    public function fetch(string $url): string
    {
        try {
            $response = Http::withHeaders($this->requestHeaders())
                ->timeout(30)
                ->get($url)
                ->throw();
        } catch (RequestException $exception) {
            throw new RuntimeException(
                sprintf('Failed to retrieve job description: %s', $exception->getMessage()),
                previous: $exception
            );
        }

        $original = $this->parseUrl($url);
        $resolved = $this->parseUrl($response->effectiveUri());

        if (
            ! empty($original['query'])
            && empty($resolved['query'])
            && ($resolved['path'] ?? null) === ($original['path'] ?? null)
        ) {
            throw new RuntimeException(
                'The site redirected to a generic careers page. Some job boards rely on query '
                . 'parameters that are stripped when accessed without a browser session. Try using '
                . "a direct job posting link (e.g., a 'View job' or 'Print view' URL) or copy the job "
                . 'description text and paste it manually.'
            );
        }

        $contentType = $response->header('content-type', '');
        $body = $response->body();

        if (Str::contains($contentType, 'text/html')) {
            $text = $this->cleanHtml($body);
        } else {
            $text = trim($body);
        }

        if ($text === '') {
            throw new RuntimeException('Job description appears to be empty.');
        }

        return $text;
    }

    private function cleanHtml(string $html): string
    {
        $stripped = strip_tags($html);
        $compressed = preg_replace('/\s+/u', ' ', $stripped ?? '') ?? '';

        return trim($compressed);
    }

    /**
     * Normalize URI details, even if we receive a string or Uri object.
     *
     * @param string|\Psr\Http\Message\UriInterface $uri
     *
     * @return array<string, string>
     */
    private function parseUrl($uri): array
    {
        $value = (string) $uri;

        return array_filter(parse_url($value) ?: [], static fn ($item) => $item !== null);
    }

    /**
     * Request headers mimic a real browser to reduce bot blocking.
     *
     * @return array<string, string>
     */
    private function requestHeaders(): array
    {
        return [
            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) '
                . 'AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language' => 'en-US,en;q=0.9',
        ];
    }
}
