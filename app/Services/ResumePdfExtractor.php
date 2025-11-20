<?php

namespace App\Services;

use RuntimeException;
use Smalot\PdfParser\Parser;

class ResumePdfExtractor
{
    public function __construct(
        private readonly Parser $parser
    ) {}

    /**
     * Parse a PDF resume into normalized markdown content.
     *
     * @throws RuntimeException
     */
    public function extract(string $path): string
    {
        if (! is_file($path)) {
            throw new RuntimeException('Uploaded resume file could not be found.');
        }

        $document = $this->parser->parseFile($path);

        $text = $document->getText();
        $text = $this->normalizeWhitespace($text);
        $text = $this->mergeWrappedWords($text);

        $markdown = $this->toMarkdown($text);
        $markdown = $this->collapseBlankLines($markdown);

        $markdown = trim($markdown);

        if ($markdown === '') {
            throw new RuntimeException('Uploaded resume appears to be empty.');
        }

        return $markdown;
    }

    private function normalizeWhitespace(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        // Remove rogue null bytes and non-breaking spaces.
        $text = str_replace(["\u{0000}", "\u{00A0}"], ' ', $text);

        // Trim trailing whitespace at end of lines.
        $text = preg_replace('/[ \t]+\n/u', "\n", $text) ?? $text;

        return $text;
    }

    private function mergeWrappedWords(string $text): string
    {
        // Join words split by hyphenation at line breaks (e.g., "organisa-\ntion").
        $text = preg_replace('/(?<=\p{L})-\n(?=\p{L})/u', '', $text) ?? $text;

        // Merge hard line breaks inside sentences when the next line starts lowercase.
        $text = preg_replace('/(?<=\S)\n(?=[a-z])/mu', ' ', $text) ?? $text;

        return $text;
    }

    private function toMarkdown(string $text): string
    {
        $lines = array_map(
            fn ($line) => $this->sanitizeLine($line),
            explode("\n", $text)
        );

        $markdownLines = [];

        foreach ($lines as $line) {
            if ($line === '') {
                $markdownLines[] = '';

                continue;
            }

            if ($this->isHeading($line)) {
                $markdownLines[] = '## '.$line;

                continue;
            }

            if ($bullet = $this->normalizeBullet($line)) {
                $markdownLines[] = $bullet;

                continue;
            }

            $markdownLines[] = $line;
        }

        return implode("\n", $markdownLines);
    }

    private function sanitizeLine(string $line): string
    {
        // Normalize common bullet glyphs to a markdown dash.
        $line = preg_replace('/^[\x{2022}\x{2023}\x{25CF}\x{25CB}\x{25A0}]+\s*/u', '- ', $line) ?? $line;

        return trim($line);
    }

    private function isHeading(string $line): bool
    {
        if (mb_strlen($line) < 3 || mb_strlen($line) > 80) {
            return false;
        }

        if (str_contains($line, '.')) {
            return false;
        }

        $letters = preg_replace('/[^a-z]/iu', '', $line) ?? '';

        if ($letters === '') {
            return false;
        }

        // Treat lines that look like ALL CAPS headings as headings.
        return mb_strtoupper($letters, 'UTF-8') === $letters;
    }

    private function normalizeBullet(string $line): ?string
    {
        if ($line === '') {
            return null;
        }

        if (preg_match('/^(?:•|-|\x{2022})\s*(.+)$/u', $line, $matches)) {
            return '- '.$matches[1];
        }

        if (preg_match('/^\d{1,2}[.)]\s*(.+)$/u', $line, $matches)) {
            return '- '.$matches[1];
        }

        return null;
    }

    private function collapseBlankLines(string $markdown): string
    {
        $markdown = preg_replace("/\n{3,}/u", "\n\n", $markdown) ?? $markdown;

        return $markdown;
    }
}
