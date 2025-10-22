<?php

return [
    'api_key' => env('OPENAI_API_KEY'),
    'timeout' => env('OPENAI_TIMEOUT', 180),
    'analysis' => [
        'model' => env('OPENAI_ANALYSIS_MODEL', 'gpt-5-nano'),
        'system_prompt' => env(
            'OPENAI_ANALYSIS_SYSTEM_PROMPT',
            'You are an experienced career coach and resume analyst helping a candidate decide if a job is a strong fit. '
            . "Review the provided job description and the candidate's resume. Highlight how well the "
            . 'candidate matches the role, identify the most relevant experience, and point out any gaps '
            . 'or missing qualifications. Recommend clear, actionable steps the candidate can take to '
            . 'strengthen their candidacy.'
        ),
    ],
    'tailor' => [
        'model' => env('OPENAI_TAILOR_MODEL', 'gpt-5-mini'),
        'system_prompt' => env(
            'OPENAI_TAILOR_SYSTEM_PROMPT',
            "You are a professional resume writer. Using the candidate's existing resume, evaluation feedback, and the target job "
            . 'description, craft a tailored resume that emphasizes matching skills, experience, and accomplishments. '
            . 'Preserve truthful information while rephrasing or reorganizing content for the strongest impact. '
            . 'Maintain markdown formatting with clear sections.'
        ),
    ],
    'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
];
