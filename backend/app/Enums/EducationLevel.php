<?php

namespace App\Enums;

enum EducationLevel: string
{
    case HighSchool = 'high_school';
    case Undergraduate = 'undergraduate';
    case Graduate = 'graduate';
    case Other = 'other';
}
