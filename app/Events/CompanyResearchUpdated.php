<?php

namespace App\Events;

use App\Models\CompanyResearch;
use App\Models\JobDescription;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

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
        return [
            'status' => $this->status,
            'job_id' => $this->job->id,
            'company' => $this->job->company ?? data_get($this->job->metadata, 'company'),
            'company_research' => $this->research
                ? [
                    'summary' => $this->research->summary,
                    'last_ran_at' => $this->research->ran_at?->toIso8601String(),
                    'model' => $this->research->model,
                    'focus' => $this->research->focus,
                ]
                : null,
            'error_message' => $this->errorMessage,
        ];
    }
}

