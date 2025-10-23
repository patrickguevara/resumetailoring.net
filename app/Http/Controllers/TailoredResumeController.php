<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTailoredResumeRequest;
use App\Jobs\GenerateTailoredResume;
use App\Models\ResumeEvaluation;
use App\Models\TailoredResume;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

    public function show(Request $request, TailoredResume $tailoredResume): JsonResponse
    {
        abort_unless($tailoredResume->user_id === $request->user()->id, 404);

        $tailoredResume->loadMissing(
            'resume:id,title,slug',
            'jobDescription:id,title,source_url,company,metadata'
        );

        return response()->json([
            'id' => $tailoredResume->id,
            'title' => $tailoredResume->title,
            'model' => $tailoredResume->model,
            'content_markdown' => $tailoredResume->content_markdown,
            'created_at' => $tailoredResume->created_at?->toIso8601String(),
            'evaluation_id' => $tailoredResume->resume_evaluation_id,
            'resume' => $tailoredResume->resume
                ? [
                    'id' => $tailoredResume->resume->id,
                    'title' => $tailoredResume->resume->title,
                    'slug' => $tailoredResume->resume->slug,
                ]
                : null,
            'job_description' => $tailoredResume->jobDescription
                ? [
                    'id' => $tailoredResume->jobDescription->id,
                    'title' => $tailoredResume->jobDescription->title,
                    'url' => $tailoredResume->jobDescription->isManual()
                        ? null
                        : $tailoredResume->jobDescription->source_url,
                    'source_label' => $tailoredResume->jobDescription->sourceLabel(),
                    'is_manual' => $tailoredResume->jobDescription->isManual(),
                    'company' => $tailoredResume->jobDescription->company
                        ?? data_get($tailoredResume->jobDescription->metadata, 'company'),
                ]
                : null,
        ]);
    }
}
