<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTailoredResumeRequest;
use App\Models\ResumeEvaluation;
use App\Services\ResumeIntelligenceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use RuntimeException;

class TailoredResumeController extends Controller
{
    public function store(
        StoreTailoredResumeRequest $request,
        ResumeEvaluation $evaluation,
        ResumeIntelligenceService $intelligenceService
    ): RedirectResponse {
        $user = $request->user();

        abort_unless($evaluation->user_id === $user->id, 404);

        if ($evaluation->feedback_markdown === null) {
            return back()->withErrors([
                'evaluation' => 'Evaluation feedback is required before tailoring a resume.',
            ]);
        }

        $resume = $evaluation->resume;
        $job = $evaluation->jobDescription;

        try {
            $result = $intelligenceService->tailor($resume, $job, $evaluation, $evaluation->feedback_markdown);
        } catch (RuntimeException $exception) {
            return back()->withErrors([
                'tailor' => $exception->getMessage(),
            ]);
        }

        $title = $request->string('title')->trim();

        if ($title === '') {
            $jobTitle = $job->title;

            if ($jobTitle === null || $jobTitle === '') {
                if ($job->isManual()) {
                    $jobTitle = 'manual job description';
                } else {
                    $jobTitle = Str::of((string) $job->source_url)
                        ->afterLast('/')
                        ->before('?')
                        ->replace('-', ' ')
                        ->title();
                }
            }

            $title = sprintf('Tailored for %s', $jobTitle ?: 'target role');
        }

        $tailored = $resume->tailoredResumes()->create([
            'user_id' => $user->id,
            'job_description_id' => $job->id,
            'resume_evaluation_id' => $evaluation->id,
            'model' => $result['model'],
            'title' => $title,
            'content_markdown' => $result['content'],
        ]);

        return to_route('resumes.show', $resume)->with('flash', [
            'type' => 'success',
            'message' => sprintf('Tailored resume "%s" created.', $tailored->title),
        ]);
    }
}
