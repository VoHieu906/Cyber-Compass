<?php

use App\Models\Course;
use App\Models\CourseGraph;
use App\Models\LessonArchitecture;
use App\Modules\Admin\Console\E2eCourseMapCommand;

/* The test course map for Playwright (F3): installs where nothing exists, removes only itself. */

it('installs a valid map and removes exactly what it installed', function () {
    $this->artisan('cybercompass:e2e-course-map install')->assertSuccessful();
    expect(CourseGraph::query()->count())->toBe(1)->and(LessonArchitecture::query()->count())->toBe(3);
    $this->artisan('content:check-graphs')->assertSuccessful();
    $this->getJson('/api/v1/courses/'.E2eCourseMapCommand::COURSE)->assertJsonPath('data.graph.lessons.0.shape', 'pipeline');

    $this->artisan('cybercompass:e2e-course-map remove')->assertSuccessful();
    expect(CourseGraph::query()->count())->toBe(0)->and(LessonArchitecture::query()->count())->toBe(0);
});

it('never touches a real map', function () {
    $course = Course::query()->where('slug', E2eCourseMapCommand::COURSE)->firstOrFail();
    $real = ['version' => 1, 'problem' => 'A real map.', 'layout' => 'auto', 'edges' => []];
    CourseGraph::query()->create(['course_id' => $course->id, 'graph' => $real]);

    $this->artisan('cybercompass:e2e-course-map install')->assertSuccessful();
    $this->artisan('cybercompass:e2e-course-map remove')->assertSuccessful();

    expect(CourseGraph::query()->first()->graph)->toEqual($real);
});

it('refuses to run in production', function () {
    app()->detectEnvironment(fn () => 'production');
    $this->artisan('cybercompass:e2e-course-map install')->assertFailed();
    expect(CourseGraph::query()->count())->toBe(0);
});
