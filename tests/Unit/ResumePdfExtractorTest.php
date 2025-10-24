<?php

use App\Services\ResumePdfExtractor;
use Smalot\PdfParser\Parser;

it('converts parsed pdf text into normalized markdown', function () {
    $path = tempnam(sys_get_temp_dir(), 'pdf');
    file_put_contents($path, 'dummy');

    try {
        $parser = \Mockery::mock(Parser::class);
        $document = \Mockery::mock('Smalot\\PdfParser\\Document');

        $parser->expects('parseFile')
            ->once()
            ->with($path)
            ->andReturn($document);

        $document->expects('getText')
            ->once()
            ->andReturn("EXPERIENCE\n• Led cross-functional teams\nEducation\n2019 - 2020\n");

        $extractor = new ResumePdfExtractor($parser);
        $markdown = $extractor->extract($path);

        expect($markdown)
            ->toContain("## EXPERIENCE")
            ->and($markdown)->toContain('- Led cross-functional teams')
            ->and($markdown)->toContain('Education');
    } finally {
        @unlink($path);
        \Mockery::close();
    }
});

it('throws when the source file is missing', function () {
    $parser = \Mockery::mock(Parser::class);
    $extractor = new ResumePdfExtractor($parser);

    expect(fn () => $extractor->extract('/path/to/missing.pdf'))
        ->toThrow(\RuntimeException::class, 'Uploaded resume file could not be found.');

    \Mockery::close();
});
