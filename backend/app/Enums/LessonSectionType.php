<?php

namespace App\Enums;

enum LessonSectionType: string
{
    case Prose = 'prose';
    case Callout = 'callout';
    case Steps = 'steps';
    case Code = 'code';
    case Diagram = 'diagram';
    case Visual3d = 'visual_3d';
}
