<?php

namespace App\Enums;

enum RuleDefinitionKind: string
{
    case EvidenceRule = 'evidence_rule';
    case ClinicianRule = 'clinician_rule';
    case UserPolicy = 'user_policy';
    case ProgramRule = 'program_rule';
    case AiSuggestion = 'ai_suggestion';
}
