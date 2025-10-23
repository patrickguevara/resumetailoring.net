<?php

namespace App\Events;

use App\Models\CompanyResearch;
use App\Models\JobDescription;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class CompanyResearchUpdated implements ShouldBroadcast
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public string $status,
        public JobDescription $job,
        public ?CompanyResearch $research = null,
        public ?string $errorMessage = null,
    ) {
        $this->job->loadMissing('latestCompanyResearch');

        if ($this->research !== null) {
            $this->research->loadMissing('jobDescription:id,user_id,metadata,company');
        }
    }

    public function broadcastAs(): string
    {
        return 'CompanyResearchUpdated';
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel(sprintf('App.Models.User.%d', $this->job->user_id)),
        ];
    }

    public function broadcastWith(): array
    {
        [$summary, $summaryTruncated] = $this->truncateSummary($this->research?->summary);

        return [
            'status' => $this->status,
            'job_id' => $this->job->id,
            'company' => $this->job->company ?? data_get($this->job->metadata, 'company'),
            'company_research' => $this->research
                ? [
                    'summary' => $summary,
                    'summary_is_truncated' => $summaryTruncated,
                    'last_ran_at' => $this->research->ran_at?->toIso8601String(),
                    'model' => $this->research->model,
                    'focus' => $this->research->focus,
                ]
                : null,
            'error_message' => $this->errorMessage,
        ];
    }

    /**
     * @return array{0: ?string, 1: bool}
     */
    private function truncateSummary(?string $content, int $limit = 6000): array
    {
        if ($content === null) {
            return [null, false];
        }

        $truncated = Str::limit($content, $limit);

        return [$truncated, mb_strlen($content) > $limit];
    }
}
