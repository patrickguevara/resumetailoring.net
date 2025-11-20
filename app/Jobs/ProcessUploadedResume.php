<?php

namespace App\Jobs;

use App\Events\ResumeUpdated;
use App\Models\Resume;
use App\Services\ResumePdfExtractor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class ProcessUploadedResume implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private const QUEUE = 'resumes';

    public function __construct(
        public int $resumeId,
        public string $storedPath
    ) {
        $this->onQueue(self::QUEUE);
    }

    public function handle(ResumePdfExtractor $extractor): void
    {
        $resume = Resume::query()->find($this->resumeId);

        if (! $resume) {
            $this->cleanupFile();

            return;
        }

        try {
            $absolutePath = $this->absolutePath();
            $markdown = $extractor->extract($absolutePath);

            $resume->forceFill([
                'content_markdown' => $markdown,
                'ingestion_status' => 'completed',
                'ingestion_error' => null,
                'ingested_at' => now(),
            ])->save();
        } catch (Throwable $exception) {
            $this->markFailed($resume, $exception);

            return;
        } finally {
            $this->cleanupFile();
        }

        $resume->refresh();
        broadcast(new ResumeUpdated($resume));
    }

    private function absolutePath(): string
    {
        $disk = Storage::disk('local');

        if (! $disk->exists($this->storedPath)) {
            throw new RuntimeException('Uploaded resume file could not be located for processing.');
        }

        return $disk->path($this->storedPath);
    }

    private function markFailed(Resume $resume, Throwable $exception): void
    {
        Log::warning('Resume ingestion failed', [
            'resume_id' => $resume->id,
            'error' => $exception->getMessage(),
        ]);

        $resume->forceFill([
            'ingestion_status' => 'failed',
            'ingestion_error' => $exception->getMessage(),
        ])->save();

        $resume->refresh();
        broadcast(new ResumeUpdated($resume));
    }

    private function cleanupFile(): void
    {
        $disk = Storage::disk('local');

        if ($disk->exists($this->storedPath)) {
            $disk->delete($this->storedPath);
        }
    }
}
