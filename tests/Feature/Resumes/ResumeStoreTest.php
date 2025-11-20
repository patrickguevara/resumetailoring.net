<?php

use App\Jobs\ProcessUploadedResume;
use App\Models\Resume;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

it('stores a markdown resume immediately', function () {
    Queue::fake();
    Storage::fake('local');

    $user = User::factory()->create();

    $response = actingAs($user)->post(route('resumes.store'), [
        'title' => 'Product Manager Resume',
        'description' => 'Primary resume',
        'input_type' => 'markdown',
        'content_markdown' => "# Summary\n\nReady to evaluate.",
    ]);

    $resume = Resume::first();

    expect($resume)
        ->not->toBeNull()
        ->and($resume->ingestion_status)->toBe('completed')
        ->and($resume->ingestion_error)->toBeNull()
        ->and($resume->ingested_at)->not->toBeNull();

    Queue::assertNothingPushed();

    $response->assertRedirect(route('resumes.show', $resume));
});

it('queues ingestion when uploading a pdf resume', function () {
    Queue::fake();
    Storage::fake('local');

    $user = User::factory()->create();

    $pdf = UploadedFile::fake()->create('resume.pdf', 256, 'application/pdf');

    $response = actingAs($user)->post(route('resumes.store'), [
        'title' => 'AI Resume',
        'description' => 'Latest copy',
        'input_type' => 'pdf',
        'resume_file' => $pdf,
    ]);

    $resume = Resume::first();

    expect($resume)
        ->not->toBeNull()
        ->and($resume->ingestion_status)->toBe('processing')
        ->and($resume->ingested_at)->toBeNull()
        ->and($resume->content_markdown)->toContain('Processing uploaded resume');

    $storedPath = null;

    Queue::assertPushed(ProcessUploadedResume::class, function ($job) use ($resume, &$storedPath) {
        $storedPath = $job->storedPath;

        return $job->resumeId === $resume->id && is_string($storedPath) && $storedPath !== '';
    });

    expect($storedPath)->not->toBeNull();
    Storage::disk('local')->assertExists($storedPath);

    $response->assertRedirect(route('resumes.show', $resume));
});

it('requires a pdf file when selecting pdf upload', function () {
    Queue::fake();

    $user = User::factory()->create();

    $response = actingAs($user)
        ->from(route('resumes.index'))
        ->post(route('resumes.store'), [
            'title' => 'Test resume',
            'description' => null,
            'input_type' => 'pdf',
        ]);

    $response->assertSessionHasErrors(['resume_file']);
    expect(Resume::count())->toBe(0);
});
