<?php

use App\Events\ResumeUpdated;
use App\Jobs\ProcessUploadedResume;
use App\Models\User;
use App\Services\ResumePdfExtractor;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

it('updates the resume once pdf ingestion succeeds', function () {
    Event::fake();
    Storage::fake('local');

    $user = User::factory()->create();

    $resume = $user->resumes()->create([
        'title' => 'Pending Upload',
        'slug' => (string) Str::uuid(),
        'description' => null,
        'content_markdown' => '*Processing uploaded resume...*',
        'ingestion_status' => 'processing',
        'ingestion_error' => null,
        'ingested_at' => null,
    ]);

    $storedPath = "resume-uploads/{$user->id}/upload.pdf";
    Storage::disk('local')->put($storedPath, 'original pdf bytes');

    $job = new ProcessUploadedResume($resume->id, $storedPath);

    $extractor = \Mockery::mock(ResumePdfExtractor::class);
    $extractor->expects('extract')
        ->once()
        ->with(Storage::disk('local')->path($storedPath))
        ->andReturn("# Summary\n\n- Highlights");

    try {
        $job->handle($extractor);
    } finally {
        \Mockery::close();
    }

    $resume->refresh();

    expect($resume->ingestion_status)->toBe('completed')
        ->and($resume->ingestion_error)->toBeNull()
        ->and($resume->ingested_at)->not->toBeNull()
        ->and($resume->content_markdown)->toContain('# Summary');

    Storage::disk('local')->assertMissing($storedPath);

    Event::assertDispatched(ResumeUpdated::class, function (ResumeUpdated $event) use ($resume) {
        return $event->resume->id === $resume->id
            && $event->resume->ingestion_status === 'completed';
    });
});

it('marks the resume as failed when extraction throws an exception', function () {
    Event::fake();
    Storage::fake('local');

    $user = User::factory()->create();

    $resume = $user->resumes()->create([
        'title' => 'Broken Upload',
        'slug' => (string) Str::uuid(),
        'description' => null,
        'content_markdown' => '*Processing uploaded resume...*',
        'ingestion_status' => 'processing',
        'ingestion_error' => null,
        'ingested_at' => null,
    ]);

    $storedPath = "resume-uploads/{$user->id}/upload.pdf";
    Storage::disk('local')->put($storedPath, 'broken pdf bytes');

    $job = new ProcessUploadedResume($resume->id, $storedPath);

    $extractor = \Mockery::mock(ResumePdfExtractor::class);
    $extractor->expects('extract')
        ->once()
        ->andThrow(new \RuntimeException('PDF parse failed.'));

    try {
        $job->handle($extractor);
    } finally {
        \Mockery::close();
    }

    $resume->refresh();

    expect($resume->ingestion_status)->toBe('failed')
        ->and($resume->ingestion_error)->toBe('PDF parse failed.')
        ->and($resume->ingested_at)->toBeNull();

    Storage::disk('local')->assertMissing($storedPath);

    Event::assertDispatched(ResumeUpdated::class, function (ResumeUpdated $event) use ($resume) {
        return $event->resume->id === $resume->id
            && $event->resume->ingestion_status === 'failed'
            && $event->resume->ingestion_error === 'PDF parse failed.';
    });
});
