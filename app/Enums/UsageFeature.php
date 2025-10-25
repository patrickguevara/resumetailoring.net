<?php

namespace App\Enums;

enum UsageFeature: string
{
    case ResumeUpload = 'resume_uploads';
    case Evaluation = 'evaluations';
    case Tailoring = 'tailored_resumes';
    case CompanyResearch = 'company_research';

    public function label(): string
    {
        return match ($this) {
            self::ResumeUpload => 'resume upload',
            self::Evaluation => 'resume evaluation',
            self::Tailoring => 'tailored resume',
            self::CompanyResearch => 'company research',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::ResumeUpload => 'Resume uploads',
            self::Evaluation => 'Evaluations',
            self::Tailoring => 'Tailored resumes',
            self::CompanyResearch => 'Company research',
        };
    }
}

