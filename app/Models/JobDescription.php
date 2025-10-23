<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class JobDescription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'company',
        'source_url',
        'source_url_hash',
        'content_markdown',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (JobDescription $job): void {
            if ($job->isDirty('source_url')) {
                $job->source_url_hash = hash('sha256', (string) $job->source_url);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(ResumeEvaluation::class);
    }

    public function tailoredResumes(): HasMany
    {
        return $this->hasMany(TailoredResume::class);
    }

    public function companyResearches(): HasMany
    {
        return $this->hasMany(CompanyResearch::class);
    }

    public function latestCompanyResearch(): HasOne
    {
        return $this->hasOne(CompanyResearch::class)->latestOfMany('ran_at');
    }

    public function isManual(): bool
    {
        return Str::startsWith((string) $this->source_url, 'manual://');
    }

    public function sourceLabel(): string
    {
        if ($this->isManual()) {
            return 'Manual job description';
        }

        return (string) $this->source_url;
    }
}
