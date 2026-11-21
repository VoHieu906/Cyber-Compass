<?php

namespace App\Enums;

/** The seven exercise question types (ARCHITECTURE D31). */
enum ExerciseQuestionType: string
{
    case SingleChoice = 'single_choice';
    case MultiChoice = 'multi_choice';
    case TrueFalse = 'true_false';
    case FillBlank = 'fill_blank';
    case Matching = 'matching';
    case Ordering = 'ordering';
    case Scenario = 'scenario';
}
