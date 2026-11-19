<?php

namespace App\Modules\Catalog\Actions;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;

final class EnrollInCourse
{
    /** Idempotent: enrolling twice keeps the original enrollment date. */
    public function handle(User $user, Course $course): Enrollment
    {
        return Enrollment::query()->firstOrCreate(
            ['user_id' => $user->id, 'course_id' => $course->id],
            ['enrolled_at' => now()],
        );
    }
}
