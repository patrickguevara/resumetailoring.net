<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ResumeEvaluation extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'user_id',
        'resume_id',
        'job_description_id',
        'status',
        'model',
        'headline',
        'feedback_markdown',
        'feedback_structured',
        'notes',
        'error_message',
        'completed_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
        'feedback_structured' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function resume(): BelongsTo
    {
        return $this->belongsTo(Resume::class);
    }

    public function jobDescription(): BelongsTo
    {
        return $this->belongsTo(JobDescription::class);
    }

    public function tailoredResumes(): HasMany
    {
        return $this->hasMany(TailoredResume::class);
    }

    public function companyResearches(): HasMany
    {
        return $this->hasMany(CompanyResearch::class);
    }

    public function aiPrompts(): MorphMany
    {
        return $this->morphMany(AiPrompt::class, 'promptable');
    }

    /**
     * Get feedback data with smart fallback for legacy evaluations.
     */
    public function getFeedbackDataAttribute(): array
    {
        if ($this->feedback_structured) {
            return $this->feedback_structured;
        }

        // Fallback for old evaluations without structured data
        return [
            'sentiment' => 'good_match',
            'highlights' => null,
            'key_phrases' => [],
            'sections' => [
                'summary' => $this->feedback_markdown ?? '',
                'relevant_experience' => null,
                'gaps' => null,
                'recommendations' => null,
            ],
        ];
    }
}
