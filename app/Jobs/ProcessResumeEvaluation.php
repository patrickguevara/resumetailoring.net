<?php

namespace App\Jobs;

use App\Events\ResumeEvaluationUpdated;
use App\Models\ResumeEvaluation;
use App\Services\ResumeIntelligenceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ProcessResumeEvaluation implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private const QUEUE = 'ai';

    /**
     * Retry failures manually so we can surface the error to the user quickly.
     */
    public int $tries = 1;

    public function __construct(
        public int $evaluationId,
        public ?string $jobUrlOverride = null,
        public ?string $modelOverride = null,
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

        $resume = $evaluation->resume;
        $job = $evaluation->jobDescription;

        if (! $resume || ! $job) {
            return;
        }

        $model = $this->modelOverride ?: $evaluation->model;

        try {
            $result = $intelligenceService->evaluate(
                $resume,
                $job,
                $evaluation,
                $this->jobUrlOverride,
                $model
            );

            $headline = Str::of($result['content'])
                ->before("\n")
                ->stripTags()
                ->trim()
                ->limit(160);

            $evaluation->forceFill([
                'status' => ResumeEvaluation::STATUS_COMPLETED,
                'model' => $result['model'],
                'feedback_markdown' => $result['content'],
                'feedback_structured' => $result['structured'],
                'headline' => (string) $headline ?: null,
                'completed_at' => now(),
                'error_message' => null,
            ])->save();
        } catch (Throwable $exception) {
            $evaluation->forceFill([
                'status' => ResumeEvaluation::STATUS_FAILED,
                'error_message' => $exception->getMessage(),
            ])->save();

            Log::warning('Resume evaluation failed', [
                'evaluation_id' => $evaluation->id,
                'error' => $exception->getMessage(),
            ]);
        }

        $evaluation->refresh();
        $evaluation->load([
            'resume:id,title,slug',
            'jobDescription',
        ]);
        $evaluation->loadCount('tailoredResumes');

        broadcast(new ResumeEvaluationUpdated($evaluation));
    }
}
