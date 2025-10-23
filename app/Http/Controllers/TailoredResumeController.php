<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTailoredResumeRequest;
use App\Jobs\GenerateTailoredResume;
use App\Models\ResumeEvaluation;
use Illuminate\Http\RedirectResponse;

class TailoredResumeController extends Controller
{
    public function store(
        StoreTailoredResumeRequest $request,
        ResumeEvaluation $evaluation
    ): RedirectResponse {
        $user = $request->user();

        abort_unless($evaluation->user_id === $user->id, 404);

        if ($evaluation->feedback_markdown === null) {
            return back()->withErrors([
                'evaluation' => 'Evaluation feedback is required before tailoring a resume.',
            ]);
        }

        $title = (string) $request->string('title')->trim();

        GenerateTailoredResume::dispatch(
            evaluationId: $evaluation->id,
            customTitle: $title !== '' ? $title : null,
        );

        return to_route('resumes.show', $evaluation->resume)->with('flash', [
            'type' => 'info',
            'message' => 'Tailored resume request queued. We will notify you when it is ready.',
        ]);
    }
}
