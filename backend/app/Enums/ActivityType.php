<?php

namespace App\Enums;

/** What a row in `learning_activities` records (D33). */
enum ActivityType: string
{
    case LessonStarted = 'lesson_started';
    case LessonCompleted = 'lesson_completed';
    /** Another visit to a lesson that was already completed (a review). */
    case StudySession = 'study_session';
    case ExerciseSubmitted = 'exercise_submitted';
    case AssessmentSubmitted = 'assessment_submitted';
    case LabCompleted = 'lab_completed';
}
