<?php

namespace App\Services;

use App\Models\AiPrompt;
use App\Models\JobDescription;
use App\Models\Resume;
use App\Models\ResumeEvaluation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ResumeIntelligenceService
{
    public function __construct(
        private readonly JobDescriptionFetcher $jobFetcher
    ) {}

    /**
     * Generate evaluation feedback comparing a resume to a job description.
     *
     * @return array{model: string, content: string, structured: array, system_prompt: string, prompt: string, prompt_log: AiPrompt}
     */
    public function evaluate(
        Resume $resume,
        JobDescription $job,
        ResumeEvaluation $evaluation,
        ?string $jobUrlOverride = null,
        ?string $modelOverride = null
    ): array {
        $jobText = $this->resolveJobDescriptionText($job, $jobUrlOverride);
        $model = $modelOverride ?: config('resume_intelligence.analysis.model');
        $systemPrompt = config('resume_intelligence.analysis.system_prompt');

        $prompt = $this->buildAnalysisPrompt(
            $jobText,
            $resume->content_markdown,
            $this->jobSourceSummary($job, $jobUrlOverride)
        );

        $promptLog = $this->logPrompt(
            $evaluation->user_id,
            $evaluation,
            AiPrompt::CATEGORY_EVALUATION,
            $model,
            $systemPrompt,
            $prompt
        );

        $payload = $this->callOpenAI(
            $model,
            $systemPrompt,
            $prompt
        );

        // Parse JSON response into structured format
        $structured = $this->parseStructuredFeedback($payload);

        // Generate markdown from structured sections for backward compatibility
        $markdown = $this->generateMarkdownFromStructured($structured);

        return [
            'model' => $model,
            'content' => $markdown,
            'structured' => $structured,
            'system_prompt' => $systemPrompt,
            'prompt' => $prompt,
            'prompt_log' => $promptLog,
        ];
    }

    /**
     * Generate a tailored resume leveraging evaluation feedback.
     *
     * @return array{model: string, content: string, system_prompt: string, prompt: string, prompt_log: AiPrompt}
     */
    public function tailor(
        Resume $resume,
        JobDescription $job,
        ResumeEvaluation $evaluation,
        string $feedback
    ): array {
        $jobText = $this->resolveJobDescriptionText($job);
        $model = config('resume_intelligence.tailor.model');
        $systemPrompt = config('resume_intelligence.tailor.system_prompt');

        $prompt = $this->buildTailorPrompt($jobText, $resume->content_markdown, $feedback);

        $promptLog = $this->logPrompt(
            $evaluation->user_id,
            $evaluation,
            AiPrompt::CATEGORY_TAILOR,
            $model,
            $systemPrompt,
            $prompt
        );

        $payload = $this->callOpenAI(
            $model,
            $systemPrompt,
            $prompt
        );

        return [
            'model' => $model,
            'content' => $payload,
            'system_prompt' => $systemPrompt,
            'prompt' => $prompt,
            'prompt_log' => $promptLog,
        ];
    }

    /**
     * Generate a company research briefing for a given job description.
     *
     * @return array{model: string, content: string, system_prompt: string, prompt: string, prompt_log: AiPrompt}
     */
    public function researchCompany(
        JobDescription $job,
        string $companyName,
        ?string $roleTitle = null,
        ?string $focus = null,
        ?string $modelOverride = null,
        ?ResumeEvaluation $evaluation = null
    ): array {
        $jobText = $this->resolveJobDescriptionText($job);
        $model = $modelOverride ?: config('resume_intelligence.research.model');
        $systemPrompt = config('resume_intelligence.research.system_prompt');

        $focusSegment = $focus !== null && $focus !== ''
            ? "Focus areas requested by the user:\n{$focus}\n\n"
            : '';

        $role = $roleTitle !== null && $roleTitle !== ''
            ? $roleTitle
            : ($job->title ?: 'the target role');

        $currentDate = now()->format('F j, Y');

        $prompt = <<<PROMPT
Current date: {$currentDate}
Company: {$companyName}
Target role: {$role}

{$focusSegment}Job description:
{$jobText}

Provide a concise research briefing covering:
- Recent company news, strategic moves, and leadership updates
- Product or service highlights relevant to the role
- Competitive landscape or market pressures to be aware of
- Talking points or questions to raise in conversations or interviews

Deliver the response in markdown with clear section headings and bullet lists.
PROMPT;

        $promptable = $evaluation ?: $job;

        $promptLog = $this->logPrompt(
            $job->user_id,
            $promptable,
            AiPrompt::CATEGORY_RESEARCH,
            $model,
            $systemPrompt,
            $prompt
        );

        $payload = $this->callOpenAI($model, $systemPrompt, $prompt);

        return [
            'model' => $model,
            'content' => $payload,
            'system_prompt' => $systemPrompt,
            'prompt' => $prompt,
            'prompt_log' => $promptLog,
        ];
    }

    private function resolveJobDescriptionText(JobDescription $job, ?string $jobUrlOverride = null): string
    {
        $stored = trim((string) $job->content_markdown);

        if ($stored !== '') {
            return $stored;
        }

        $metadata = $job->metadata ?? [];
        $metadataKeys = [
            'content_markdown',
            'description_markdown',
            'description',
            'job_text',
        ];

        foreach ($metadataKeys as $key) {
            $value = trim((string) data_get($metadata, $key, ''));

            if ($value !== '') {
                return $value;
            }
        }

        $url = $jobUrlOverride ?? $job->source_url;

        if ($url === null || $url === '') {
            throw new RuntimeException(
                'Job description is missing. Provide a job URL or paste the description text.'
            );
        }

        $content = $this->jobFetcher->fetch($url);

        $job->update(['content_markdown' => $content]);

        return $content;
    }

    private function callOpenAI(string $model, string $systemPrompt, string $userPrompt): string
    {
        $apiKey = config('resume_intelligence.api_key');

        if (! $apiKey) {
            throw new RuntimeException('OPENAI_API_KEY is not configured.');
        }

        $executionTimeout = (int) config('resume_intelligence.timeout', 180);

        if ($executionTimeout > 0) {
            // Extend the PHP execution window to accommodate longer model responses.
            @set_time_limit($executionTimeout);
        }

        $endpoint = rtrim(config('resume_intelligence.base_url'), '/').'/responses';

        try {
            $response = Http::withToken($apiKey)
                ->timeout($executionTimeout ?: 120)
                ->post($endpoint, [
                    'model' => $model,
                    'input' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $userPrompt],
                    ],
                ])
                ->throw();
        } catch (RequestException $exception) {
            throw new RuntimeException(
                sprintf('OpenAI API call failed: %s', $exception->getMessage()),
                previous: $exception
            );
        }

        $data = $response->json();
        $text = $this->extractResponseText($data);

        if ($text === '') {
            throw new RuntimeException('Received an empty response from OpenAI.');
        }

        return $text;
    }

    /**
     * Normalise the OpenAI Responses API payload into a plain text string.
     *
     * @param  array<string, mixed>  $data
     */
    private function extractResponseText(array $data): string
    {
        if (isset($data['output_text']) && is_string($data['output_text'])) {
            return trim($data['output_text']);
        }

        if (isset($data['output']) && is_array($data['output'])) {
            $chunks = [];

            foreach ($data['output'] as $item) {
                if (! is_array($item) || ! isset($item['content']) || ! is_array($item['content'])) {
                    continue;
                }

                foreach ($item['content'] as $content) {
                    $text = null;

                    if (is_array($content)) {
                        $text = $content['text'] ?? $content['output_text'] ?? null;
                    }

                    if (is_string($text)) {
                        $chunks[] = $text;
                    }
                }
            }

            $combined = trim(implode("\n", $chunks));

            if ($combined !== '') {
                return $combined;
            }
        }

        if (isset($data['choices']) && is_array($data['choices']) && isset($data['choices'][0]['message']['content'])) {
            $content = $data['choices'][0]['message']['content'];

            if (is_string($content)) {
                return trim($content);
            }
        }

        return '';
    }

    private function logPrompt(
        int $userId,
        Model $promptable,
        string $category,
        string $model,
        ?string $systemPrompt,
        string $userPrompt
    ): AiPrompt {
        return AiPrompt::create([
            'user_id' => $userId,
            'promptable_type' => $promptable->getMorphClass(),
            'promptable_id' => $promptable->getKey(),
            'category' => $category,
            'model' => $model,
            'system_prompt' => $systemPrompt,
            'user_prompt' => $userPrompt,
        ]);
    }

    private function buildAnalysisPrompt(string $jobText, string $resumeText, string $sourceSummary): string
    {
        return <<<PROMPT
Please analyze the following job posting against the candidate resume.

{$sourceSummary}

Job description:
{$jobText}

Candidate resume:
{$resumeText}
PROMPT;
    }

    private function jobSourceSummary(JobDescription $job, ?string $jobUrlOverride): string
    {
        if ($job->isManual()) {
            return 'Job description source: Manual input provided directly by the user.';
        }

        $source = $jobUrlOverride ?: $job->source_url;

        return sprintf('Job description URL: %s', $source);
    }

    private function buildTailorPrompt(string $jobText, string $resumeText, string $analysisFeedback): string
    {
        return <<<PROMPT
Create a tailored resume for the following role. Use markdown formatting and preserve truthful information from the existing resume while highlighting the most relevant details. Incorporate the evaluation feedback to ensure key gaps and opportunities are addressed.

Job description:
{$jobText}

Evaluation feedback:
{$analysisFeedback}

Existing resume:
{$resumeText}
PROMPT;
    }

    /**
     * Parse and validate structured JSON feedback from AI response.
     *
     * @return array{sentiment: string, highlights: array|null, key_phrases: array, sections: array}
     */
    private function parseStructuredFeedback(string $payload): array
    {
        // Try to parse JSON
        $decoded = json_decode($payload, true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            // Fallback: treat as plain markdown
            return [
                'sentiment' => 'good_match',
                'highlights' => null,
                'key_phrases' => [],
                'sections' => [
                    'summary' => $payload,
                    'relevant_experience' => null,
                    'gaps' => null,
                    'recommendations' => null,
                ],
            ];
        }

        // Validate sentiment is one of the four expected values
        $validSentiments = ['excellent_match', 'good_match', 'partial_match', 'weak_match'];
        $sentiment = $decoded['sentiment'] ?? 'good_match';
        if (! in_array($sentiment, $validSentiments, true)) {
            $sentiment = 'good_match';
        }

        // Validate highlights structure (should be array with integer keys)
        $highlights = $decoded['highlights'] ?? null;
        if ($highlights !== null && is_array($highlights)) {
            $highlights = array_map('intval', array_values($highlights));
        } else {
            $highlights = null;
        }

        // Validate key_phrases is actually an array of strings
        $keyPhrases = $decoded['key_phrases'] ?? [];
        if (! is_array($keyPhrases)) {
            $keyPhrases = [];
        } else {
            $keyPhrases = array_filter(array_map(function ($phrase) {
                return is_string($phrase) ? $phrase : null;
            }, $keyPhrases), fn ($phrase) => $phrase !== null);
        }

        // Validate sections exists and is an array before accessing nested keys
        $sections = $decoded['sections'] ?? [];
        if (! is_array($sections)) {
            $sections = [];
        }

        // Validate and normalize structure
        $structured = [
            'sentiment' => $sentiment,
            'highlights' => $highlights,
            'key_phrases' => array_values($keyPhrases),
            'sections' => [
                'summary' => $sections['summary'] ?? '',
                'relevant_experience' => $sections['relevant_experience'] ?? null,
                'gaps' => $sections['gaps'] ?? null,
                'recommendations' => $sections['recommendations'] ?? null,
            ],
        ];

        return $structured;
    }

    /**
     * Generate flattened markdown from structured sections for backward compatibility.
     */
    private function generateMarkdownFromStructured(array $structured): string
    {
        $sections = $structured['sections'] ?? [];

        $parts = array_filter([
            $sections['summary'] ?? null,
            $sections['relevant_experience'] ?? null,
            $sections['gaps'] ?? null,
            $sections['recommendations'] ?? null,
        ]);

        return implode("\n\n---\n\n", $parts);
    }
}
