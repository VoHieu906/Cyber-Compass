<?php

namespace App\Enums;

enum LessonProgressStatus: string
{
    case Started = 'started';
    case Completed = 'completed';
}
