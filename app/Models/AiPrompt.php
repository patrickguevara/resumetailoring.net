<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AiPrompt extends Model
{
    use HasFactory;

    public const CATEGORY_EVALUATION = 'evaluation';

    public const CATEGORY_TAILOR = 'tailor';

    public const CATEGORY_RESEARCH = 'research';

    protected $fillable = [
        'user_id',
        'promptable_type',
        'promptable_id',
        'category',
        'model',
        'system_prompt',
        'user_prompt',
    ];

    public function promptable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
