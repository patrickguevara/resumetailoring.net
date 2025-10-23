<?php

namespace App\Services;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use DOMXPath;
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
        if (! Str::startsWith(strtolower($url), ['http://', 'https://'])) {
            throw new RuntimeException('Job description must be provided manually for this job.');
        }

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
            $candidates = [];
            $cleaned = $this->cleanHtml($body);

            if ($cleaned !== '') {
                $candidates[] = $cleaned;
            }

            $structured = $this->extractJobDescriptionFromJsonLd($body);

            if ($structured !== null && $structured !== '') {
                $candidates[] = $structured;
            }

            if ($cleaned === '' || mb_strlen($cleaned) < 200) {
                // Some sites render minimal semantic markup or rely on client-side rendering
                // that the DOM parser cannot infer. Fall back to a plainer extraction.
                $fallback = $this->sanitizeExtractedText(
                    $this->collapseWhitespace(strip_tags($body))
                );

                if ($fallback !== '') {
                    $candidates[] = $fallback;
                }
            }

            $text = $this->chooseBestDescription($candidates);
        } else {
            $text = $this->sanitizeExtractedText(
                $this->collapseWhitespace($body)
            );
        }

        if ($text === '') {
            throw new RuntimeException('Job description appears to be empty.');
        }

        return $text;
    }

    private function cleanHtml(string $html): string
    {
        $document = $this->createDocument($html);

        $this->removeNoise($document);
        $this->removeHiddenElements($document);

        $root = $this->locateContentRoot($document);

        if (! $root instanceof DOMNode) {
            return '';
        }

        $markdown = $this->renderBlockChildren($root);

        $markdown = preg_replace("/\n{3,}/u", "\n\n", $markdown ?? '') ?? '';

        return trim($markdown);
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

    private function createDocument(string $html): DOMDocument
    {
        $document = new DOMDocument();

        libxml_use_internal_errors(true);

        $encoded = $this->prepareHtml($html);
        $document->loadHTML($encoded, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);

        libxml_clear_errors();

        return $document;
    }

    private function prepareHtml(string $html): string
    {
        $encoding = mb_detect_encoding($html, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true) ?: 'UTF-8';

        if ($encoding !== 'UTF-8') {
            $html = mb_convert_encoding($html, 'UTF-8', $encoding);
        }

        if (! str_contains($html, '<?xml')) {
            return '<?xml encoding="utf-8" ?>' . $html;
        }

        return $html;
    }

    private function removeNoise(DOMDocument $document): void
    {
        $xpath = new DOMXPath($document);

        $this->removeNodes($xpath, '//script|//style|//noscript|//template|//svg|//iframe|//canvas');
        $this->removeNodes($xpath, '//form|//fieldset|//legend');
        $this->removeNodes($xpath, '//input|//select|//textarea|//button|//label');
        $this->removeNodes($xpath, '//meta|//link|//base');
    }

    private function removeHiddenElements(DOMDocument $document): void
    {
        $xpath = new DOMXPath($document);

        $this->removeNodes($xpath, '//*[@hidden]');
        $this->removeNodes($xpath, '//*[@aria-hidden="true"]');
        $this->removeNodes($xpath, '//*[contains(concat(" ", normalize-space(@class), " "), " sr-only ")]');
        $this->removeNodes($xpath, '//*[contains(concat(" ", normalize-space(@class), " "), " visually-hidden ")]');
        $this->removeNodes($xpath, '//*[contains(concat(" ", normalize-space(@class), " "), " screen-reader-text ")]');
        $this->removeNodes($xpath, '//*[contains(concat(" ", normalize-space(@class), " "), " hidden ")]');

        foreach ($xpath->query('//*[@style]') as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $style = strtolower($node->getAttribute('style'));

            if (
                str_contains($style, 'display:none')
                || str_contains($style, 'visibility:hidden')
                || str_contains($style, 'opacity:0')
            ) {
                $node->parentNode?->removeChild($node);
            }
        }
    }

    private function removeNodes(DOMXPath $xpath, string $expression): void
    {
        foreach ($xpath->query($expression) as $node) {
            $node->parentNode?->removeChild($node);
        }
    }

    private function extractJobDescriptionFromJsonLd(string $html): ?string
    {
        if (! preg_match_all(
            '/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is',
            $html,
            $matches
        )) {
            return null;
        }

        foreach ($matches[1] as $rawPayload) {
            $payload = html_entity_decode(trim($rawPayload), ENT_QUOTES | ENT_HTML5, 'UTF-8');

            if ($payload === '') {
                continue;
            }

            foreach ($this->decodeJsonLdPayloads($payload) as $data) {
                $description = $this->findJobPostingDescription($data);

                if (is_string($description) && $description !== '') {
                    $markdown = $this->convertHtmlFragmentToMarkdown($description);

                    if ($markdown !== '') {
                        return $markdown;
                    }
                }
            }
        }

        return null;
    }

    /**
     * @param array<int, string> $candidates
     */
    private function chooseBestDescription(array $candidates): string
    {
        $best = '';
        $bestScore = PHP_FLOAT_MIN;

        foreach ($candidates as $candidate) {
            if (! is_string($candidate)) {
                continue;
            }

            $candidate = trim($candidate);

            if ($candidate === '') {
                continue;
            }

            $score = $this->scoreDescriptionCandidate($candidate);

            if ($score > $bestScore) {
                $best = $candidate;
                $bestScore = $score;
            }
        }

        return $best;
    }

    private function scoreDescriptionCandidate(string $candidate): float
    {
        $length = mb_strlen($candidate);
        $lineBreaks = substr_count($candidate, "\n");
        $listMarkers = substr_count($candidate, '- ') + substr_count($candidate, '* ');
        $headingMarkers = substr_count($candidate, '#');
        $linkMarkers = substr_count($candidate, '[');

        return $length
            + ($lineBreaks * 1.5)
            + ($listMarkers * 20)
            + ($headingMarkers * 10)
            + ($linkMarkers * 5);
    }

    /**
     * @return array<int, mixed>
     */
    private function decodeJsonLdPayloads(string $payload): array
    {
        $payloads = [];

        try {
            $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
            if ($decoded !== null) {
                $payloads[] = $decoded;
            }
        } catch (\JsonException) {
            return [];
        }

        return $payloads;
    }

    /**
     * @param mixed $data
     */
    private function findJobPostingDescription($data): ?string
    {
        if (! is_array($data)) {
            return null;
        }

        if ($this->isJobPostingType($data) && isset($data['description']) && is_string($data['description'])) {
            $description = trim($data['description']);

            return $description === '' ? null : $description;
        }

        foreach ($data as $value) {
            $nested = $this->findJobPostingDescription($value);

            if ($nested !== null && $nested !== '') {
                return $nested;
            }
        }

        return null;
    }

    private function isJobPostingType(array $data): bool
    {
        if (! array_key_exists('@type', $data)) {
            return false;
        }

        $type = $data['@type'];

        if (is_string($type)) {
            return strtolower($type) === 'jobposting';
        }

        if (is_array($type)) {
            foreach ($type as $value) {
                if (is_string($value) && strtolower($value) === 'jobposting') {
                    return true;
                }
            }
        }

        return false;
    }

    private function convertHtmlFragmentToMarkdown(string $html): string
    {
        $html = str_replace("\u{00A0}", ' ', $html);
        $document = $this->createDocument('<html><body>' . $html . '</body></html>');
        $body = $document->getElementsByTagName('body')->item(0);

        if (! $body instanceof DOMNode) {
            return '';
        }

        $markdown = $this->renderBlockChildren($body);
        $markdown = preg_replace("/\n{3,}/u", "\n\n", $markdown ?? '') ?? '';

        return trim($markdown);
    }

    private function locateContentRoot(DOMDocument $document): ?DOMNode
    {
        $xpath = new DOMXPath($document);

        $candidates = [
            '//main[1]',
            '//*[@role="main"][1]',
            '//*[@id="jobDescription" or @id="job-description"][1]',
            '//*[contains(concat(" ", normalize-space(@class), " "), " job-description ")][1]',
            '//article[1]',
        ];

        foreach ($candidates as $expression) {
            $node = $xpath->query($expression)->item(0);

            if ($node instanceof DOMElement && ! $this->isNoiseContainer($node)) {
                return $node;
            }
        }

        $bestNode = null;
        $bestScore = 0;

        foreach ($xpath->query('//article|//section|//div') as $candidate) {
            if (! $candidate instanceof DOMElement || $this->isNoiseContainer($candidate)) {
                continue;
            }

            $text = trim($candidate->textContent ?? '');
            $score = mb_strlen($text);

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestNode = $candidate;
            }
        }

        if ($bestNode instanceof DOMNode && $bestScore >= 200) {
            return $bestNode;
        }

        return $document->getElementsByTagName('body')->item(0);
    }

    private function renderBlockChildren(DOMNode $node, int $listDepth = 0): string
    {
        $blocks = [];

        foreach ($node->childNodes as $child) {
            $block = $this->renderBlock($child, $listDepth);

            if ($block !== null) {
                $trimmed = trim($block);

                if ($trimmed !== '') {
                    $blocks[] = $trimmed;
                }
            }
        }

        return implode("\n\n", $blocks);
    }

    private function renderBlock(DOMNode $node, int $listDepth = 0): ?string
    {
        if ($node instanceof DOMText) {
            $text = $this->normalizeInlineText($node->nodeValue);

            return trim($text) === '' ? null : trim($text);
        }

        if (! $node instanceof DOMElement) {
            return null;
        }

        $tag = strtolower($node->tagName);

        return match ($tag) {
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6' => $this->renderHeading($node),
            'p' => $this->renderParagraph($node),
            'ul' => $this->renderList($node, $listDepth, false),
            'ol' => $this->renderList($node, $listDepth, true),
            'blockquote' => $this->renderBlockquote($node, $listDepth),
            'pre' => $this->renderPreformatted($node),
            'table' => $this->renderTable($node),
            'hr' => '---',
            'br' => null,
            default => $this->renderDefaultBlock($node, $listDepth),
        };
    }

    private function renderHeading(DOMElement $element): ?string
    {
        $level = (int) substr($element->tagName, 1);
        $level = max(1, min(6, $level));
        $content = $this->renderInlineChildren($element);

        if ($content === '') {
            return null;
        }

        return str_repeat('#', $level) . ' ' . $content;
    }

    private function renderParagraph(DOMElement $element): ?string
    {
        $content = $this->renderInlineChildren($element);

        return $content === '' ? null : $content;
    }

    private function renderList(DOMElement $element, int $depth, bool $ordered): ?string
    {
        $items = [];
        $index = 1;

        foreach ($element->childNodes as $child) {
            if (! $child instanceof DOMElement || strtolower($child->tagName) !== 'li') {
                continue;
            }

            $item = $this->renderListItem($child, $depth, $ordered, $index);

            if ($item !== null && $item !== '') {
                $items[] = $item;
            }

            if ($ordered) {
                $index++;
            }
        }

        $list = implode("\n", $items);

        return $list === '' ? null : $list;
    }

    private function renderListItem(DOMElement $element, int $depth, bool $ordered, int $index): ?string
    {
        $marker = $ordered ? sprintf('%d.', $index) : '-';
        $indent = str_repeat('  ', $depth);

        $content = $this->renderInlineChildren($element);
        $line = $content === ''
            ? sprintf('%s%s', $indent, $marker)
            : sprintf('%s%s %s', $indent, $marker, $content);

        $lines = [$line];

        foreach ($element->childNodes as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            $childTag = strtolower($child->tagName);

            if (in_array($childTag, ['ul', 'ol'], true)) {
                $nested = $this->renderList($child, $depth + 1, $childTag === 'ol');

                if ($nested !== null && $nested !== '') {
                    $lines[] = $nested;
                }
            }
        }

        return implode("\n", array_filter($lines));
    }

    private function renderBlockquote(DOMElement $element, int $listDepth): ?string
    {
        $content = $this->renderBlockChildren($element, $listDepth);

        if ($content === '') {
            return null;
        }

        $lines = preg_split("/\r\n|\r|\n/", $content) ?: [];
        $quoted = array_map(static fn ($line) => '> ' . ltrim($line), $lines);

        return implode("\n", $quoted);
    }

    private function renderPreformatted(DOMElement $element): ?string
    {
        $text = '';

        foreach ($element->childNodes as $child) {
            if ($child instanceof DOMText) {
                $text .= $child->nodeValue;
            }
        }

        if ($text === '') {
            return null;
        }

        $trimmed = rtrim($text, "\n");

        return "```\n" . $trimmed . "\n```";
    }

    private function renderTable(DOMElement $element): ?string
    {
        $rows = [];

        foreach ($element->getElementsByTagName('tr') as $row) {
            $cells = [];

            foreach ($row->childNodes as $cell) {
                if ($cell instanceof DOMElement && in_array(strtolower($cell->tagName), ['th', 'td'], true)) {
                    $cells[] = $this->renderInlineChildren($cell);
                }
            }

            if (! empty($cells)) {
                $rows[] = '| ' . implode(' | ', $cells) . ' |';
            }
        }

        if (empty($rows)) {
            return null;
        }

        $header = $rows[0];
        $columnCount = substr_count($header, '|') - 1;
        $separatorCells = array_fill(0, max(0, $columnCount), '---');
        $separator = '| ' . implode(' | ', $separatorCells) . ' |';

        if ($columnCount > 0) {
            array_splice($rows, 1, 0, [$separator]);
        }

        return implode("\n", $rows);
    }

    private function renderDefaultBlock(DOMElement $element, int $listDepth): ?string
    {
        if ($element->hasChildNodes()) {
            return $this->renderBlockChildren($element, $listDepth);
        }

        $content = $this->renderInlineChildren($element);

        return $content === '' ? null : $content;
    }

    private function renderInlineChildren(DOMNode $node): string
    {
        $parts = [];

        foreach ($node->childNodes as $child) {
            $parts[] = $this->renderInline($child);
        }

        $content = implode('', $parts);
        $content = preg_replace('/[ \t]+/u', ' ', $content ?? '') ?? '';
        $content = preg_replace('/ *\n */u', "\n", $content) ?? '';
        $content = preg_replace("/\n{3,}/u", "\n\n", $content) ?? '';

        return trim($content);
    }

    private function renderInline(DOMNode $node): string
    {
        if ($node instanceof DOMText) {
            return $this->normalizeInlineText($node->nodeValue);
        }

        if (! $node instanceof DOMElement) {
            return '';
        }

        $tag = strtolower($node->tagName);

        return match ($tag) {
            'strong', 'b' => $this->wrapInline('**', $node),
            'em', 'i' => $this->wrapInline('_', $node),
            'code' => $this->wrapInline('`', $node),
            'a' => $this->renderAnchor($node),
            'br' => "\n",
            'span', 'u', 'small', 'sup', 'sub' => $this->renderInlineChildren($node),
            default => $this->renderInlineChildren($node),
        };
    }

    private function wrapInline(string $wrapper, DOMElement $element): string
    {
        $content = $this->renderInlineChildren($element);

        if ($content === '') {
            return '';
        }

        return $wrapper . $content . $wrapper;
    }

    private function renderAnchor(DOMElement $element): string
    {
        $content = $this->renderInlineChildren($element);
        $href = trim($element->getAttribute('href'));

        if ($href === '') {
            return $content;
        }

        if ($content === '') {
            $content = $href;
        }

        return '[' . $content . '](' . $href . ')';
    }

    private function normalizeInlineText(?string $text): string
    {
        if ($text === null) {
            return '';
        }

        return $this->collapseWhitespace($text);
    }

    private function isNoiseContainer(DOMElement $element): bool
    {
        $tag = strtolower($element->tagName);

        if (in_array($tag, ['nav', 'footer', 'header', 'form'], true)) {
            return true;
        }

        $class = strtolower($element->getAttribute('class'));

        if ($class !== '') {
            $noiseClasses = [
                'footer',
                'header',
                'nav',
                'breadcrumb',
                'breadcrumbs',
                'menu',
                'sidebar',
                'subscribe',
                'newsletter',
                'cookie',
                'consent',
                'modal',
            ];

            foreach ($noiseClasses as $needle) {
                if (str_contains($class, $needle)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function sanitizeExtractedText(string $text): string
    {
        $lines = preg_split("/\r\n|\r|\n/", $text) ?: [];
        $filtered = [];
        $cssDepth = 0;
        $jsonDepth = 0;

        foreach ($lines as $line) {
            $trim = trim($line);

            if ($trim === '') {
                if (end($filtered) !== '') {
                    $filtered[] = '';
                }

                continue;
            }

            if ($cssDepth > 0) {
                $cssDepth += substr_count($trim, '{');
                $cssDepth -= substr_count($trim, '}');

                if ($cssDepth <= 0) {
                    $cssDepth = 0;
                }

                continue;
            }

            if ($jsonDepth > 0) {
                $jsonDepth += substr_count($trim, '{');
                $jsonDepth -= substr_count($trim, '}');

                if ($jsonDepth <= 0) {
                    $jsonDepth = 0;
                }

                continue;
            }

            if ($this->startsCssBlock($trim)) {
                $cssDepth = 1;
                $cssDepth += substr_count($trim, '{') - substr_count($trim, '}');

                if ($cssDepth < 0) {
                    $cssDepth = 0;
                }

                continue;
            }

            if ($this->startsJsonBlock($trim)) {
                $jsonDepth = 1;
                $jsonDepth += substr_count($trim, '{') - substr_count($trim, '}');

                if ($jsonDepth <= 0) {
                    $jsonDepth = 0;
                }

                continue;
            }

            if ($this->looksLikeJsonLine($trim) || $this->looksLikeCssLine($trim)) {
                continue;
            }

            $filtered[] = $line;
        }

        $output = implode("\n", $filtered);
        $output = preg_replace("/\n{3,}/u", "\n\n", $output ?? '') ?? '';

        return trim($output);
    }

    private function startsCssBlock(string $line): bool
    {
        if (! str_contains($line, '{')) {
            return false;
        }

        return (bool) preg_match('/^[\w\.\#][^{]{0,200}\{\s*$/u', $line);
    }

    private function startsJsonBlock(string $line): bool
    {
        if ($line === '{' || $line === '[') {
            return true;
        }

        return str_starts_with($line, '{') && ! str_contains($line, '}');
    }

    private function looksLikeJsonLine(string $line): bool
    {
        if (str_starts_with($line, '"@context"')) {
            return true;
        }

        if (str_starts_with($line, '"@type"')) {
            return true;
        }

        return (bool) preg_match('/^"\w[^"]*":/u', $line);
    }

    private function looksLikeCssLine(string $line): bool
    {
        if (str_contains($line, '{') || str_contains($line, '}')) {
            return true;
        }

        if (! str_contains($line, ':') || ! str_contains($line, ';')) {
            return false;
        }

        return (bool) preg_match('/^[\w\-]+\s*:\s*[^;]+;$/u', $line);
    }

    private function collapseWhitespace(string $value): string
    {
        $normalized = preg_replace("/\r\n|\r/", "\n", $value) ?? $value;
        $normalized = preg_replace('/[ \t]+/u', ' ', $normalized) ?? $normalized;

        return $normalized;
    }
}
