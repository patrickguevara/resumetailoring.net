<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TailoredResume extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'resume_id',
        'job_description_id',
        'resume_evaluation_id',
        'model',
        'title',
        'content_markdown',
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

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(ResumeEvaluation::class, 'resume_evaluation_id');
    }
}
