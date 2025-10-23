<?php

namespace App\Events;

use App\Models\ResumeEvaluation;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class ResumeEvaluationUpdated implements ShouldBroadcast
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public ResumeEvaluation $evaluation)
    {
        $this->evaluation->loadMissing([
            'resume:id,title,slug',
            'jobDescription',
        ]);
        $this->evaluation->loadCount('tailoredResumes');
    }

    public function broadcastAs(): string
    {
        return 'ResumeEvaluationUpdated';
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel(sprintf('App.Models.User.%d', $this->evaluation->user_id)),
        ];
    }

    public function broadcastWith(): array
    {
        $job = $this->evaluation->jobDescription;

        return [
            'evaluation' => [
                'id' => $this->evaluation->id,
                'status' => $this->evaluation->status,
                'headline' => $this->evaluation->headline,
                'model' => $this->evaluation->model,
                'notes' => $this->evaluation->notes,
            'feedback_markdown' => $this->truncateMarkdown($this->evaluation->feedback_markdown),
            'error_message' => $this->evaluation->error_message,
                'tailored_count' => $this->evaluation->tailored_resumes_count ?? 0,
                'completed_at' => $this->evaluation->completed_at?->toIso8601String(),
                'created_at' => $this->evaluation->created_at?->toIso8601String(),
                'job_description' => $job
                    ? [
                        'id' => $job->id,
                        'title' => $job->title,
                        'url' => $job->isManual() ? null : $job->source_url,
                        'source_label' => $job->sourceLabel(),
                        'is_manual' => $job->isManual(),
                        'company' => $job->company ?? data_get($job->metadata, 'company'),
                        'description_preview' => $this->descriptionPreview($job->content_markdown),
                    ]
                    : null,
                'resume' => $this->evaluation->resume
                    ? [
                        'id' => $this->evaluation->resume->id,
                        'title' => $this->evaluation->resume->title,
                        'slug' => $this->evaluation->resume->slug,
                    ]
                    : null,
            ],
        ];
    }

    private function truncateMarkdown(?string $content): ?string
    {
        if ($content === null) {
            return null;
        }

        return Str::limit($content, 14000);
    }

    private function descriptionPreview(?string $content): ?string
    {
        if ($content === null) {
            return null;
        }

        return Str::of($content)->stripTags()->limit(600)->value();
    }
}
