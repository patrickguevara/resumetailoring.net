<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class CompanyResearch extends Model
{
    use HasFactory;

    protected $table = 'company_research';

    protected $fillable = [
        'user_id',
        'job_description_id',
        'resume_evaluation_id',
        'company',
        'model',
        'focus',
        'summary',
        'ran_at',
    ];

    protected $casts = [
        'ran_at' => 'datetime',
    ];

    public function jobDescription(): BelongsTo
    {
        return $this->belongsTo(JobDescription::class);
    }

    public function resumeEvaluation(): BelongsTo
    {
        return $this->belongsTo(ResumeEvaluation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function prompt(): MorphOne
    {
        return $this->morphOne(AiPrompt::class, 'promptable');
    }
}
