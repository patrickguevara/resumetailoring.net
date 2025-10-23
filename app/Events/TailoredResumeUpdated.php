<?php

namespace App\Events;

use App\Models\ResumeEvaluation;
use App\Models\TailoredResume;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TailoredResumeUpdated implements ShouldBroadcast
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public string $status,
        public ResumeEvaluation $evaluation,
        public ?TailoredResume $tailoredResume = null,
        public ?string $errorMessage = null,
    ) {
        $this->evaluation->loadMissing('resume:id,title,slug', 'jobDescription');

        if ($this->tailoredResume !== null) {
            $this->tailoredResume->loadMissing(
                'jobDescription:id,title,source_url,company,metadata',
                'resume:id,title,slug'
            );
        }
    }

    public function broadcastAs(): string
    {
        return 'TailoredResumeUpdated';
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel(sprintf('App.Models.User.%d', $this->evaluation->user_id)),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'status' => $this->status,
            'evaluation_id' => $this->evaluation->id,
            'error_message' => $this->errorMessage,
            'tailored_resume' => $this->tailoredResume
                ? [
                    'id' => $this->tailoredResume->id,
                    'title' => $this->tailoredResume->title,
                    'model' => $this->tailoredResume->model,
                    'content_markdown' => $this->tailoredResume->content_markdown,
                    'created_at' => $this->tailoredResume->created_at?->toIso8601String(),
                    'evaluation_id' => $this->tailoredResume->resume_evaluation_id,
                    'job_description' => $this->tailoredResume->jobDescription
                        ? [
                            'id' => $this->tailoredResume->jobDescription->id,
                            'title' => $this->tailoredResume->jobDescription->title,
                            'url' => $this->tailoredResume->jobDescription->isManual()
                                ? null
                                : $this->tailoredResume->jobDescription->source_url,
                            'source_label' => $this->tailoredResume->jobDescription->sourceLabel(),
                            'is_manual' => $this->tailoredResume->jobDescription->isManual(),
                            'company' => $this->tailoredResume->jobDescription->company
                                ?? data_get($this->tailoredResume->jobDescription->metadata, 'company'),
                        ]
                        : null,
                    'resume' => $this->tailoredResume->resume
                        ? [
                            'id' => $this->tailoredResume->resume->id,
                            'title' => $this->tailoredResume->resume->title,
                            'slug' => $this->tailoredResume->resume->slug,
                        ]
                        : null,
                ]
                : null,
        ];
    }
}

