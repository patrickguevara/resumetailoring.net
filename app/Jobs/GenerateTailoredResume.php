<?php

namespace App\Jobs;

use App\Events\ResumeEvaluationUpdated;
use App\Events\TailoredResumeUpdated;
use App\Models\ResumeEvaluation;
use App\Models\TailoredResume;
use App\Services\ResumeIntelligenceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class GenerateTailoredResume implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private const QUEUE = 'ai';

    public int $tries = 1;

    public function __construct(
        public int $evaluationId,
        public ?string $customTitle = null,
    ) {
        $this->onQueue(self::QUEUE);
    }

    public function handle(ResumeIntelligenceService $intelligenceService): void
    {
        $evaluation = ResumeEvaluation::query()
            ->with(['resume', 'jobDescription'])
            ->find($this->evaluationId);

        if (! $evaluation) {
            return;
        }

        if ($evaluation->feedback_markdown === null) {
            broadcast(new TailoredResumeUpdated(
                status: 'failed',
                evaluation: $evaluation,
                tailoredResume: null,
                errorMessage: 'Evaluation feedback is required before tailoring a resume.',
            ));

            return;
        }

        $resume = $evaluation->resume;
        $job = $evaluation->jobDescription;

        if (! $resume || ! $job) {
            return;
        }

        try {
            $result = $intelligenceService->tailor(
                $resume,
                $job,
                $evaluation,
                $evaluation->feedback_markdown,
            );
        } catch (Throwable $exception) {
            Log::warning('Tailored resume generation failed', [
                'evaluation_id' => $evaluation->id,
                'error' => $exception->getMessage(),
            ]);

            broadcast(new TailoredResumeUpdated(
                status: 'failed',
                evaluation: $evaluation,
                tailoredResume: null,
                errorMessage: $exception->getMessage(),
            ));

            return;
        }

        $title = $this->deriveTitle($job->title, $job->source_url);

        if ($this->customTitle !== null && $this->customTitle !== '') {
            $title = $this->customTitle;
        }

        /** @var TailoredResume $tailored */
        $tailored = $resume->tailoredResumes()->create([
            'user_id' => $evaluation->user_id,
            'job_description_id' => $job->id,
            'resume_evaluation_id' => $evaluation->id,
            'model' => $result['model'],
            'title' => $title,
            'content_markdown' => $result['content'],
        ]);

        $tailored->load('jobDescription:id,title,source_url,company,metadata');

        broadcast(new TailoredResumeUpdated(
            status: 'completed',
            evaluation: $evaluation->fresh(),
            tailoredResume: $tailored,
            errorMessage: null,
        ));

        $evaluation->refresh();
        $evaluation->load(['resume:id,title,slug', 'jobDescription']);
        $evaluation->loadCount('tailoredResumes');

        broadcast(new ResumeEvaluationUpdated($evaluation));
    }

    private function deriveTitle(?string $jobTitle, ?string $jobUrl): string
    {
        if ($jobTitle !== null && $jobTitle !== '') {
            return sprintf('Tailored for %s', $jobTitle);
        }

        if ($jobUrl === null || $jobUrl === '' || Str::startsWith($jobUrl, 'manual://')) {
            return 'Tailored resume';
        }

        $friendly = Str::of($jobUrl)
            ->afterLast('/')
            ->before('?')
            ->replace('-', ' ')
            ->replace('_', ' ')
            ->title()
            ->trim();

        return sprintf(
            'Tailored for %s',
            $friendly !== '' ? $friendly : 'target role'
        );
    }
}
