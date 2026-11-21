<?php

namespace App\Enums;

/** The kinds of notification a learner can receive and switch per channel (D34). */
enum NotificationType: string
{
    /** A new lesson was published on a topic in my roadmap. */
    case Recommendation = 'recommendation';
    case LessonCompleted = 'lesson_completed';
    /** A course finished, a roadmap quarter reached, a 7, 30 or 100-day streak. */
    case Milestone = 'milestone';
    /** No study yet today, sent at 18:00 local time. */
    case Reminder = 'reminder';
    case AssessmentResult = 'assessment_result';
}
