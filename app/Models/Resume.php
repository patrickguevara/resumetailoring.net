<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Resume extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'title',
        'slug',
        'description',
        'content_markdown',
        'ingestion_status',
        'ingestion_error',
        'ingested_at',
    ];

    protected $casts = [
        'ingested_at' => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
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
}
