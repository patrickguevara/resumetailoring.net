<?php

use App\Services\JobDescriptionFetcher;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

it('extracts visible job description content as markdown', function () {
    $html = <<<'HTML'
    <html>
        <body>
            <header>
                <h1>Company Careers</h1>
            </header>
            <main id="job-description">
                <h2>Senior Engineer</h2>
                <div class="intro">
                    <p>Join our mission <span style="display:none">secret</span> to build.</p>
                    <p class="hidden">This paragraph should not appear.</p>
                </div>
                <section>
                    <h3>Responsibilities</h3>
                    <ul>
                        <li>Lead projects</li>
                        <li>Coach teammates</li>
                    </ul>
                    <form>
                        <label>First name</label>
                        <input type="text" />
                    </form>
                    <div style="opacity:0">Hidden role</div>
                    <button>Apply Now</button>
                </section>
                <p><strong>Location:</strong> Remote</p>
                <p>Learn more at <a href="https://company.example/about">Company site</a>.</p>
            </main>
        </body>
    </html>
    HTML;

    Http::fake([
        'https://example.com/job' => Http::response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']),
    ]);

    $fetcher = new JobDescriptionFetcher();
    $markdown = $fetcher->fetch('https://example.com/job');

    $expected = <<<'MARKDOWN'
    ## Senior Engineer

    Join our mission to build.

    ### Responsibilities

    - Lead projects
    - Coach teammates

    **Location:** Remote

    Learn more at [Company site](https://company.example/about).
    MARKDOWN;

    expect($markdown)->toBe($expected);
    expect($markdown)->not->toContain('First name');
    expect($markdown)->not->toContain('Apply Now');
});

it('prefers structured data descriptions when visible markup is sparse', function () {
    $html = <<<'HTML'
    <html>
        <body>
            <main>
                <h1>Senior Engineering Manager, Cards Growth</h1>
                <p>Tilt Finance</p>
            </main>
            <script type="application/ld+json">
            {
                "@context": "https://schema.org/",
                "@type": "JobPosting",
                "title": "Senior Engineering Manager, Cards Growth",
                "description": "<h1>Join the Tilt team</h1><p>At Tilt we see a side of people that traditional lenders miss.</p><h2>Why You're a Great Fit</h2><ul><li>Collaborate across teams to drive growth.</li><li>Mentor engineers and raise the bar.</li></ul>"
            }
            </script>
        </body>
    </html>
    HTML;

    Http::fake([
        'https://example.com/json-ld-job' => Http::response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']),
    ]);

    $fetcher = new JobDescriptionFetcher();
    $markdown = $fetcher->fetch('https://example.com/json-ld-job');

    $expected = <<<'MARKDOWN'
    # Join the Tilt team

    At Tilt we see a side of people that traditional lenders miss.

    ## Why You're a Great Fit

    - Collaborate across teams to drive growth.
    - Mentor engineers and raise the bar.
    MARKDOWN;

    expect($markdown)->toBe($expected);
    expect($markdown)->not->toContain('Tilt Finance');
});
