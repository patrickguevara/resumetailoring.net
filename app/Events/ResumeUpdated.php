<?php

namespace App\Events;

use App\Models\Resume;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ResumeUpdated implements ShouldBroadcast
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public Resume $resume)
    {
        $this->resume->loadMissing([
            'tailoredResumes.jobDescription',
        ]);
        $this->resume->loadCount(['evaluations', 'tailoredResumes']);
    }

    public function broadcastAs(): string
    {
        return 'ResumeUpdated';
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel(sprintf('App.Models.User.%d', $this->resume->user_id)),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'resume' => [
                'id' => $this->resume->id,
                'slug' => $this->resume->slug,
                'title' => $this->resume->title,
                'description' => $this->resume->description,
                'content_markdown' => $this->resume->content_markdown,
                'ingestion_status' => $this->resume->ingestion_status,
                'ingestion_error' => $this->resume->ingestion_error,
                'ingested_at' => $this->resume->ingested_at?->toIso8601String(),
                'created_at' => $this->resume->created_at?->toIso8601String(),
                'updated_at' => $this->resume->updated_at?->toIso8601String(),
                'evaluations_count' => $this->resume->evaluations_count,
                'tailored_count' => $this->resume->tailored_resumes_count,
                'tailored_for' => $this->tailoredTargets(),
            ],
        ];
    }

    private function tailoredTargets(): array
    {
        return $this->resume->tailoredResumes
            ->filter(fn ($tailored) => $tailored->jobDescription !== null)
            ->map(fn ($tailored) => [
                'job_id' => $tailored->jobDescription->id,
                'job_title' => $tailored->jobDescription->title
                    ?? $tailored->jobDescription->sourceLabel(),
                'company' => $tailored->jobDescription->company
                    ?? data_get($tailored->jobDescription->metadata, 'company'),
            ])
            ->unique(fn ($item) => $item['job_id'])
            ->take(3)
            ->values()
            ->all();
    }
}

