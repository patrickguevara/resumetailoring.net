<?php

namespace App\Jobs;

use App\Events\CompanyResearchUpdated;
use App\Models\CompanyResearch;
use App\Models\JobDescription;
use App\Services\ResumeIntelligenceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class RunCompanyResearch implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private const QUEUE = 'ai';

    public int $tries = 1;

    public function __construct(
        public int $jobId,
        public string $company,
        public ?string $focus = null,
        public ?string $modelOverride = null,
    ) {
        $this->onQueue(self::QUEUE);
    }

    public function handle(ResumeIntelligenceService $intelligenceService): void
    {
        $job = JobDescription::query()
            ->with('latestCompanyResearch')
            ->find($this->jobId);

        if (! $job) {
            return;
        }

        try {
            $result = $intelligenceService->researchCompany(
                $job,
                $this->company,
                $job->title ?? 'the role',
                $this->focus ?: null,
                $this->modelOverride,
            );
        } catch (Throwable $exception) {
            Log::warning('Company research failed', [
                'job_id' => $job->id,
                'error' => $exception->getMessage(),
            ]);

            broadcast(new CompanyResearchUpdated(
                status: 'failed',
                job: $job->fresh(),
                research: null,
                errorMessage: $exception->getMessage(),
            ));

            return;
        }

        $ranAt = now();

        /** @var CompanyResearch $research */
        $research = CompanyResearch::create([
            'user_id' => $job->user_id,
            'job_description_id' => $job->id,
            'company' => $this->company,
            'model' => $result['model'],
            'focus' => $this->focus ?: null,
            'summary' => $result['content'],
            'ran_at' => $ranAt,
        ]);

        $promptLog = $result['prompt_log'] ?? null;
        if ($promptLog !== null) {
            $promptLog->promptable()->associate($research);
            $promptLog->save();
        }

        $metadata = $job->metadata ?? [];
        unset($metadata['company'], $metadata['company_research']);

        $job->forceFill([
            'company' => $this->company,
            'metadata' => $metadata !== [] ? $metadata : null,
        ])->save();

        $job->load('latestCompanyResearch');

        broadcast(new CompanyResearchUpdated(
            status: 'completed',
            job: $job,
            research: $research,
            errorMessage: null,
        ));
    }
}
